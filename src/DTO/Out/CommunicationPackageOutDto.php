<?php

namespace App\DTO\Out;

final class CommunicationPackageOutDto
{
    public int $id;
    public ?string $name = null;
    public ?string $description = null;
    public ?string $knowMore = null;
    public array $benefits = [];
    public array $tags = [];
    public array $service = [];
    public ?array $validity = null;
    public array $destination = [];
    public bool $isActive;
    public ?string $activeStartAt = null;
    public ?string $activeEndAt = null;
    public int $displayOrder;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;
    /** true si el paquete tiene al menos un vínculo explícito paquete→producto por proveedor. */
    public bool $hasBindings = false;
    /** No nulo = paquete generado por rango para esta promoción (ver CommunicationPackage::$promotion). */
    public ?int $promotionId = null;
}
