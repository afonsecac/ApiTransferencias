<?php

namespace App\DTO\Out;

/**
 * Una fila de cobertura de CommunicationPackageAdminService::coverage() — un
 * CommunicationProduct concreto que cubre la tupla de destino del paquete.
 */
final class PackageCoverageItemOutDto
{
    public string $provider;
    public int $productId;
    public string $externalRef;
    public ?string $description = null;
    public float $wholesalePrice;
    public ?string $priceCurrency = null;
}
