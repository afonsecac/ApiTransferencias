<?php

namespace App\Tests\Service;

use App\Entity\CommunicationProduct;
use App\Provider\ProviderContextFactory;
use App\Provider\ProviderRegistry;
use App\Provider\ProviderResolver;
use App\Repository\SysConfigRepository;
use App\Service\CommunicationSaleService;
use App\Service\ConfigureSequenceService;
use App\Service\HistoricalSaleService;
use App\Service\NotificationCenterService;
use App\Service\Pricing\PackageSalePriceResolver;
use App\Service\Provider\ProviderAvailabilityService;
use App\Service\BalanceService;
use App\Repository\ClientProviderRoutingRepository;
use App\Repository\EnvironmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @covers \App\Service\CommunicationSaleService::resolveProductExternalId
 *
 * Bug real (2026-08-10): el despacho a proveedor usaba
 * CommunicationProduct::getPackageId() (columna legacy, entero) como
 * productExternalId. CommunicationCatalogSyncService la colapsa a 0 para
 * cualquier externalId no numérico — CSQ usa "{articleId}-{amount}"
 * (CsqCommunicationProvider::fetchProducts()) — así que TODO producto CSQ
 * mandaba "0" al proveedor. resolveProductExternalId() usa externalRef (la
 * clave canónica) con fallback a packageId, porque los productos creados a
 * mano vía POST /products (CommunicationProductService::createProduct())
 * nunca setean externalRef.
 *
 * Se prueba por reflexión (método privado) porque ejercitar esta ruta de
 * punta a punta requiere invokeRechargeCommunication()/executeNewSaleInfo(),
 * que no tienen ningún test hoy y arman un EntityManager/Connection mock
 * mucho más grande que lo que amerita cubrir esta única regla.
 */
class CommunicationSaleServiceResolveProductExternalIdTest extends TestCase
{
    private CommunicationSaleService $service;

    protected function setUp(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $security = $this->createMock(Security::class);
        $parameters = $this->createMock(ParameterBagInterface::class);
        $mailer = $this->createMock(MailerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $environmentRepository = $this->createMock(EnvironmentRepository::class);
        $sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $serializer = $this->createMock(SerializerInterface::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $historicalSaleService = $this->createMock(HistoricalSaleService::class);
        $balanceService = $this->createMock(BalanceService::class);
        $notificationCenter = $this->createMock(NotificationCenterService::class);
        $availabilityService = $this->createMock(ProviderAvailabilityService::class);
        $salePriceResolver = $this->createMock(PackageSalePriceResolver::class);
        $configureSequence = $this->createMock(ConfigureSequenceService::class);

        $routingRepo = $this->createMock(ClientProviderRoutingRepository::class);
        $providerRegistry = new ProviderRegistry([]);
        $providerResolver = new ProviderResolver($sysConfigRepo, $routingRepo, $this->createMock(LoggerInterface::class));
        $providerContextFactory = new ProviderContextFactory($providerResolver);

        $this->service = new CommunicationSaleService(
            $em,
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
            $balanceService,
            $notificationCenter,
            $availabilityService,
            $salePriceResolver,
            new \App\Service\Catalog\CatalogVersionResolver($sysConfigRepo),
            $this->createMock(\App\Service\Pricing\PackageCatalogResolver::class),
            $this->createMock(\App\Provider\ProviderDispatchResolver::class),
            $this->createMock(\App\Provider\PromotionProviderDispatchResolver::class),
        );
    }

    private function invoke(?CommunicationProduct $product): ?string
    {
        $method = new \ReflectionMethod(CommunicationSaleService::class, 'resolveProductExternalId');
        $method->setAccessible(true);

        return $method->invoke($this->service, $product);
    }

    public function testPrefersExternalRefOverPackageId(): void
    {
        // Producto CSQ típico: packageId colapsado a 0, externalRef con el
        // id compuesto real (articleId-amount).
        $product = $this->createMock(CommunicationProduct::class);
        $product->method('getExternalRef')->willReturn('7951-2200');
        $product->method('getPackageId')->willReturn(0);

        $this->assertSame('7951-2200', $this->invoke($product));
    }

    public function testFallsBackToPackageIdWhenExternalRefIsEmpty(): void
    {
        // Producto creado a mano vía POST /products: nunca setea externalRef.
        $product = $this->createMock(CommunicationProduct::class);
        $product->method('getExternalRef')->willReturn('');
        $product->method('getPackageId')->willReturn(42);

        $this->assertSame('42', $this->invoke($product));
    }

    public function testReturnsNullWhenProductIsNull(): void
    {
        $this->assertNull($this->invoke(null));
    }
}
