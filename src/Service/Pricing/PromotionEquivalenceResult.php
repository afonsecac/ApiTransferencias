<?php

namespace App\Service\Pricing;

/**
 * Resultado de CommunicationPromotionEquivalenceService::populateEquivalences()/
 * coverage() — nunca se omiten en silencio los huecos: `gaps` lista, para
 * cada tramo, qué proveedores con capacidad de auto-poblado (
 * ProviderPromotionCatalogInterface) todavía no tienen equivalencia — el
 * admin los cura a mano desde la pantalla de bindings existente
 * (CommunicationPackageBindingService).
 */
final readonly class PromotionEquivalenceResult
{
    /**
     * @param list<array{provider: string, matched: int, error: ?string}> $providers
     * @param list<array{packageId: int, destinationAmount: float, missingProviders: list<string>}> $gaps
     */
    public function __construct(
        public array $providers,
        public array $gaps,
    ) {
    }
}
