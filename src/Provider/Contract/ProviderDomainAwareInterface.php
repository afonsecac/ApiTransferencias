<?php

namespace App\Provider\Contract;

use App\Enums\ProviderDomainEnum;

/**
 * Un proveedor que declara explícitamente en qué dominios opera. Es opcional por compatibilidad: un proveedor
 * que solo implementa CommunicationProviderInterface cuenta como de comunicaciones (ver
 * ProviderRegistry::domainsOf()). Un proveedor de mercado DEBE implementarla, y así no aparece en el ping, la
 * disponibilidad, los vínculos ni el enrutado de comunicaciones.
 */
interface ProviderDomainAwareInterface extends CommunicationProviderInterface
{
    /**
     * @return list<ProviderDomainEnum>
     */
    public function getDomains(): array;
}
