<?php

namespace App\Security;

use App\Entity\Account;
use App\Repository\SysConfigRepository;

/**
 * Decide si el mercado está abierto para una cuenta (ADR-001, interruptor de despliegue gradual): exige el
 * interruptor global `market.enabled` (apagado por defecto: sin la clave, o con cualquier valor que no sea '1')
 * Y el de la cuenta. Con el global apagado nada de mercado responde aunque haya cuentas habilitadas.
 */
final class MarketAccess
{
    public const GLOBAL_SWITCH_KEY = 'market.enabled';
    public const ROLE = 'ROLE_MARKET_API_USER';

    public function __construct(private readonly SysConfigRepository $sysConfigRepo)
    {
    }

    public function isGloballyEnabled(): bool
    {
        return $this->sysConfigRepo->findCachedValue(self::GLOBAL_SWITCH_KEY) === '1';
    }

    public function isOpenFor(Account $account): bool
    {
        return $account->isMarketEnabled() && $this->isGloballyEnabled();
    }
}
