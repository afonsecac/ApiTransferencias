<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Entity\User;
use App\Market\Inventario\Application\CreateWarehouse;
use App\Market\Inventario\Domain\WarehouseUser;
use App\Market\Inventario\Domain\WarehouseUserPermissions;
use App\Market\Inventario\Domain\WarehouseUserRepository;
use App\Service\UserService;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * COMR-288: el rol externo del socio de almacén contra el kernel real (firewall, access_control, Voter y controladores). Un
 * usuario con permisos sobre el almacén A no puede leer nada del B, y el rol externo no entra en el dashboard ni en las rutas de
 * administración del mercado.
 *
 * @coversNothing
 */
final class WarehousePartnerIsolationFunctionalTest extends ProviderFunctionalTestCase
{
    private int $warehouseA;
    private int $warehouseB;
    private User $partner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->warehouseA = (int) $this->em->getConnection()->fetchOne('SELECT id FROM market_warehouse WHERE is_main');
        $this->warehouseB = $this->createWarehouse('Almacén de otro socio');
        $this->partner = $this->createUser('socio-a@example.test', ['ROLE_MARKET_WAREHOUSE_USER']);
        $this->link($this->partner, $this->warehouseA, new WarehouseUserPermissions(stock: true));
    }

    private function createWarehouse(string $name): int
    {
        $details = (self::getContainer()->get(CreateWarehouse::class))($name);

        return (int) $details->warehouse->id();
    }

    /** @param list<string> $roles */
    private function createUser(string $email, array $roles): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setPassword('irrelevant-hash')
            ->setFirstName('Socio')
            ->setLastName('Prueba')
            ->setRoles($roles)
            ->setIsActive(true)
            ->setIsCheckValidation(true);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function link(User $user, int $warehouseId, WarehouseUserPermissions $permissions): void
    {
        self::getContainer()->get(WarehouseUserRepository::class)->save(WarehouseUser::link((int) $user->getId(), $warehouseId, $permissions));
    }

    /** @return array{int, mixed} */
    private function get(string $uri, ?User $as): array
    {
        $server = ['HTTP_ACCEPT' => 'application/json'];
        if ($as !== null) {
            // Tras clear() el usuario de una llamada anterior queda desligado: se recarga por id.
            $as = $this->em->find(User::class, $as->getId());
            $as->setCurrentIp('127.0.0.1');
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . self::getContainer()->get(UserService::class)->createSession($as, '127.0.0.1');
        }
        $this->em->clear();

        $response = self::$kernel->handle(Request::create($uri, 'GET', [], [], [], $server));

        return [$response->getStatusCode(), json_decode((string) $response->getContent(), true)];
    }

    public function testThePartnerSeesOnlyItsOwnWarehouses(): void
    {
        [$status, $body] = $this->get('/warehouse/api/me/warehouses', $this->partner);

        $this->assertSame(Response::HTTP_OK, $status, json_encode($body));
        $this->assertSame([$this->warehouseA], array_column($body, 'id'));
        $this->assertTrue($body[0]['permissions']['stock']);
        $this->assertFalse($body[0]['permissions']['deliveries']);
    }

    public function testThePartnerReadsTheStockOfItsWarehouse(): void
    {
        [$status, $body] = $this->get("/warehouse/api/warehouses/{$this->warehouseA}/stock", $this->partner);

        $this->assertSame(Response::HTTP_OK, $status, json_encode($body));
        $this->assertIsArray($body);
    }

    public function testThePartnerCannotReadTheStockOfAnotherWarehouse(): void
    {
        [$status] = $this->get("/warehouse/api/warehouses/{$this->warehouseB}/stock", $this->partner);

        $this->assertSame(Response::HTTP_FORBIDDEN, $status);
    }

    public function testAWarehouseThatDoesNotExistIsForbiddenNotRevealed(): void
    {
        [$status] = $this->get('/warehouse/api/warehouses/999999/stock', $this->partner);

        $this->assertSame(Response::HTTP_FORBIDDEN, $status);
    }

    public function testAPermissionOnOneWarehouseDoesNotOpenTheOther(): void
    {
        $other = $this->createUser('socio-b@example.test', ['ROLE_MARKET_WAREHOUSE_USER']);
        $this->link($other, $this->warehouseB, new WarehouseUserPermissions(stock: true));

        [$a] = $this->get("/warehouse/api/warehouses/{$this->warehouseA}/stock", $other);
        [$b] = $this->get("/warehouse/api/warehouses/{$this->warehouseB}/stock", $other);

        $this->assertSame(Response::HTTP_FORBIDDEN, $a);
        $this->assertSame(Response::HTTP_OK, $b);
    }

    public function testALinkWithoutTheStockPermissionCannotReadTheStock(): void
    {
        $courier = $this->createUser('entregas@example.test', ['ROLE_MARKET_WAREHOUSE_USER']);
        $this->link($courier, $this->warehouseA, new WarehouseUserPermissions(deliveries: true));

        [$status] = $this->get("/warehouse/api/warehouses/{$this->warehouseA}/stock", $courier);

        $this->assertSame(Response::HTTP_FORBIDDEN, $status);
    }

    public function testAnExternalUserGetsA403InTheDashboardAndInTheMarketAdministration(): void
    {
        foreach (['/dashboard/api/market/stock', '/dashboard/api/market/warehouses', '/dashboard/api/market/gift-cards', '/dashboard/api/users'] as $uri) {
            [$status] = $this->get($uri, $this->partner);

            $this->assertSame(Response::HTTP_FORBIDDEN, $status, $uri);
        }
    }

    public function testADashboardAdministratorDoesNotEnterThePartnerPrefix(): void
    {
        $admin = $this->createAdminUser();

        [$status] = $this->get('/warehouse/api/me/warehouses', $admin);

        $this->assertSame(Response::HTTP_FORBIDDEN, $status);
    }

    public function testWithoutATokenThePartnerPrefixIsA401(): void
    {
        [$status] = $this->get('/warehouse/api/me/warehouses', null);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $status);
    }
}
