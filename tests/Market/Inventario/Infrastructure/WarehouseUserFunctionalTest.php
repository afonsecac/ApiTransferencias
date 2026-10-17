<?php

declare(strict_types=1);

namespace App\Tests\Market\Inventario\Infrastructure;

use App\Entity\User;
use App\Market\Inventario\Infrastructure\Http\CreateWarehouseDto;
use App\Market\Inventario\Infrastructure\Http\InviteWarehouseUserDto;
use App\Market\Inventario\Infrastructure\Http\UpdateWarehouseDto;
use App\Market\Inventario\Infrastructure\Http\WarehouseController;
use App\Market\Organizacion\Infrastructure\Http\CostCenterDto;
use App\Market\Organizacion\Infrastructure\Http\CreateOrgUnitDto;
use App\Market\Organizacion\Infrastructure\Http\OrgUnitController;
use App\Security\ExternalRoles;
use App\Market\Inventario\Infrastructure\Http\SetWarehouseUserActiveDto;
use App\Market\Inventario\Infrastructure\Http\WarehouseUserController;
use App\Market\Inventario\Infrastructure\Http\WarehouseUserPermissionsDto;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * COMR-287 contra Postgres real: el vínculo usuario–almacén, la invitación con el flujo de activación existente y el contrato HTTP.
 *
 * @covers \App\Market\Inventario\Infrastructure\Persistence\DoctrineWarehouseUserRepository
 * @covers \App\Market\Inventario\Infrastructure\Persistence\DashboardWarehouseStaff
 * @covers \App\Market\Inventario\Infrastructure\Http\WarehouseUserController
 */
