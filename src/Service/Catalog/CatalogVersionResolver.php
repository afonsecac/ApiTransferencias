<?php

namespace App\Service\Catalog;

use App\Entity\Account;
use App\Repository\SysConfigRepository;

/**
 * Decide si una cuenta compra contra el catálogo V2 (CommunicationPackage/
 * CommunicationContract) o el legacy (CommunicationClientPackage/
 * CommunicationPricePackage) — mismo idioma que el kill switch de routing
 * (App\Provider\ProviderResolver): sys_config, sin desplegar para
 * activar/desactivar por cliente.
 *
 * Sin ninguna de las dos claves seteadas, isV2() siempre es false — "flag
 * OFF = comportamiento idéntico" (ningún cliente existente cambia de
 * comportamiento el día del deploy de la Fase 4).
 *
 * Precedencia: si el cliente de la cuenta está en la lista explícita de
 * `communications.catalog.v2.clients`, es V2 SIEMPRE (aunque el default
 * global sea legacy) — así se puede pilotar con un cliente concreto sin
 * mover el default. Si no está en la lista, se usa el default global.
 */
final class CatalogVersionResolver
{
    public const DEFAULT_VERSION_KEY = 'communications.catalog.version.default';
    public const V2_CLIENTS_KEY = 'communications.catalog.v2.clients';

    public function __construct(
        private readonly SysConfigRepository $sysConfigRepo,
    ) {
    }

    public function isV2(Account $account): bool
    {
        $clientId = $account->getClient()?->getId();
        if ($clientId !== null && in_array((string) $clientId, $this->v2ClientIds(), true)) {
            return true;
        }

        return $this->sysConfigRepo->findCachedValue(self::DEFAULT_VERSION_KEY) === 'v2';
    }

    /**
     * @return list<string>
     */
    private function v2ClientIds(): array
    {
        $csv = $this->sysConfigRepo->findCachedValue(self::V2_CLIENTS_KEY);
        if ($csv === null || trim($csv) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $csv)), static fn (string $id) => $id !== ''));
    }
}
