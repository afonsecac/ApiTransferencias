<?php

namespace App\DTO;

use App\OpenApi\Attribute\OAProperty;
use Symfony\Component\Validator\Constraints as Assert;

class CreateClientPackageDto implements IInput
{
    /**
     * Ausente = crea/actualiza solo el paquete REFERENCIA (tenant null).
     * Presente = alta de un solo cliente directo (comportamiento histórico,
     * exige priceClientPackageId — ver CommunicationPackageService::create()).
     * Para dar de alta varios clientes a la vez usa $clients en su lugar.
     */
    #[Assert\Positive]
    protected ?int $tenantId;

    protected ?int $priceClientPackageId;

    /**
     * Requerido cuando no se da priceClientPackageId: producto/proveedor
     * del que sale el precio del paquete referencia cuando no tiene
     * contrato (ver CommunicationClientPackage::resolveProduct()).
     */
    #[Assert\Positive]
    protected ?int $productId;

    /**
     * @var int[]|null ids de cliente a los que materializar el paquete
     *  referencia recién creado/actualizado — vacío/ausente = solo la
     *  referencia (mismo criterio que promociones/contratos).
     */
    protected ?array $clients;

    protected ?int $environmentId;

    #[Assert\Length(max: 255)]
    protected ?string $name;

    #[Assert\Length(max: 255)]
    protected ?string $description;

    #[Assert\PositiveOrZero]
    protected ?float $amount;

    #[Assert\Length(exactly: 3)]
    protected ?string $currency;

    protected ?string $activeStartAt;

    protected ?string $activeEndAt;

    #[Assert\Length(max: 500)]
    protected ?string $knowMore;

    #[OAProperty(schema: [
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
                    'base'                 => ['type' => 'integer'],
                    'promotion_bonus'      => ['type' => 'integer'],
                    'total_excluding_tax'  => ['type' => 'integer'],
                    'total_including_tax'  => ['type' => 'integer'],
                ]],
                'schedule' => ['type' => 'object', 'nullable' => true, 'default' => null, 'properties' => [
                    'start' => ['type' => 'string'],
                    'end'   => ['type' => 'string', 'nullable' => true, 'default' => null],
                ]],
            ],
        ],
    ])]
    protected ?array $benefits;

    #[OAProperty(schema: [
        'type' => 'array',
        'nullable' => true,
        'items' => ['type' => 'string', 'enum' => ['AIRTIME', 'BUNDLE', 'DATA', 'SMS', 'INTERNET']],
    ])]
    protected ?array $tags;

    #[OAProperty(schema: [
        'type' => 'object',
        'nullable' => true,
        'properties' => [
            'name'       => ['type' => 'string', 'enum' => ['Mobile', 'uSIM', 'Devices']],
            'subservice' => ['type' => 'object', 'properties' => [
                'name' => ['type' => 'string', 'enum' => ['AIRTIME', 'BUNDLE', 'DATA', 'SMS', 'INTERNET', 'uSIM']],
            ]],
        ],
    ])]
    protected ?array $service;

    #[OAProperty(schema: [
        'type' => 'object',
        'nullable' => true,
        'properties' => [
            'amount'    => ['type' => 'number', 'format' => 'currency-number'],
            'unit'      => ['type' => 'string', 'enum' => ['CUP', 'MLC', 'USD']],
            'unit_type' => ['type' => 'string', 'enum' => ['CURRENCY']],
        ],
    ])]
    protected ?array $destination;

    #[OAProperty(schema: [
        'type' => 'object',
        'nullable' => true,
        'default' => null,
        'properties' => [
            'quantity' => ['type' => 'integer'],
            'unit'     => ['type' => 'string', 'enum' => ['DAYS', 'MONTH', 'YEAR']],
        ],
    ])]
    protected ?array $validity;

    public function __construct(
        ?int $tenantId = null,
        ?int $priceClientPackageId = null,
        ?int $productId = null,
        ?array $clients = null,
        ?int $environmentId = null,
        ?string $name = null,
        ?string $description = null,
        ?float $amount = null,
        ?string $currency = null,
        ?string $activeStartAt = null,
        ?string $activeEndAt = null,
        ?string $knowMore = null,
        ?array $benefits = null,
        ?array $tags = null,
        ?array $service = null,
        ?array $destination = null,
        ?array $validity = null,
    ) {
        $this->tenantId = $tenantId;
        $this->priceClientPackageId = $priceClientPackageId;
        $this->productId = $productId;
        $this->clients = $clients;
        $this->environmentId = $environmentId;
        $this->name = $name;
        $this->description = $description;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->activeStartAt = $activeStartAt;
        $this->activeEndAt = $activeEndAt;
        $this->knowMore = $knowMore;
        $this->benefits = $benefits;
        $this->tags = $tags;
        $this->service = $service;
        $this->destination = $destination;
        $this->validity = $validity;
    }

    public function getTenantId(): ?int { return $this->tenantId; }
    public function setTenantId(?int $tenantId): void { $this->tenantId = $tenantId; }

    public function getPriceClientPackageId(): ?int { return $this->priceClientPackageId; }
    public function setPriceClientPackageId(?int $priceClientPackageId): void { $this->priceClientPackageId = $priceClientPackageId; }

    public function getProductId(): ?int { return $this->productId; }
    public function setProductId(?int $productId): void { $this->productId = $productId; }

    /** @return int[]|null */
    public function getClients(): ?array { return $this->clients; }
    /** @param int[]|null $clients */
    public function setClients(?array $clients): void { $this->clients = $clients; }

    public function getEnvironmentId(): ?int { return $this->environmentId; }
    public function setEnvironmentId(?int $environmentId): void { $this->environmentId = $environmentId; }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): void { $this->name = $name; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): void { $this->description = $description; }

    public function getAmount(): ?float { return $this->amount; }
    public function setAmount(?float $amount): void { $this->amount = $amount; }

    public function getCurrency(): ?string { return $this->currency; }
    public function setCurrency(?string $currency): void { $this->currency = $currency; }

    public function getActiveStartAt(): ?string { return $this->activeStartAt; }
    public function setActiveStartAt(?string $activeStartAt): void { $this->activeStartAt = $activeStartAt; }

    public function getActiveEndAt(): ?string { return $this->activeEndAt; }
    public function setActiveEndAt(?string $activeEndAt): void { $this->activeEndAt = $activeEndAt; }

    public function getKnowMore(): ?string { return $this->knowMore; }
    public function setKnowMore(?string $knowMore): void { $this->knowMore = $knowMore; }

    public function getBenefits(): ?array { return $this->benefits; }
    public function setBenefits(?array $benefits): void { $this->benefits = $benefits; }

    public function getTags(): ?array { return $this->tags; }
    public function setTags(?array $tags): void { $this->tags = $tags; }

    public function getService(): ?array { return $this->service; }
    public function setService(?array $service): void { $this->service = $service; }

    public function getDestination(): ?array { return $this->destination; }
    public function setDestination(?array $destination): void { $this->destination = $destination; }

    public function getValidity(): ?array { return $this->validity; }
    public function setValidity(?array $validity): void { $this->validity = $validity; }
}
