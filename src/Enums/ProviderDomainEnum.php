<?php

namespace App\Enums;

/**
 * Dominio en el que opera un proveedor (ADR-001). Los de comunicaciones (recargas, paquetes) son los de
 * siempre; los de mercado (productos físicos) llegan con el hito 1. Un proveedor declara los suyos con
 * ProviderDomainAwareInterface; ProviderRegistry::registered() solo lista los de comunicaciones.
 */
enum ProviderDomainEnum: string
{
    case Communications = 'communications';
    case Market = 'market';
}
