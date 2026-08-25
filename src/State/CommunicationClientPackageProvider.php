<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * GET /communication/packages (colección) — Fase 4 de la deprecación de V1:
 * delega SIEMPRE en CommunicationPackageCatalogProvider (catálogo V2), sin
 * ninguna bifurcación por CatalogVersionResolver::isV2() — confirmado en la
 * investigación de la Fase 2 (contra dev, staging Y producción) que todas
 * las cuentas ya resuelven V2 (sin CSV override, default global 'v2'). Esta
 * URL ya no consulta la tabla de CommunicationClientPackage; la clase sigue
 * existiendo (contratos de precio V1, histórico de ventas) hasta que se
 * borre en la Fase 5.
 */
class CommunicationClientPackageProvider implements ProviderInterface
{
    public function __construct(
        // CommunicationPackageCatalogProvider es `final` — se inyecta por su
        // interfaz (mockeable en tests) con el servicio explícito, mismo
        // idioma que el resto del namespace App\State.
        #[Autowire(service: CommunicationPackageCatalogProvider::class)]
        private readonly ProviderInterface $catalogProvider,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        return $this->catalogProvider->provide($operation, $uriVariables, $context);
    }
}
