<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Account;
use App\Entity\CommunicationClientPackage;
use App\Repository\CommunicationClientPackageRepository;
use App\Service\Catalog\CatalogVersionResolver;
use App\Service\Pricing\PackageMaterializationService;
use App\Service\Pricing\PackageSalePriceResolver;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * GET /communication/packages (colección) — legacy V1
 * (CommunicationClientPackage) por defecto. Para cuentas marcadas V2
 * (CatalogVersionResolver::isV2()) delega TODO en
 * CommunicationPackageCatalogProvider (catálogo V2 compartido) al inicio de
 * provide(), sin tocar nada de la materialización/resolución de precio V1
 * que sigue abajo — la rama V1 queda intacta a propósito, para que sea
 * testeable como regresión pura.
 */
class CommunicationClientPackageProvider implements ProviderInterface
{

    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private readonly ProviderInterface $itemProvider,
        #[Autowire(service: 'doctrine.orm.entity_manager')]
        private readonly EntityManagerInterface $em,
        private readonly PackageMaterializationService $materializationService,
        private readonly Security $security,
        private readonly PackageSalePriceResolver $salePriceResolver,
        private readonly CatalogVersionResolver $catalogVersion,
        // CommunicationPackageCatalogProvider es `final` — se inyecta por su
        // interfaz (mockeable en tests) con el servicio explícito, mismo
        // idioma que el resto de este constructor.
        #[Autowire(service: CommunicationPackageCatalogProvider::class)]
        private readonly ProviderInterface $catalogProvider,
        #[Autowire('@monolog.logger.provider')]
        private readonly LoggerInterface $providerLogger,
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // Switch V2 (ver docblock de clase): para cuentas marcadas V2, esta
        // misma URL sirve CommunicationPackage en vez de
        // CommunicationClientPackage — se reutiliza CommunicationPackageCatalogProvider
        // por delegación directa (su provide() ya es agnóstico de
        // $operation/$uriVariables) sin tocar nada de lo que sigue.
        $tenant = $this->security->getUser();
        if ($tenant instanceof Account && $this->catalogVersion->isV2($tenant)) {
            return $this->catalogProvider->provide($operation, $uriVariables, $context);
        }

        $clientPackages = $this->itemProvider->provide($operation, $uriVariables, $context);

        if ($clientPackages instanceof \Countable && $clientPackages->count() === 0) {
            $tenant = $this->security->getUser();
            if (!is_null($tenant) && $tenant instanceof Account) {
                $materializedCount = $this->materializeFromReferences($tenant);

                if ($materializedCount > 0) {
                    try {
                        $this->em->flush();
                    } catch (UniqueConstraintViolationException $e) {
                        // Otro request ganó la carrera materializando el
                        // mismo paquete para este mismo tenant (índice único
                        // uniq_ccp_tenant_reference) — se descarta este
                        // intento y se relee lo que sea que haya quedado.
                        $this->providerLogger->info('CommunicationClientPackageProvider: carrera de materialización, se descarta este intento.', [
                            'tenantId' => $tenant->getId(),
                        ]);
                    }
                }

                $clientPackages = $this->itemProvider->provide($operation, $uriVariables, $context);
            }
        }

        $tenant = $this->security->getUser();
        if ($tenant instanceof Account && is_iterable($clientPackages)) {
            $this->salePriceResolver->resolveMany($this->onlyClientPackages($clientPackages), $tenant);
        }

        return $clientPackages;
    }

    /**
     * Materializa desde paquete REFERENCIA (tenant IS NULL) — el camino
     * moderno, alimentado por ClientCatalogImportService y por el alta
     * manual de paquetes en el dashboard.
     */
    private function materializeFromReferences(Account $tenant): int
    {
        $environment = $tenant->getEnvironment();
        if ($environment === null) {
            return 0;
        }

        /** @var CommunicationClientPackageRepository $clientPackageRepo */
        $clientPackageRepo = $this->em->getRepository(CommunicationClientPackage::class);
        $references = $clientPackageRepo->findReferencePackages($environment, new \DateTimeImmutable());

        foreach ($references as $reference) {
            $this->materializationService->materializeForTenant($reference, $tenant);
        }

        return count($references);
    }

    /**
     * @param iterable<mixed> $items
     * @return iterable<CommunicationClientPackage>
     */
    private function onlyClientPackages(iterable $items): iterable
    {
        foreach ($items as $item) {
            if ($item instanceof CommunicationClientPackage) {
                yield $item;
            }
        }
    }
}
