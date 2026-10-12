<?php

namespace App\Tests\Service;

use App\Entity\Account;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationSaleInfo;
use App\Enums\CommunicationStateEnum;
use App\Exception\MyCurrentException;
use App\Provider\ProviderContextFactory;
use App\Provider\ProviderRegistry;
use App\Provider\ProviderResolver;
use App\Repository\ClientProviderRoutingRepository;
use App\Repository\CommunicationSaleInfoRepository;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use App\Service\BalanceService;
use App\Service\CommunicationSaleService;
use App\Service\ConfigureSequenceService;
use App\Service\HistoricalSaleService;
use App\Service\NotificationCenterService;
use App\Service\Pricing\PackageCatalogResolver;
use App\Service\Provider\ProviderAvailabilityService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * COMR-62 (C3, ADR-001): los sitios de CommunicationSaleService que bifurcan por subtipo de venta lanzan ante
 * uno desconocido en lugar de tratarlo en silencio como «paquete».
 *
 * @covers \App\Service\CommunicationSaleService
 */
class CommunicationSaleServiceUnknownSubtypeTest extends TestCase
{
    private CommunicationSaleInfoRepository&MockObject $repository;
    private CommunicationSaleService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(CommunicationSaleInfoRepository::class);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($this->repository);
        $em->method('getConnection')->willReturn($this->createMock(Connection::class));
        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->method('getTableName')->willReturn('communication_sale_info');
        $em->method('getClassMetadata')->willReturn($classMetadata);

        $sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $providerResolver = new ProviderResolver($sysConfigRepo, $this->createMock(ClientProviderRoutingRepository::class), new NullLogger());

        $this->service = new CommunicationSaleService(
            $em,
            $this->createMock(Security::class),
            $this->createMock(ParameterBagInterface::class),
            $this->createMock(MailerInterface::class),
            new NullLogger(),
            $this->createMock(UserPasswordHasherInterface::class),
            $this->createMock(EnvironmentRepository::class),
            $sysConfigRepo,
            $this->createMock(SerializerInterface::class),
            new ProviderRegistry([]),
            $providerResolver,
            new ProviderContextFactory($providerResolver),
            $this->createMock(ConfigureSequenceService::class),
            $this->createMock(MessageBusInterface::class),
            $this->createMock(HistoricalSaleService::class),
            $this->createMock(BalanceService::class),
            $this->createMock(NotificationCenterService::class),
            $this->createMock(ProviderAvailabilityService::class),
            $this->createMock(PackageCatalogResolver::class),
            $this->createMock(\App\Provider\ProviderDispatchResolver::class),
            $this->createMock(\App\Provider\PromotionProviderDispatchResolver::class),
            $this->createMock(\App\Service\Provider\SaleProviderFailoverService::class),
            $this->createMock(\App\Service\DispatchPauseLedger::class),
        );
    }

    private function unknownSubtypeSale(): CommunicationSaleInfo
    {
        $sale = new CommunicationSaleInfo();
        (new \ReflectionProperty($sale, 'id'))->setValue($sale, 77);
        $sale->setTenant($this->createMock(Account::class));
        $sale->setCatalogPackage(new CommunicationPackage());
        $sale->setState(CommunicationStateEnum::PENDING);
        $sale->setStateProcess('Pending');
        $sale->setProvider('ETECSA');
        $sale->setCreatedAt(new \DateTimeImmutable('now'));

        return $sale;
    }

    public function testCheckStatusOrderThrowsForAnUnknownSubtypeInsteadOfPollingItAsAPackage(): void
    {
        $this->repository->method('find')->with(77)->willReturn($this->unknownSubtypeSale());

        try {
            $this->service->checkStatusOrder(77);
            $this->fail('Un subtipo desconocido debe lanzar.');
        } catch (MyCurrentException $e) {
            $this->assertSame('UNSUPPORTED_SALE_SUBTYPE', $e->getCodeWork());
        }
    }

    public function testResolvingAwaitingSalesThrowsForAnUnknownSubtypeInsteadOfRoutingItAsASale(): void
    {
        $this->repository->method('findAwaitingProvider')->willReturn([$this->unknownSubtypeSale()]);

        try {
            $this->service->dispatchAwaitingProviderSales();
            $this->fail('Un subtipo desconocido debe lanzar.');
        } catch (MyCurrentException $e) {
            $this->assertSame('UNSUPPORTED_SALE_SUBTYPE', $e->getCodeWork());
        }
    }
}
