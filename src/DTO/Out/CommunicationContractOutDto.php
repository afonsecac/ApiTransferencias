<?php

namespace App\DTO\Out;

final class CommunicationContractOutDto
{
    public int $id;

    /**
     * @var list<CommunicationPackageRefOutDto>
     */
    public array $packages = [];

    public ?TenantRefOutDto $tenant = null;
    public float $destinationAmount;
    public string $destinationCurrency;
    /**
     * Clasificación del contrato (Fase 1 del rediseño por categoría) —
     * mismo shape que CommunicationPackageOutDto::$service. Todavía solo
     * informativo: no afecta qué contrato resuelve el precio (eso es Fase
     * 3, ver PackageCatalogResolver).
     *
     * @var array{name?: string, subservice?: array{name?: string}}
     */
    public array $service = [];
    public float $price;
    public string $currency;
    public ?string $startAt = null;
    public ?string $endAt = null;
    public bool $isActive;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;
    public ?string $createdByEmail = null;
}
