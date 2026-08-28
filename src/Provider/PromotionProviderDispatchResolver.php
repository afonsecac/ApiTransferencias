<?php

namespace App\Provider;

use App\Entity\Account;
use App\Entity\CommunicationProduct;
use App\Entity\CommunicationPromotions;
use App\Entity\Environment;
use App\Enums\CommunicationProviderEnum;
use App\Exception\MyCurrentException;
use App\Repository\ClientProviderRoutingRepository;
use App\Repository\CommunicationPromotionProviderProductRepository;
use App\Repository\SysConfigRepository;
use App\Service\Provider\ProviderAvailabilityService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Elige QUÉ proveedor despacha de verdad la redención de una
 * CommunicationPromotions — equivalente de ProviderDispatchResolver, pero
 * para promociones en vez de CommunicationPackage (V2). Se recorre la lista
 * de PRIORIDAD del cliente (ClientProviderRouting::$priority) y se busca,
 * para cada proveedor en orden, un producto vinculado a la promoción — el
 * primero que sirve, gana.
 *
 * A diferencia de ProviderDispatchResolver::findDispatchableProduct(), aquí
 * NO hay matching automático por tupla destino: una promoción no tiene un
 * destinationAmount/destinationCurrency único a nivel de promoción (genera
 * tramos de precio por cliente vía CommunicationClientPackage), así que un
 * match automático sobre el catálogo completo podría devolver un producto
 * no pensado para esa promoción. Toda asociación proveedor→producto debe
 * ser explícita (CommunicationPromotionProviderProduct) — el único
 * fallback implícito es el producto "de origen" de la promoción
 * (CommunicationPromotions::$product), y SOLO cuando su proveedor coincide
 * con el candidato evaluado, para que ninguna promoción existente (sin
 * vínculos nuevos) cambie de comportamiento.
 *
 * Mismo kill switch y fallback a proveedor único que ProviderResolver/
 * ProviderDispatchResolver usan hoy (comunications.provider.routing.enabled
 * + communications.provider.default).
 */
class PromotionProviderDispatchResolver
{
    public function __construct(
        private readonly ClientProviderRoutingRepository $routingRepo,
        private readonly CommunicationPromotionProviderProductRepository $promotionBindingRepo,
        private readonly ProviderAvailabilityService $availabilityService,
        private readonly SysConfigRepository $sysConfigRepo,
    ) {
    }

    public function select(Account $account, CommunicationPromotions $promotion): SelectedDispatch
    {
        $environmentType = $account->getEnvironment()?->getType();

        foreach ($this->candidateProviders($account) as $provider) {
            if (!$this->availabilityService->canDispatchTo($provider->value, $environmentType)) {
                continue;
            }

            $product = $this->findDispatchableProduct($promotion, $provider->value, $account->getEnvironment());
            if ($product !== null) {
                return new SelectedDispatch($provider, $product, $this->resolveExternalRef($product));
            }
        }

        throw new MyCurrentException(
            'PROMOTION_NOT_DISPATCHABLE',
            'Ningún proveedor disponible puede despachar esta promoción',
            Response::HTTP_CONFLICT,
        );
    }

    /**
     * @return list<CommunicationProviderEnum>
     */
    private function candidateProviders(Account $account): array
    {
        $client = $account->getClient();
        if ($client === null || !$this->routingEnabled()) {
            return [$this->defaultProvider()];
        }

        // findActiveRouteScopesForClient() devuelve una proyección escalar
        // (ver su docblock) — este resolver, a diferencia de
        // ProviderDispatchResolver, NO filtra por scope ni expande
        // fallbackProvider: las promociones no tienen categoría
        // servicio/subservicio propia, fuera de alcance de esta extensión.
        $rows = $this->routingRepo->findActiveRouteScopesForClient($client->getId());
        if ($rows === []) {
            return [$this->defaultProvider()];
        }

        $providers = [];
        foreach ($rows as $row) {
            $provider = CommunicationProviderEnum::tryFrom($row['provider'] ?? '');
            if ($provider !== null) {
                $providers[] = $provider;
            }
        }

        return $providers === [] ? [$this->defaultProvider()] : $providers;
    }

    private function findDispatchableProduct(CommunicationPromotions $promotion, string $provider, ?Environment $environment): ?CommunicationProduct
    {
        $bound = $this->promotionBindingRepo->findForPromotionAndProvider($promotion, $provider)?->getProduct();
        if ($bound !== null && $bound->isEnabled() && ($bound->getEnvironment() === null || $bound->getEnvironment() === $environment)) {
            return $bound;
        }

        $default = $promotion->getProduct();
        if ($default !== null && $default->getProvider() === $provider && $default->isEnabled()) {
            return $default;
        }

        return null;
    }

    /**
     * Mismo fallback que CommunicationSaleService::resolveProductExternalId():
     * productos creados a mano (POST /products) nunca setean externalRef, solo
     * los sincronizados vía CommunicationCatalogSyncService lo hacen.
     */
    private function resolveExternalRef(CommunicationProduct $product): string
    {
        return $product->getExternalRef() !== '' ? $product->getExternalRef() : (string) $product->getPackageId();
    }

    private function routingEnabled(): bool
    {
        return $this->sysConfigRepo->findCachedValue(ProviderResolver::ROUTING_ENABLED_KEY) !== '0';
    }

    private function defaultProvider(): CommunicationProviderEnum
    {
        $raw = $this->sysConfigRepo->findCachedValue(ProviderResolver::DEFAULT_PROVIDER_KEY);
        $provider = $raw !== null ? CommunicationProviderEnum::tryFrom($raw) : null;

        return $provider ?? CommunicationProviderEnum::ETECSA;
    }
}
