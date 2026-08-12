<?php

namespace App\Tests\Service;

use App\Entity\Account;
use App\Entity\Client;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationNationality;
use App\Entity\CommunicationOffice;
use App\Entity\CommunicationPricePackage;
use App\Entity\CommunicationProduct;
use App\Entity\CommunicationSalePackage;
use App\Entity\CommunicationSaleRecharge;
use App\Entity\Environment;
use App\Provider\ProviderContextFactory;
use App\Provider\ProviderRegistry;
use App\Provider\ProviderResolver;
use App\Repository\ClientProviderRoutingRepository;
use App\Repository\CommunicationClientPackageRepository;
use App\Repository\CommunicationPricePackageRepository;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use App\Service\BalanceService;
use App\Service\CommunicationSaleService;
use App\Service\ConfigureSequenceService;
use App\Service\HistoricalSaleService;
use App\Service\NotificationCenterService;
use App\Service\Pricing\PackageSalePriceResolver;
use App\Service\Provider\CurrencyConversionService;
use App\Service\Provider\ProductPriceResolver;
use App\Service\Provider\ProviderAvailabilityService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @covers \App\Service\CommunicationSaleService
 * @covers \App\Service\Pricing\PackageSalePriceResolver
 *
 * Test de regresión del bug que motivó PackageSalePriceResolver: antes de
 * este rediseño, processRecharge()/processReserve() cobraban
 * CommunicationClientPackage.amount mientras que executeSale() cobraba
 * CommunicationClientPackage.priceClientPackage.amount — dos campos que
 * podían divergir tras un cambio de tarifa del proveedor (el refresco
 * periódico de catálogo solo actualizaba el segundo). Este test usa un
 * PackageSalePriceResolver REAL (no un doble) para demostrar que ambos
 * flujos consultan ahora exactamente el mismo cálculo y cobran el mismo
 * importe para el mismo paquete y la misma cuenta.
 */
class CommunicationSaleServicePricingTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private BalanceService&MockObject $balanceService;
    private CommunicationClientPackageRepository&MockObject $packageRepo;
    private CommunicationSaleService $service;
    private CommunicationClientPackage $package;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $security = $this->createMock(Security::class);
        $this->balanceService = $this->createMock(BalanceService::class);
        $availabilityService = $this->createMock(ProviderAvailabilityService::class);
        $availabilityService->method('canDispatchTo')->willReturn(false);

        $account = $this->accountWithCurrency('USD');
        $security->method('getUser')->willReturn($account);

        // El producto es la fuente de precio cuando no hay contrato — mismo
        // costo mayorista y misma moneda que la del cliente, así
        // ProductPriceResolver lo devuelve tal cual, sin necesitar mockear
        // CurrencyConversionService.
        $product = (new CommunicationProduct())
            ->setEnvironment($this->createMock(Environment::class))
            ->setPackageId(555)
            ->setPackageType('RECHARGE')
            ->setPrice(17.5)
            ->setPriceCurrency('USD')
            ->setEnabled(true)
            ->setDescription('Producto de regresión')
            ->setProvider('ETECSA')
            ->setExternalRef('555');

        // Paquete SIN contrato (priceClientPackage null, product directo) —
        // exactamente el caso que antes divergía: sin PackageSalePriceResolver,
        // processRecharge() habría leído $package->amount (el snapshot crudo,
        // que aquí se deja deliberadamente distinto del costo real del
        // producto) y executeSale() habría leído
        // $package->priceClientPackage->amount (null, al no haber contrato).
        $this->package = (new CommunicationClientPackage())
            ->setTenant($account)
            ->setProduct($product)
            ->setName('Paquete de regresión')
            ->setDescription('Paquete de regresión')
            ->setAmount(999.0) // snapshot crudo deliberadamente "envenenado"
            ->setCurrency('EUR') // y en una moneda distinta, a propósito
            ->setActiveEndAt(new \DateTimeImmutable('+1 year'));

        $this->packageRepo = $this->createMock(CommunicationClientPackageRepository::class);
        $this->packageRepo->method('getPackageById')->willReturn($this->package);

        $commercialOffice = $this->createMock(CommunicationOffice::class);
        $nationality = $this->createMock(CommunicationNationality::class);
        $this->em->method('getRepository')->willReturnMap([
            [CommunicationClientPackage::class, $this->packageRepo],
            [CommunicationOffice::class, $this->offices($commercialOffice)],
            [CommunicationNationality::class, $this->nationalities($nationality)],
        ]);

        $this->balanceService->method('hasAvailableBalance')->willReturn(true);

        $parameters = $this->createMock(ParameterBagInterface::class);
        $mailer = $this->createMock(MailerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $environmentRepository = $this->createMock(EnvironmentRepository::class);
        $sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $serializer = $this->createMock(SerializerInterface::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $historicalSaleService = $this->createMock(HistoricalSaleService::class);
        $notificationCenter = $this->createMock(NotificationCenterService::class);
        $configureSequence = $this->createMock(ConfigureSequenceService::class);
        $configureSequence->method('getLastSequence')->willReturn(1);

        $routingRepo = $this->createMock(ClientProviderRoutingRepository::class);
        $routingRepo->method('findActiveProviderCodesForClient')->willReturn([]);
        $providerRegistry = new ProviderRegistry([]);
        $providerResolver = new ProviderResolver($sysConfigRepo, $routingRepo, $this->createMock(LoggerInterface::class));
        $providerContextFactory = new ProviderContextFactory($providerResolver);

        // El resolver es REAL — es justo lo que prueba este test: que ambos
        // flujos de venta pasan por el mismo cálculo.
        $contractRepository = $this->createMock(CommunicationPricePackageRepository::class);
        $productPriceResolver = new ProductPriceResolver(
            $this->createMock(CurrencyConversionService::class),
            $this->createMock(LoggerInterface::class),
        );
        $salePriceResolver = new PackageSalePriceResolver(
            $contractRepository,
            $productPriceResolver,
            $this->createMock(LoggerInterface::class),
        );

        $this->service = new CommunicationSaleService(
            $this->em,
            $security,
            $parameters,
            $mailer,
            $logger,
            $passwordHasher,
            $environmentRepository,
            $sysConfigRepo,
            $serializer,
            $providerRegistry,
            $providerResolver,
            $providerContextFactory,
            $configureSequence,
            $messageBus,
            $historicalSaleService,
            $this->balanceService,
            $notificationCenter,
            $availabilityService,
            $salePriceResolver,
            new \App\Service\Catalog\CatalogVersionResolver($sysConfigRepo),
            $this->createMock(\App\Service\Pricing\PackageCatalogResolver::class),
            $this->createMock(\App\Provider\ProviderDispatchResolver::class),
        );
    }

    public function testRechargeAndPackageSaleChargeTheSameResolvedAmountForTheSamePackage(): void
    {
        $recharge = new CommunicationSaleRecharge();
        $recharge->setPackageId(1);
        $rechargeResult = $this->service->processRecharge($recharge);

        $sale = new CommunicationSalePackage();
        $sale->setPackageId(1);
        $sale->nationalityId = 1;
        $sale->commercialOfficeId = 1;
        $saleResult = $this->service->executeSale($sale);

        // El costo real del producto (17.5 USD) es lo que ambos flujos
        // deben cobrar — ni el snapshot crudo envenenado (999.0 EUR) del
        // paquete, ni null.
        $this->assertSame(17.5, $rechargeResult->getAmount());
        $this->assertSame('USD', $rechargeResult->getCurrency());
        $this->assertSame($rechargeResult->getAmount(), $saleResult->getAmount());
        $this->assertSame($rechargeResult->getCurrency(), $saleResult->getCurrency());
    }

    private function accountWithCurrency(string $currency): Account&MockObject
    {
        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn(1);
        $client->method('getCurrency')->willReturn($currency);

        $environment = $this->createMock(Environment::class);
        $environment->method('getId')->willReturn(10);

        $account = $this->createMock(Account::class);
        $account->method('getClient')->willReturn($client);
        $account->method('getEnvironment')->willReturn($environment);
        $account->method('getId')->willReturn(99);

        return $account;
    }

    /** @return \Doctrine\ORM\EntityRepository&MockObject */
    private function offices(CommunicationOffice $office): MockObject
    {
        $repo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repo->method('findOneBy')->willReturn($office);

        return $repo;
    }

    /** @return \Doctrine\ORM\EntityRepository&MockObject */
    private function nationalities(CommunicationNationality $nationality): MockObject
    {
        $repo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repo->method('findOneBy')->willReturn($nationality);

        return $repo;
    }
}