final class WarehouseUserFunctionalTest extends ProviderFunctionalTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        // Quien invita pertenece a una empresa: el usuario nuevo queda en ella.
        $this->admin = $this->createAdminUser();
        $this->admin->setCompany($this->createClient());
        $this->em->flush();
        $this->authenticateAsAdmin($this->admin);
    }

    private function controller(): WarehouseUserController
    {
        return self::getContainer()->get(WarehouseUserController::class);
    }

    /** @return array<mixed> */
    private function body(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function mainWarehouseId(): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT id FROM market_warehouse WHERE is_main');
    }

    private function invite(string $email, ?string $first = 'Luis', ?string $last = 'Gómez', bool $stock = true, bool $deliveries = false, ?int $warehouse = null, bool $external = false): Response
    {
        return $this->controller()->invite($warehouse ?? $this->mainWarehouseId(), new InviteWarehouseUserDto($email, $first, $last, $stock, null, null, $deliveries, $external));
    }

    public function testInvitingANewPersonCreatesTheAccountAndLeavesThemInvited(): void
    {
        $response = $this->invite('nuevo-almacen@example.test');

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $body = $this->body($response);
        $this->assertSame('invited', $body['status']);
        $this->assertSame('Luis Gómez', $body['fullName']);
        $this->assertTrue($body['permissions']['stock']);
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'nuevo-almacen@example.test']);
        $this->assertNotNull($user);
        $this->assertNotTrue($user->isIsCheckValidation(), 'la cuenta no está activada');
        $this->assertSame($this->admin->getCompany()?->getId(), $user->getCompany()?->getId());
    }

    public function testInvitingAnExistingUserLinksThemWithoutCreatingAnotherAccount(): void
    {
        $before = (int) $this->em->getConnection()->fetchOne('SELECT count(*) FROM "user"');

        $response = $this->invite($this->admin->getEmail() ?? '', null, null, deliveries: true);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $this->assertSame('active', $this->body($response)['status']);
        $this->assertSame($before, (int) $this->em->getConnection()->fetchOne('SELECT count(*) FROM "user"'));
    }

    public function testTheSamePersonIsNotLinkedTwiceToTheSameWarehouse(): void
    {
        $this->invite('doble@example.test');

        $second = $this->invite('doble@example.test');

        $this->assertSame(Response::HTTP_CONFLICT, $second->getStatusCode());
        $this->assertSame('WAREHOUSE_USER_ALREADY_LINKED', $this->body($second)['error']['code']);
    }

    public function testAnInvitationWithoutPermissionsOrWithoutANameAnswers422(): void
    {
        $noPermission = $this->invite('sinpermiso@example.test', stock: false);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $noPermission->getStatusCode());

        $noName = $this->invite('sinnombre@example.test', null, null);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $noName->getStatusCode());
        $this->assertSame('INVALID_WAREHOUSE_USER', $this->body($noName)['error']['code']);
    }

    public function testItListsTheUsersWithTheirThreeStates(): void
    {
        $w = $this->mainWarehouseId();
        $this->invite('invitado@example.test');
        $adminId = (int) $this->admin->getId();
        $this->invite($this->admin->getEmail() ?? '', null, null);
        $this->controller()->setActive($w, $adminId, new SetWarehouseUserActiveDto(false));
        $this->invite('activo@example.test');
        $this->em->getConnection()->executeStatement("UPDATE \"user\" SET is_check_validation = TRUE WHERE email = 'activo@example.test'");
        $this->em->clear();

        $rows = $this->body($this->controller()->list($w));

        $byEmail = array_column($rows, 'status', 'email');
        $this->assertSame('invited', $byEmail['invitado@example.test']);
        $this->assertSame('active', $byEmail['activo@example.test']);
        $this->assertSame('inactive', $byEmail[$this->admin->getEmail()]);
    }

    public function testPermissionsChangeButNeverToNone(): void
    {
        $w = $this->mainWarehouseId();
        $userId = $this->body($this->invite('permisos@example.test'))['userId'];

        $changed = $this->controller()->permissions($w, $userId, new WarehouseUserPermissionsDto(false, true, true, false));
        $this->assertSame(Response::HTTP_OK, $changed->getStatusCode(), (string) $changed->getContent());
        $this->assertSame(['stock' => false, 'assembly' => true, 'transfers' => true, 'deliveries' => false], $this->body($changed)['permissions']);

        $none = $this->controller()->permissions($w, $userId, new WarehouseUserPermissionsDto(false, false, false, false));
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $none->getStatusCode());
    }

    public function testDeactivatingKeepsTheLink(): void
    {
        $w = $this->mainWarehouseId();
        $userId = $this->body($this->invite('baja@example.test'))['userId'];

        $response = $this->controller()->setActive($w, $userId, new SetWarehouseUserActiveDto(false));

        $this->assertSame('inactive', $this->body($response)['status']);
        $this->assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT count(*) FROM market_warehouse_user WHERE user_id = :u', ['u' => $userId]));
    }

    public function testResendingWorksWhileInvitedAndAnswers409OnceActivated(): void
    {
        $w = $this->mainWarehouseId();
        $userId = $this->body($this->invite('reenvio@example.test'))['userId'];

        $this->assertSame(Response::HTTP_OK, $this->controller()->resend($w, $userId)->getStatusCode());

        $this->em->getConnection()->executeStatement("UPDATE \"user\" SET is_check_validation = TRUE WHERE email = 'reenvio@example.test'");
        $this->em->clear();
        $again = $this->controller()->resend($w, $userId);
        $this->assertSame(Response::HTTP_CONFLICT, $again->getStatusCode());
        $this->assertSame('ACCOUNT_ALREADY_ACTIVATED', $this->body($again)['error']['code']);
    }

    public function testAPersonCanBeInTwoWarehousesWithDifferentPermissions(): void
    {
        $main = $this->mainWarehouseId();
        $this->em->getConnection()->executeStatement(<<<'SQL'
            INSERT INTO market_warehouse (slug, name, status, is_main, org_unit_id, cost_center_id, municipality_id, created_at, updated_at)
            SELECT 'segundo-usuarios', 'Segundo', 'active', FALSE, org_unit_id, cost_center_id, municipality_id, NOW(), NOW() FROM market_warehouse WHERE is_main
        SQL);
        $second = (int) $this->em->getConnection()->fetchOne("SELECT id FROM market_warehouse WHERE slug = 'segundo-usuarios'");

        $this->invite('dos@example.test', stock: true, warehouse: $main);
        $response = $this->invite('dos@example.test', stock: false, deliveries: true, warehouse: $second);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $this->assertFalse($this->body($response)['permissions']['stock']);
        $this->assertTrue($this->body($response)['permissions']['deliveries']);
    }

    public function testAnUnknownWarehouseAnswers404(): void
    {
        $this->assertSame(Response::HTTP_NOT_FOUND, $this->controller()->list(999999)->getStatusCode());
    }

    /** Un almacén de un socio: unidad con el tipo `warehouse_partner` como dueña. */
    private function partnerWarehouseId(): int
    {
        $unit = json_decode((string) self::getContainer()->get(OrgUnitController::class)->create(new CreateOrgUnitDto(
            countryCode: 'PA',
            name: 'Socio externo',
            legalName: 'Socio externo SA',
            taxId: 'EXT-1',
            address: 'Calle 50, Ciudad de Panamá',
            billingEmail: 'billing@socio.example',
            types: ['warehouse_partner'],
            fiscalData: ['ruc' => 'EXT-1', 'dv' => '12'],
            costCenters: [new CostCenterDto('PRINCIPAL', 'Principal', true)],
        ))->getContent(), true, flags: JSON_THROW_ON_ERROR)['id'];
        $warehouses = self::getContainer()->get(WarehouseController::class);
        $id = $this->body($warehouses->create(new CreateWarehouseDto('Almacén del socio externo')))['id'];
        $warehouses->update($id, new UpdateWarehouseDto('Almacén del socio externo', null, null, $unit, null, false, false, false));

        return $id;
    }

    public function testInvitingAnExternalPersonCreatesAnAccountWithTheExternalRoleAndNoDashboardRole(): void
    {
        $warehouse = $this->partnerWarehouseId();

        $response = $this->controller()->invite($warehouse, new InviteWarehouseUserDto('persona-socio@example.test', 'Rosa', 'Díaz', true, null, null, null, true));

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $body = $this->body($response);
        $this->assertSame('invited', $body['status']);
        $this->assertTrue($body['external']);
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'persona-socio@example.test']);
        $this->assertContains(ExternalRoles::WAREHOUSE_PARTNER, $user->getRoles());
        $this->assertNotContains('ROLE_SYSTEM_USER', $user->getRoles());
        $this->assertNotContains('ROLE_API_ADMIN', $user->getRoles());
    }

    public function testExternalPeopleAreNotInvitedToTheMainWarehouseOfThePlatform(): void
    {
        $before = (int) $this->em->getConnection()->fetchOne('SELECT count(*) FROM "user"');

        $response = $this->invite('externo-en-principal@example.test', external: true);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), (string) $response->getContent());
        $this->assertSame($before, (int) $this->em->getConnection()->fetchOne('SELECT count(*) FROM "user"'), 'no se crea la cuenta');
    }

    public function testADashboardUserIsNeverTurnedIntoAnExternalPerson(): void
    {
        $warehouse = $this->partnerWarehouseId();

        $response = $this->controller()->invite($warehouse, new InviteWarehouseUserDto($this->admin->getEmail(), null, null, true, null, null, null, true));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), (string) $response->getContent());
        $this->assertContains('ROLE_SUPER_ADMIN', $this->em->getRepository(User::class)->find($this->admin->getId())->getRoles());
    }
}
