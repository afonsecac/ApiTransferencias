<?php

namespace App\Service\Provider\Manual;

use App\Enums\CommunicationProviderEnum;
use App\Exception\MyCurrentException;
use App\Provider\Contract\ManualProductBuilderInterface;
use App\Provider\Contract\ManualProductFormField;
use App\Provider\Contract\ProviderProductDto;

/**
 * Alta manual de productos CSQ por rango de montos — para promociones que
 * CSQ anuncia (por correo, con un `articleId`/SKUID y un rango de montos)
 * pero nunca expone en `GET /product/portfolio`, así que
 * `CommunicationCatalogSyncService::syncProducts()` nunca las sincroniza.
 *
 * Replica exactamente la expansión `by_range` de
 * CsqCommunicationProvider::fetchProducts() (externalId
 * "{articleId}-{amount}", wholesalePrice = round(amount/exchangeRate, 2)),
 * para que las filas generadas sean indistinguibles de las que produciría
 * el sync real — la diferencia es que aquí `fromAmount`/`toAmount` ya vienen
 * en moneda de destino (el admin transcribe el correo tal cual), sin la
 * conversión `(valor/100)*exchangeRate` que sí aplica al payload crudo de
 * CSQ (ver amountsFromRange() de CsqCommunicationProvider).
 */
final class CsqManualProductBuilder implements ManualProductBuilderInterface
{
    /**
     * Tope duro de productos generables en una sola alta — mismo criterio y
     * mismo valor que CommunicationPackageAdminService::MAX_BATCH_SIZE, para
     * evitar que un step mal puesto intente insertar miles de filas.
     */
    private const MAX_BATCH_SIZE = 200;

    /**
     * Mismo mapa que CsqCommunicationProvider::TOPUP_TYPE_SERVICE_MAP — se
     * replica aquí (en vez de compartirlo) porque el alta manual, a
     * diferencia del sync, debe RECHAZAR un topupType desconocido en vez de
     * degradarlo silenciosamente a "no es servicio móvil/Internet": un admin
     * tecleando a mano se beneficia de un error claro, no de un producto mal
     * clasificado que aparezca en catálogo por error.
     *
     * @var array<string, array{service: string, subservice: string}>
     */
    private const TOPUP_TYPE_SERVICE_MAP = [
        'RTR' => ['service' => 'Mobile', 'subservice' => 'Airtime'],
        'Bundles' => ['service' => 'Mobile', 'subservice' => 'Bundle'],
        'Data' => ['service' => 'Utilities', 'subservice' => 'Internet'],
    ];

    private const REQUIRED_IDENTIFIER_FIELDS = ['phoneNumber', 'accountIdentifier'];

    public function getCode(): CommunicationProviderEnum
    {
        return CommunicationProviderEnum::CSQ;
    }

    public function getFormSchema(): array
    {
        return [
            new ManualProductFormField('articleId', 'Article ID (SKUID)', 'text', help: 'Identificador del producto en CSQ, ej. 8142'),
            new ManualProductFormField('name', 'Nombre base', 'text'),
            new ManualProductFormField('fromAmount', 'Monto desde', 'number', help: 'En la moneda de destino (ej. CUP), tal cual el correo de CSQ'),
            new ManualProductFormField('toAmount', 'Monto hasta', 'number'),
            new ManualProductFormField('step', 'Paso', 'number', default: 25.0),
            new ManualProductFormField('exchangeRate', 'Tipo de cambio CSQ', 'number', help: 'wholesalePrice = monto / exchangeRate'),
            new ManualProductFormField('destinationUnit', 'Unidad de destino', 'text', default: 'CUP'),
            new ManualProductFormField('priceCurrency', 'Moneda del precio', 'text', default: 'USD'),
            new ManualProductFormField('topupType', 'Tipo de producto', 'select', options: array_keys(self::TOPUP_TYPE_SERVICE_MAP)),
            new ManualProductFormField('requiredIdentifierField', 'Identificador requerido', 'select', options: self::REQUIRED_IDENTIFIER_FIELDS, default: 'phoneNumber'),
            new ManualProductFormField('description', 'Descripción', 'text', required: false),
            new ManualProductFormField('enabled', 'Habilitado', 'toggle', required: false, default: true),
            new ManualProductFormField('initialDate', 'Vigente desde', 'date', required: false),
            new ManualProductFormField('endDateAt', 'Vigente hasta', 'date', required: false),
        ];
    }

