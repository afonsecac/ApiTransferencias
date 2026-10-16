<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Entity\User;
use App\Service\TwoFactorService;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use App\Tests\Support\WithJwtSecret;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * COMR-288: el 2FA es obligatorio para el rol externo del socio de almacén, sea cual sea la política global (`2fa.mode`), y sin él
 * no se obtiene sesión ni se pasa por `/warehouse/api`. Contra Postgres real y el kernel completo.
 *
 * @coversNothing
 */
final class WarehousePartnerTwoFactorFunctionalTest extends ProviderFunctionalTestCase
{
    use WithJwtSecret;

    private const PASSWORD = 'Una-clave-larga-123!';

    protected function setUp(): void
    {
        $this->useLongJwtSecret();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->restoreJwtSecret();
    }

    /** @param list<string> $roles */
    private function user(string $email, array $roles, bool $twoFactor = false): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setFirstName('Socio')
            ->setLastName('Dos Factores')
            ->setRoles($roles)
            ->setIsActive(true)
            ->setIsCheckValidation(true);
        $user->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, self::PASSWORD));
        $user->setTwoFactorEnabled($twoFactor);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function service(): TwoFactorService
    {
        return self::getContainer()->get(TwoFactorService::class);
    }

    public function testTheExternalRoleRequiresTwoFactorEvenWhenThePolicyIsOptional(): void
    {
        $partner = $this->user('socio-2fa@example.test', ['ROLE_MARKET_WAREHOUSE_USER']);

        $this->assertSame('optional', $this->service()->getConfig()['mode']);
        $this->assertTrue($this->service()->requiresTwoFactor($partner));
        $this->assertTrue($this->service()->requiresEnrollment($partner));
    }

    public function testAnOrdinaryUserIsStillGovernedByTheGlobalPolicy(): void
    {
        $staff = $this->user('personal-2fa@example.test', ['ROLE_SYSTEM_USER']);

        $this->assertFalse($this->service()->requiresTwoFactor($staff));
    }

    public function testAPartnerWithTwoFactorGoesToVerificationNotToEnrollment(): void
    {
        $partner = $this->user('socio-con-2fa@example.test', ['ROLE_MARKET_WAREHOUSE_USER'], twoFactor: true);

        $this->assertTrue($this->service()->requiresTwoFactor($partner));
        $this->assertFalse($this->service()->requiresEnrollment($partner));
    }

    public function testLoggingInWithoutTwoFactorGivesNoSessionOnlyAnEnrollmentToken(): void
    {
        $this->user('socio-login@example.test', ['ROLE_MARKET_WAREHOUSE_USER']);

        $response = self::$kernel->handle(Request::create('/dashboard/api/login', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode(['username' => 'socio-login@example.test', 'password' => self::PASSWORD], JSON_THROW_ON_ERROR)));
        $body = json_decode((string) $response->getContent(), true);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $this->assertTrue($body['requires2fa']);
        $this->assertTrue($body['requiresEnrollment']);
        $this->assertNotEmpty($body['pendingToken']);
        $this->assertArrayNotHasKey('token', $body);
    }

    public function testAJwtOfAPartnerWithoutTwoFactorIsRejectedInThePartnerPrefix(): void
    {
        $partner = $this->user('socio-sin-2fa@example.test', ['ROLE_MARKET_WAREHOUSE_USER']);
        $partner->setCurrentIp('127.0.0.1');
        $token = self::getContainer()->get(\App\Service\UserService::class)->createSession($partner, '127.0.0.1');
        $this->em->clear();

        $response = self::$kernel->handle(Request::create('/warehouse/api/me/warehouses', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]));

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertStringContainsString('Two-factor', (string) $response->getContent());
    }
}
