<?php

namespace App\Service\Provider\Manual;

use App\Enums\CommunicationProviderEnum;
use App\Exception\MyCurrentException;
use App\Provider\Contract\ManualProductBuilderInterface;
use App\Provider\Contract\ManualProductFormField;
use App\Provider\Contract\ProviderProductDto;

/**
 * Alta manual de un único producto ETECSA — mismo shape que
 * EtecsaCommunicationProvider::fetchProducts(): externalId = packageId
 * plano, destinationAmount null (monto flexible), service fijo
 * MOBILE/AIRTIME (ETECSA solo vende telefonía móvil). A diferencia de CSQ,
 * ETECSA no necesita expansión de rango — un producto ETECSA es un solo
 * monto libre, no N denominaciones fijas.
 */
final class EtecsaManualProductBuilder implements ManualProductBuilderInterface
{
    public function getCode(): CommunicationProviderEnum
    {
        return CommunicationProviderEnum::ETECSA;
    }

    public function getFormSchema(): array
    {
        return [
            new ManualProductFormField('packageId', 'Package ID', 'text', help: 'Id del paquete en ETECSA'),
            new ManualProductFormField('packageType', 'Tipo de paquete', 'text', required: false),
            new ManualProductFormField('price', 'Precio mayorista', 'number'),
            new ManualProductFormField('description', 'Descripción', 'text', required: false),
            new ManualProductFormField('enabled', 'Habilitado', 'toggle', required: false, default: true),
            new ManualProductFormField('initialDate', 'Vigente desde', 'date', required: false),
            new ManualProductFormField('endDateAt', 'Vigente hasta', 'date', required: false),
        ];
    }

    public function build(array $values): iterable
    {
        $packageId = $values['packageId'] ?? null;
        if ($packageId === null || $packageId === '') {
            throw new MyCurrentException('MANUAL_PRODUCT_MISSING_FIELD', 'El campo "packageId" es obligatorio', 422);
        }

        $price = $values['price'] ?? null;
        if ($price === null || !is_numeric($price)) {
            throw new MyCurrentException('MANUAL_PRODUCT_MISSING_FIELD', 'El campo "price" es obligatorio y numérico', 422);
        }

        $packageType = isset($values['packageType']) ? (string) $values['packageType'] : '';
        $description = isset($values['description']) ? (string) $values['description'] : null;
        $enabled = (bool) ($values['enabled'] ?? true);
        $initialDate = isset($values['initialDate']) ? new \DateTimeImmutable((string) $values['initialDate']) : null;
        $endDateAt = isset($values['endDateAt']) ? new \DateTimeImmutable((string) $values['endDateAt']) : null;

        yield new ProviderProductDto(
            externalId: (string) $packageId,
            name: $description ?? (string) $packageId,
            description: $description,
            productTypeRaw: $packageType,
            wholesalePrice: (float) $price,
            priceCurrency: null,
            destinationAmount: null,
            destinationMinAmount: null,
            destinationMaxAmount: null,
            destinationUnit: null,
            benefits: [],
            enabled: $enabled,
            validFrom: $initialDate,
            validTo: $endDateAt,
            raw: [],
            isMobileOrInternetService: true,
            service: ['name' => 'MOBILE', 'subservice' => ['name' => 'AIRTIME']],
            requiredIdentifierFields: [],
        );
    }
}