    public function build(array $values): iterable
    {
        $articleId = $this->requireString($values, 'articleId');
        $name = $this->requireString($values, 'name');
        $from = $this->requireFloat($values, 'fromAmount');
        $to = $this->requireFloat($values, 'toAmount');
        $step = $this->requireFloat($values, 'step');
        $exchangeRate = $this->requireFloat($values, 'exchangeRate');
        $destinationUnit = (string) ($values['destinationUnit'] ?? 'CUP');
        $priceCurrency = (string) ($values['priceCurrency'] ?? 'USD');
        $topupType = $this->requireString($values, 'topupType');
        $requiredIdentifierField = (string) ($values['requiredIdentifierField'] ?? '');
        $description = isset($values['description']) ? (string) $values['description'] : null;
        $enabled = (bool) ($values['enabled'] ?? true);
        $initialDate = isset($values['initialDate']) ? new \DateTimeImmutable((string) $values['initialDate']) : null;
        $endDateAt = isset($values['endDateAt']) ? new \DateTimeImmutable((string) $values['endDateAt']) : null;

        if ($step <= 0.0) {
            throw new MyCurrentException('MANUAL_PRODUCT_INVALID_RANGE', 'El paso (step) debe ser positivo', 422);
        }
        if ($to < $from) {
            throw new MyCurrentException('MANUAL_PRODUCT_INVALID_RANGE', 'El rango es inválido: toAmount debe ser mayor o igual a fromAmount', 422);
        }
        if ($exchangeRate <= 0.0) {
            throw new MyCurrentException('MANUAL_PRODUCT_INVALID_RANGE', 'El tipo de cambio debe ser positivo', 422);
        }

        $serviceMap = self::TOPUP_TYPE_SERVICE_MAP[$topupType] ?? null;
        if ($serviceMap === null) {
            throw new MyCurrentException(
                'MANUAL_PRODUCT_INVALID_TOPUP_TYPE',
                sprintf('topupType "%s" desconocido; valores válidos: %s', $topupType, implode(', ', array_keys(self::TOPUP_TYPE_SERVICE_MAP))),
                422,
            );
        }

        if ($requiredIdentifierField !== '' && !in_array($requiredIdentifierField, self::REQUIRED_IDENTIFIER_FIELDS, true)) {
            throw new MyCurrentException(
                'MANUAL_PRODUCT_INVALID_IDENTIFIER_FIELD',
                sprintf('requiredIdentifierField "%s" desconocido; valores válidos: %s', $requiredIdentifierField, implode(', ', self::REQUIRED_IDENTIFIER_FIELDS)),
                422,
            );
        }

        // Mismo cálculo (por índice, no acumulando +=) que
        // CommunicationPackageAdminService::createBatch() usa para su propia
        // expansión de rango — evita arrastrar error de coma flotante en
        // rangos largos.
        $count = (int) floor(($to - $from) / $step + 1e-9) + 1;
        if ($count > self::MAX_BATCH_SIZE) {
            throw new MyCurrentException(
                'MANUAL_PRODUCT_RANGE_TOO_LARGE',
                sprintf('El rango genera %d productos; el máximo permitido es %d', $count, self::MAX_BATCH_SIZE),
                422,
            );
        }

        $service = ['name' => $serviceMap['service'], 'subservice' => ['name' => $serviceMap['subservice']]];
        $requiredIdentifierFields = $requiredIdentifierField !== '' ? [[$requiredIdentifierField]] : [];

        for ($i = 0; $i < $count; ++$i) {
            $amount = round($from + $i * $step, 2);
            $amountLabel = (string) $amount;

            yield new ProviderProductDto(
                externalId: $articleId . '-' . $amountLabel,
                name: "{$name} - {$amountLabel} {$destinationUnit}",
                description: $description,
                productTypeRaw: $topupType,
                wholesalePrice: round($amount / $exchangeRate, 2),
                priceCurrency: $priceCurrency,
                destinationAmount: $amount,
                destinationMinAmount: null,
                destinationMaxAmount: null,
                destinationUnit: $destinationUnit,
                benefits: [],
                enabled: $enabled,
                validFrom: $initialDate,
                validTo: $endDateAt,
                raw: [],
                isMobileOrInternetService: true,
                service: $service,
                requiredIdentifierFields: $requiredIdentifierFields,
            );
        }
    }

    private function requireString(array $values, string $key): string
    {
        $value = $values[$key] ?? null;
        if ($value === null || $value === '') {
            throw new MyCurrentException('MANUAL_PRODUCT_MISSING_FIELD', "El campo \"{$key}\" es obligatorio", 422);
        }

        return (string) $value;
    }

    private function requireFloat(array $values, string $key): float
    {
        $value = $values[$key] ?? null;
        if ($value === null || !is_numeric($value)) {
            throw new MyCurrentException('MANUAL_PRODUCT_MISSING_FIELD', "El campo \"{$key}\" es obligatorio y numérico", 422);
        }

        return (float) $value;
    }
}
