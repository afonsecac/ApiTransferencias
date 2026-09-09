<?php

namespace App\Service\Provider\Manual;

use App\DTO\CreateManualProductDto;
use App\Entity\CommunicationProduct;
use App\Entity\Environment;
use App\Enums\CommunicationProviderEnum;
use App\Exception\MyCurrentException;
use App\Service\Provider\CommunicationCatalogSyncService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Alta manual de productos de catálogo a partir de un identificador del
 * proveedor, sin llamar a su API — para catálogo/promociones que el
 * proveedor no publica en su endpoint de sincronización (ver
 * ManualProductBuilderInterface). Servicio nuevo con pocas dependencias:
 * inyección directa, sin extender CommonService (CLAUDE.md regla 5).
 */
class ManualProductService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ManualProductBuilderRegistry $builderRegistry,
        private readonly CommunicationCatalogSyncService $catalogSyncService,
    ) {
    }

    public function create(CreateManualProductDto $dto): ManualProductResult
    {
        $providerCode = CommunicationProviderEnum::tryFrom((string) $dto->getProvider());
        if ($providerCode === null) {
            throw new MyCurrentException('PROVIDER_NOT_REGISTERED', "El proveedor \"{$dto->getProvider()}\" no existe", 404);
        }

        $environment = $this->assertEnvironment((int) $dto->getEnvironmentId());
        $builder = $this->builderRegistry->get($providerCode);

        $items = iterator_to_array($builder->build($dto->getValues() ?? []), false);
        $externalRefs = array_map(static fn ($item) => $item->externalId, $items);

        $syncResult = $this->catalogSyncService->upsertProducts($items, $providerCode, $environment);

        $products = $externalRefs === [] ? [] : $this->em->getRepository(CommunicationProduct::class)->findBy([
            'environment' => $environment,
            'provider' => $providerCode->value,
            'externalRef' => $externalRefs,
        ]);

        return new ManualProductResult($syncResult->created, $syncResult->updated, $syncResult->skipped, $products);
    }

    private function assertEnvironment(int $environmentId): Environment
    {
        $environment = $this->em->getRepository(Environment::class)->find($environmentId);
        if ($environment === null) {
            throw new MyCurrentException('ENVIRONMENT_NOT_FOUND', 'Environment not found', 404);
        }

        return $environment;
    }
}
