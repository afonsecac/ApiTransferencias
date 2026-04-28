<?php

namespace App\DTO\Out;

use App\OpenApi\Attribute\OAProperty;

final class ClientPackageDetailOutDto extends ClientPackageOutDto
{
    #[OAProperty(
        schema: [
            'type' => 'array',
            'nullable' => true,
            'items' => [
                'type' => 'object',
                'properties' => [
                    'type'                   => ['type' => 'string', 'enum' => ['CREDITS', 'TALKTIME', 'DATA', 'SMS']],
                    'unit'                   => ['type' => 'string', 'enum' => ['CUP', 'USD', 'UNITS', 'MINUTES', 'GB', 'ILIM']],
                    'unit_type'              => ['type' => 'string', 'enum' => ['CURRENCY', 'QUANTITY', 'DATA', 'TIME']],
                    'additional_information' => ['type' => 'string'],
                    'amount'                 => ['type' => 'object', 'properties' => [
                        'base'                => ['type' => 'integer'],
                        'promotion_bonus'     => ['type' => 'integer'],
                        'total_excluding_tax' => ['type' => 'integer'],
                        'total_including_tax' => ['type' => 'integer'],
                    ]],
                    'schedule' => ['type' => 'object', 'nullable' => true, 'default' => null, 'properties' => [
                        'start' => ['type' => 'string'],
                        'end'   => ['type' => 'string', 'nullable' => true, 'default' => null],
                    ]],
                ],
            ],
        ],
        description: 'Lista de beneficios del paquete (créditos, datos, minutos, SMS, etc.)',
    )]
    public ?array $benefits = null;

    #[OAProperty(
        schema: [
            'type' => 'array',
            'nullable' => true,
            'items' => ['type' => 'string', 'enum' => ['AIRTIME', 'BUNDLE', 'DATA', 'SMS', 'INTERNET']],
        ],
        description: 'Categorías del paquete',
    )]
    public ?array $tags = null;

    #[OAProperty(
        schema: [
            'type' => 'object',
            'nullable' => true,
            'properties' => [
                'name'       => ['type' => 'string', 'enum' => ['Mobile', 'uSIM', 'Devices']],
                'subservice' => ['type' => 'object', 'properties' => [
                    'name' => ['type' => 'string', 'enum' => ['AIRTIME', 'BUNDLE', 'DATA', 'SMS', 'INTERNET', 'uSIM']],
                ]],
            ],
        ],
        description: 'Servicio y subservicio asociados al paquete',
    )]
    public ?array $service = null;

    #[OAProperty(
        schema: [
            'type' => 'object',
            'nullable' => true,
            'properties' => [
                'amount'    => ['type' => 'number', 'format' => 'currency-number'],
                'unit'      => ['type' => 'string', 'enum' => ['CUP', 'MLC', 'USD']],
                'unit_type' => ['type' => 'string', 'enum' => ['CURRENCY']],
            ],
        ],
        description: 'Precio de destino del paquete con unidad de moneda',
    )]
    public ?array $destination = null;

    #[OAProperty(
        schema: [
            'type' => 'object',
            'nullable' => true,
            'default' => null,
            'properties' => [
                'quantity' => ['type' => 'integer'],
                'unit'     => ['type' => 'string', 'enum' => ['DAYS', 'MONTH', 'YEAR']],
            ],
        ],
        description: 'Vigencia del paquete (cantidad y unidad de tiempo)',
    )]
    public ?array $validity = null;

    #[OAProperty(description: 'URL o texto con información adicional sobre el paquete')]
    public ?string $knowMore = null;

    public ?PricePackageOutDto $pricePackage = null;
}
