<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Account;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationPricePackage;
use App\Repository\CommunicationClientPackageRepository;
use App\Repository\CommunicationPricePackageRepository;
use App\Service\PackagePriceService;
use App\Service\Pricing\PackageMaterializationService;
use App\Service\Pricing\PackageSalePriceResolver;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class CommunicationClientPackageProvider implements ProviderInterface
{

    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private readonly ProviderInterface $itemProvider,
        #[Autowire(service: 'doctrine.orm.entity_manager')]
        private readonly EntityManagerInterface $em,
        private readonly PackagePriceService  $packagePriceService,
        private readonly PackageMaterializationService $materializationService,
        private readonly Security $security,
        private readonly PackageSalePriceResolver $salePriceResolver,
        #[Autowire('@monolog.logger.provider')]
        private readonly LoggerInterface $providerLogger,
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $clientPackages = $this->itemProvider->provide($operation, $uriVariables, $context);

        if ($clientPackages instanceof \Countable && $clientPackages->count() === 0) {
            $tenant = $this->security->getUser();
            if (!is_null($tenant) && $tenant instanceof Account) {
                $materializedCount = $this->materializeFromReferences($tenant);
                // Sin ninguna referencia todavía (entorno recién migrado, o
                // proveedor que aún no pasó por ClientCatalogImportService
                // tras el rediseño): red de seguridad con el mecanismo
                // anterior, plantillas en CommunicationPricePackage.tenant
                // IS NULL. Se retira en la Fase 5 de limpieza.
                if ($materializedCount === 0) {
                    $materializedCount = $this->materializeFromLegacyTemplates($tenant);
                }

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
     * Rama legacy previa al rediseño de precios: plantillas propias del
     * tenant si ya las tenía, o si no, copia de las plantillas globales
     * (CommunicationPricePackage.tenant IS NULL). Se mantiene solo como red
     * de seguridad — ver comentario en provide().
     */
    private function materializeFromLegacyTemplates(Account $tenant): int
    {
        /** @var CommunicationPricePackageRepository $pricePackageRepo */
        $pricePackageRepo = $this->em->getRepository(CommunicationPricePackage::class);
        $packageItems = $pricePackageRepo->getPricesByEnvironment($tenant->getEnvironment()?->getType(), $tenant->getId());
        if (count($packageItems) > 0) {
            foreach ($packageItems as $packageItem) {
                $this->packagePriceService->createPackageClient($packageItem, $tenant);
            }

            return count($packageItems);
        }

        $packageItems = $pricePackageRepo->getPricesByEnvironment($tenant->getEnvironment()?->getType());
        foreach ($packageItems as $packageItem) {
            $this->packagePriceService->copyPricePackage($packageItem, $tenant);
        }

        return count($packageItems);
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
