<?php

namespace App\Service\Provider;

use App\Entity\CommunicationProduct;
use App\Entity\Environment;
use App\Enums\CommunicationProviderEnum;
use App\Provider\Contract\ProviderCatalogInterface;
use App\Provider\Contract\ProviderContext;
use App\Provider\ProviderRegistry;
use App\Service\Etecsa\SyncResult;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Sincroniza el catálogo de productos de CUALQUIER proveedor registrado,
 * agnóstico de cuál sea — generaliza lo que antes era
 * EtecsaCatalogSyncService::syncProducts(), específico de ETECSA.
 *
 * La clave de upsert es (environment, provider, externalRef), no
 * (environment, packageId): con dos proveedores, el mismo packageId numérico
 * puede repetirse sin ambigüedad porque cada proveedor tiene su propio
 * espacio de IDs (ver migración Version20260801140000).
 */
class CommunicationCatalogSyncService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProviderRegistry $providerRegistry,
    ) {
    }

    public function syncProducts(CommunicationProviderEnum $providerCode, Environment $environment): SyncResult
    {
        $adapter = $this->providerRegistry->getFor($providerCode, ProviderCatalogInterface::class);
        $context = new ProviderContext(
            provider: $providerCode,
            environmentType: $environment->getType(),
            environmentId: $environment->getId(),
        );

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $repo = $this->em->getRepository(CommunicationProduct::class);

        foreach ($adapter->fetchProducts($context) as $item) {
            if ($item->externalId === '') {
                ++$skipped;
                continue;
            }

            $entity = $repo->findOneBy([
                'environment' => $environment,
                'provider' => $providerCode->value,
                'externalRef' => $item->externalId,
            ]);

            if ($entity === null) {
                $entity = new CommunicationProduct();
                $entity->setEnvironment($environment);
                $entity->setProvider($providerCode->value);
                $entity->setExternalRef($item->externalId);
                $this->em->persist($entity);
                ++$created;
            } else {
                ++$updated;
            }

            // packageId (int) es una columna heredada mantenida por
            // compatibilidad con el código que aún la lee directamente
            // (CommunicationSaleService). externalRef es la clave canónica;
            // 0 marca un ID externo no numérico (proveedores futuros).
            $entity->setPackageId(is_numeric($item->externalId) ? (int) $item->externalId : 0);
            $entity->setPackageType((string) ($item->productTypeRaw ?? ''));
            $entity->setProductType((string) ($item->productTypeRaw ?? ''));
            $entity->setIsMobileOrInternetService($item->isMobileOrInternetService);
            $entity->setBenefits($item->benefits);
            $entity->setService($item->service);
            $entity->setRequiredIdentifierFields($item->requiredIdentifierFields);
            $entity->setPrice($item->wholesalePrice);
            $entity->setPriceCurrency($item->priceCurrency);
            $entity->setDestinationAmount($item->destinationAmount);
            $entity->setDestinationUnit($item->destinationUnit);
            $entity->setEnabled($item->enabled);
            $entity->setDescription($item->description ?? $item->name);

            if ($item->validFrom !== null) {
                $entity->setInitialDate($item->validFrom);
            }
            $entity->setEndDateAt($item->validTo);
        }

        $this->em->flush();

        return new SyncResult($created, $updated, $skipped);
    }
}
