<?php

namespace App\DTO;

use App\OpenApi\Attribute\OAProperty;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Alta de promoción V2 (catálogo compartido, Fase 5B) — a diferencia de
 * UpsertPromotionDto (legacy, un producto de origen + paquetes por
 * cliente), esta genera un CommunicationPackage por cada monto del rango
 * [amountFrom, amountTo] saltando de amountStep en amountStep (ambos
 * extremos incluidos, así que amountFrom == amountTo es válido para una
 * promoción de un solo producto), marcado con esta promoción — catálogo
 * compartido, vigente solo durante [startAt, endAt] — más un
 * CommunicationContract "por defecto" por cada uno, con el precio
 * interpolado linealmente entre priceFrom/priceTo. NO tiene productId: las
 * equivalencias por proveedor se resuelven aparte (Fase 5C/5D) — sin
 * equivalencia explícita, ningún proveedor despacha ese tramo.
 */
class CreatePromotionV2Dto implements IInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    protected ?string $name;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    protected ?string $description;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    protected ?string $packageNameTemplate;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    protected ?string $packageDescriptionTemplate;

    #[Assert\Length(max: 500)]
    protected ?string $knowMore;

    #[Assert\NotBlank]
    protected ?string $startAt;

    #[Assert\NotBlank]
    protected ?string $endAt;

    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?int $environmentId;

    #[Assert\NotBlank]
    #[Assert\Length(max: 10)]
    protected ?string $destinationCurrency;

    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?float $amountFrom;

    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?float $amountTo;

    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?float $amountStep;

    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    protected ?float $priceFrom;

    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    protected ?float $priceTo;

    #[Assert\NotBlank]
    #[Assert\Length(exactly: 3)]
    protected ?string $priceCurrency;

    #[Assert\PositiveOrZero]
    protected ?int $displayOrder;

    #[OAProperty(schema: [
        'type' => 'array',
        'nullable' => true,
        'items' => [
            'type' => 'object',
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['CREDITS', 'TALKTIME', 'DATA', 'SMS']],
                'unit' => ['type' => 'string', 'enum' => ['CUP', 'USD', 'UNITS', 'MINUTES', 'GB', 'ILIM']],
                'unit_type' => ['type' => 'string', 'enum' => ['CURRENCY', 'QUANTITY', 'DATA', 'TIME']],
                'additional_information' => ['type' => 'string'],
                'amount' => ['type' => 'object', 'properties' => [
                    'base' => ['type' => 'integer'],
                    'promotion_bonus' => ['type' => 'integer'],
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
            'name' => ['type' => 'string', 'enum' => ['Mobile', 'uSIM', 'Devices']],
            'subservice' => ['type' => 'object', 'properties' => [
                'name' => ['type' => 'string', 'enum' => ['AIRTIME', 'BUNDLE', 'DATA', 'SMS', 'INTERNET', 'uSIM']],
            ]],
        ],
    ])]
    protected ?array $service;

    #[OAProperty(schema: [
        'type' => 'object',
        'nullable' => true,
        'default' => null,
        'properties' => [
            'quantity' => ['type' => 'integer'],
            'unit' => ['type' => 'string', 'enum' => ['DAYS', 'MONTH', 'YEAR']],
        ],
    ])]
    protected ?array $validity;

    public function __construct(
        ?string $name = null,
        ?string $description = null,
        ?string $packageNameTemplate = null,
        ?string $packageDescriptionTemplate = null,
        ?string $knowMore = null,
        ?string $startAt = null,
        ?string $endAt = null,
        ?int $environmentId = null,
        ?string $destinationCurrency = null,
        ?float $amountFrom = null,
        ?float $amountTo = null,
        ?float $amountStep = null,
        ?float $priceFrom = null,
        ?float $priceTo = null,
        ?string $priceCurrency = null,
        ?int $displayOrder = null,
        ?array $benefits = null,
        ?array $tags = null,
        ?array $service = null,
        ?array $validity = null,
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->packageNameTemplate = $packageNameTemplate;
        $this->packageDescriptionTemplate = $packageDescriptionTemplate;
        $this->knowMore = $knowMore;
        $this->startAt = $startAt;
        $this->endAt = $endAt;
        $this->environmentId = $environmentId;
        $this->destinationCurrency = $destinationCurrency;
        $this->amountFrom = $amountFrom;
        $this->amountTo = $amountTo;
        $this->amountStep = $amountStep;
        $this->priceFrom = $priceFrom;
        $this->priceTo = $priceTo;
        $this->priceCurrency = $priceCurrency;
        $this->displayOrder = $displayOrder;
        $this->benefits = $benefits;
        $this->tags = $tags;
        $this->service = $service;
        $this->validity = $validity;
    }

    #[Assert\Callback]
    public function validateRange(ExecutionContextInterface $context): void
    {
        if ($this->amountFrom !== null && $this->amountTo !== null && $this->amountTo < $this->amountFrom) {
            $context->buildViolation('El monto final debe ser mayor o igual al monto inicial')
                ->atPath('amountTo')
                ->addViolation();
        }
    }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $v): void { $this->name = $v; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): void { $this->description = $v; }

    public function getPackageNameTemplate(): ?string { return $this->packageNameTemplate; }
    public function setPackageNameTemplate(?string $v): void { $this->packageNameTemplate = $v; }

    public function getPackageDescriptionTemplate(): ?string { return $this->packageDescriptionTemplate; }
    public function setPackageDescriptionTemplate(?string $v): void { $this->packageDescriptionTemplate = $v; }

    public function getKnowMore(): ?string { return $this->knowMore; }
    public function setKnowMore(?string $v): void { $this->knowMore = $v; }

    public function getStartAt(): ?string { return $this->startAt; }
    public function setStartAt(?string $v): void { $this->startAt = $v; }

    public function getEndAt(): ?string { return $this->endAt; }
    public function setEndAt(?string $v): void { $this->endAt = $v; }

    public function getEnvironmentId(): ?int { return $this->environmentId; }
    public function setEnvironmentId(?int $v): void { $this->environmentId = $v; }

    public function getDestinationCurrency(): ?string { return $this->destinationCurrency; }
    public function setDestinationCurrency(?string $v): void { $this->destinationCurrency = $v; }

    public function getAmountFrom(): ?float { return $this->amountFrom; }
    public function setAmountFrom(?float $v): void { $this->amountFrom = $v; }

    public function getAmountTo(): ?float { return $this->amountTo; }
    public function setAmountTo(?float $v): void { $this->amountTo = $v; }

    public function getAmountStep(): ?float { return $this->amountStep; }
    public function setAmountStep(?float $v): void { $this->amountStep = $v; }

    public function getPriceFrom(): ?float { return $this->priceFrom; }
    public function setPriceFrom(?float $v): void { $this->priceFrom = $v; }

    public function getPriceTo(): ?float { return $this->priceTo; }
    public function setPriceTo(?float $v): void { $this->priceTo = $v; }

    public function getPriceCurrency(): ?string { return $this->priceCurrency; }
    public function setPriceCurrency(?string $v): void { $this->priceCurrency = $v; }

    public function getDisplayOrder(): ?int { return $this->displayOrder; }
    public function setDisplayOrder(?int $v): void { $this->displayOrder = $v; }

    public function getBenefits(): ?array { return $this->benefits; }
    public function setBenefits(?array $v): void { $this->benefits = $v; }

    public function getTags(): ?array { return $this->tags; }
    public function setTags(?array $v): void { $this->tags = $v; }

    public function getService(): ?array { return $this->service; }
    public function setService(?array $v): void { $this->service = $v; }

    public function getValidity(): ?array { return $this->validity; }
    public function setValidity(?array $v): void { $this->validity = $v; }
}
