<?php

namespace App\Tests\Service;

use App\DTO\CreateClientPackageDto;
use App\Entity\Account;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationPricePackage;
use App\Entity\Environment;
use App\Exception\MyCurrentException;
use App\Repository\CommunicationPricePackageRepository;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use App\Service\CommunicationPackageService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @covers \App\Service\CommunicationPackageService
 */
class CommunicationPackageServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private CommunicationPackageService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->service = new CommunicationPackageService(
            $this->em,
            $this->createMock(Security::class),
            $this->createMock(ParameterBagInterface::class),
            $this->createMock(MailerInterface::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(UserPasswordHasherInterface::class),
            $this->createMock(EnvironmentRepository::class),
            $this->createMock(SysConfigRepository::class),
            $this->createMock(SerializerInterface::class),
        );
    }

    public function testCreateThrowsWhenTenantNotFound(): void
    {
        $dto = new CreateClientPackageDto(tenantId: 99, priceClientPackageId: 1);

        $this->em->method('getRepository')->willReturnCallback(
            fn(string $class) => match ($class) {
                Account::class => $this->repoReturning(null),
                default        => $this->createMock(EntityRepository::class),
            }
        );

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('Tenant not found');

        $this->service->create($dto);
    }

    public function testCreateThrowsWhenPricePackageNotFound(): void
    {
        $dto = new CreateClientPackageDto(tenantId: 1, priceClientPackageId: 99);

        $tenant = $this->createMock(Account::class);

        $this->em->method('getRepository')->willReturnCallback(
            fn(string $class) => match ($class) {
                Account::class                => $this->repoReturning($tenant),
                CommunicationPricePackage::class => $this->repoReturning(null),
                default                       => $this->createMock(EntityRepository::class),
            }
        );

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('Price package not found');

        $this->service->create($dto);
    }

    public function testCreateThrowsWhenEnvironmentNotFound(): void
    {
        $dto = new CreateClientPackageDto(tenantId: 1, priceClientPackageId: 1, environmentId: 99);

        $tenant       = $this->createMock(Account::class);
        $pricePackage = $this->buildPricePackage();

        $this->em->method('getRepository')->willReturnCallback(
            fn(string $class) => match ($class) {
                Account::class                => $this->repoReturning($tenant),
                CommunicationPricePackage::class => $this->repoReturning($pricePackage),
                Environment::class            => $this->repoReturning(null),
                default                       => $this->createMock(EntityRepository::class),
            }
        );

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('Environment not found');

        $this->service->create($dto);
    }

    public function testCreatePersistsAndReturnsPackage(): void
    {
        $dto = new CreateClientPackageDto(
            tenantId: 1,
            priceClientPackageId: 1,
            name: 'Plan Especial',
            amount: 20.0,
            currency: 'USD',
        );

        $tenant       = $this->createMock(Account::class);
        $pricePackage = $this->buildPricePackage();

        $this->em->method('getRepository')->willReturnCallback(
            fn(string $class) => match ($class) {
                Account::class                => $this->repoReturning($tenant),
                CommunicationPricePackage::class => $this->repoReturning($pricePackage),
                default                       => $this->createMock(EntityRepository::class),
            }
        );

        $this->em->expects($this->once())->method('persist')
            ->with($this->isInstanceOf(CommunicationClientPackage::class));
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->create($dto);

        $this->assertInstanceOf(CommunicationClientPackage::class, $result);
        $this->assertSame('Plan Especial', $result->getName());
        $this->assertSame(20.0, $result->getAmount());
        $this->assertSame('USD', $result->getCurrency());
    }

    public function testCreateInheritsFieldsFromPricePackageWhenNotOverridden(): void
    {
        $dto = new CreateClientPackageDto(tenantId: 1, priceClientPackageId: 1);

        $tenant       = $this->createMock(Account::class);
        $pricePackage = $this->buildPricePackage(name: 'Precio Base', amount: 10.0, currency: 'CUP');

        $this->em->method('getRepository')->willReturnCallback(
            fn(string $class) => match ($class) {
                Account::class                => $this->repoReturning($tenant),
                CommunicationPricePackage::class => $this->repoReturning($pricePackage),
                default                       => $this->createMock(EntityRepository::class),
            }
        );
        $this->em->method('persist');
        $this->em->method('flush');

        $result = $this->service->create($dto);

        $this->assertSame('Precio Base', $result->getName());
        $this->assertSame(10.0, $result->getAmount());
        $this->assertSame('CUP', $result->getCurrency());
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function repoReturning(mixed $value): EntityRepository&MockObject
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->willReturn($value);
        return $repo;
    }

    private function buildPricePackage(
        string $name = 'Paquete Test',
        float  $amount = 5.0,
        string $currency = 'USD',
    ): CommunicationPricePackage {
        $p = new CommunicationPricePackage();
        $p->setName($name);
        $p->setAmount($amount);
        $p->setCurrency($currency);
        $p->setDataInfo(['benefits' => [], 'tags' => [], 'service' => [], 'destination' => [], 'validity' => []]);
        return $p;
    }
}
