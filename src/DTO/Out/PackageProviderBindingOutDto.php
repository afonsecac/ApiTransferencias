<?php

namespace App\DTO\Out;

/**
 * Una fila de CommunicationPackageBindingService::listBindings() — un
 * proveedor registrado en el sistema (ProviderRegistry::registered()), el
 * producto vinculado explícitamente a este paquete si existe, y los
 * productos candidatos que matchean por tupla (mismo criterio que
 * coverage()) para elegir entre ellos.
 */
final class PackageProviderBindingOutDto
{
    public string $provider;
    public ?PackageCoverageItemOutDto $boundProduct = null;
    /** @var PackageCoverageItemOutDto[] */
    public array $candidates = [];
    /**
     * true = candidates matcheó por tupla (destinationAmount/currency).
     * false = sin match automático, candidates es el catálogo COMPLETO
     * habilitado del proveedor (ej. ETECSA, cuyos productos no traen
     * destinationAmount/destinationUnit poblados).
     */
    public bool $autoMatched = true;
}
