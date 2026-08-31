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
    /**
     * Identificador(es) de destino que este producto exige (ver
     * App\Entity\CommunicationProduct::$requiredIdentifierFields) — lista
     * de opciones OR, cada una una lista de campos AND, en vocabulario
     * neutral ('phoneNumber' | 'accountIdentifier'). `[]` = sin declarar
     * (comportamiento histórico, solo phoneNumber).
     *
     * @var list<list<string>>
     */
    public array $requiredIdentifierFields = [];
}
