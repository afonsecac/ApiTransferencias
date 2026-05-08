<?php

namespace App\DTO\Out;

use App\Entity\CommunicationProduct;
use App\OpenApi\Attribute\OAProperty;

class CommunicationProductOutDto
{
    public ?int $id = null;

    #[OAProperty(description: 'Id externo del producto en el proveedor')]
    public ?int $packageId = null;

    #[OAProperty(description: 'Tipo del paquete según taxonomía del proveedor')]
    public ?string $packageType = null;

    #[OAProperty(description: 'Tipo de producto (puede coincidir con packageType en datos sincronizados)')]
    public ?string $productType = null;

    public ?string $description = null;

    #[OAProperty(description: 'Precio base del producto')]
    public ?float $price = null;

    #[OAProperty(description: 'Si el producto está activo y disponible para venta')]
    public ?bool $enabled = null;

    #[OAProperty(description: 'Fecha de inicio de validez en formato ISO-8601')]
    public ?string $initialDate = null;

    #[OAProperty(description: 'Fecha de fin de validez en formato ISO-8601 (null = sin fin definido)')]
    public ?string $endDateAt = null;

    #[OAProperty(description: 'Referencia compacta al environment (id, type)')]
    public ?EnvironmentRefOutDto $environment = null;

    public static function fromEntity(CommunicationProduct $product): self
    {
        $dto = new self();
        $dto->id          = $product->getId();
        $dto->packageId   = $product->getPackageId();
        $dto->packageType = $product->getPackageType();
        $dto->productType = $product->getProductType();
        $dto->description = $product->getDescription();
        $dto->price       = $product->getPrice();
        $dto->enabled     = $product->isEnabled();
        $dto->initialDate = $product->getInitialDate()?->format(\DateTimeInterface::ATOM);
        $dto->endDateAt   = $product->getEndDateAt()?->format(\DateTimeInterface::ATOM);

        $env = $product->getEnvironment();
        if ($env !== null) {
            $envDto       = new EnvironmentRefOutDto();
            $envDto->id   = (int) $env->getId();
            $envDto->type = $env->getType();
            $dto->environment = $envDto;
        }

        return $dto;
    }
}
