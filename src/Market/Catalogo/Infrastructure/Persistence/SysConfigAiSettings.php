<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Persistence;

use App\Market\Catalogo\Domain\AiSettings;
use App\Repository\SysConfigRepository;

/**
 * El interruptor sale de la configuración del sistema (`market.ai.enabled`): apagado por defecto, y solo el valor «1» lo
 * enciende, igual que el interruptor de mercado (`market.enabled`).
 */
final class SysConfigAiSettings implements AiSettings
{
    public const KEY = 'market.ai.enabled';

    public function __construct(private readonly SysConfigRepository $config)
    {
    }

    public function isEnabled(): bool
    {
        return $this->config->findCachedValue(self::KEY, true) === '1';
    }
}
