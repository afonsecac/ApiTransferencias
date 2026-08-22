<?php

namespace App\DTO;

use App\DTO\Validation\BenefitOperationValidator;
use App\OpenApi\Attribute\OAProperty;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Alta en lote de CommunicationPackage (catálogo agnóstico de proveedor,
 * V2) — genera un paquete por cada monto en el rango [fromAmount,
 * toAmount] saltando de `step` en `step` (ambos extremos incluidos).
 * `nameTemplate`/`descriptionTemplate` admiten el placeholder "{monto}",
 * sustituido por el monto de cada paquete generado — ej. "Cubacel {monto}
 * CUP" con fromAmount=100, toAmount=300, step=100 genera "Cubacel 100 CUP",
 * "Cubacel 200 CUP", "Cubacel 300 CUP". El resto de campos (benefits, tags,
 * service, validity, knowMore, displayOrder, vigencia) se aplican
 * idénticos a todos los paquetes generados.
 */
class CreateCommunicationPackageBatchDto implements IInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    protected ?string $nameTemplate;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    protected ?string $descriptionTemplate;

    #[Assert\Length(max: 500)]
    protected ?string $knowMore;

    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?float $fromAmount;

    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?float $toAmount;

    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?float $step;

    #[Assert\NotBlank]
    #[Assert\Length(max: 10)]
    protected ?string $destinationCurrency;

    protected ?bool $isActive;

    protected ?string $activeStartAt;

    protected ?string $activeEndAt;

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
                // Cálculo en vivo contra la línea base (destinationAmount
                // para CREDITS/CURRENCY; el beneficio del mismo type/unit
                // en el paquete regular equivalente para el resto) — ver
                // CommunicationPackageAdminService::applyBenefitOperations().
                // Sin operation, amount.base/promotion_bonus se usan tal
                // cual (comportamiento previo).
                'operation' => ['type' => 'string', 'nullable' => true, 'enum' => ['MULTIPLY', 'ADD', 'SET']],
                'value' => ['type' => 'number', 'nullable' => true, 'example' => 6],
            ],
        ],
    ])]
    protected ?array $benefits;

    #[OAProperty(schema: [
        'type' => 'array',
        'nullable' => true,
        'items' => ['type' => 'string', 'enum' => ['AIRTIME', 'BUNDLE', 'DATA', 'SMS', 'INTERNET', 'LANDLINE', 'UNLIMITED']],
    ])]
    protected ?array $tags;

    #[OAProperty(schema: [
        'type' => 'object',
        'nullable' => true,
        'properties' => [
            'name' => ['type' => 'string', 'enum' => ['Mobile', 'uSIM', 'Devices', 'Utilities']],
            'subservice' => ['type' => 'object', 'properties' => [
                'name' => ['type' => 'string', 'enum' => ['AIRTIME', 'BUNDLE', 'DATA', 'SMS', 'INTERNET', 'LANDLINE', 'uSIM']],
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
        ?string $nameTemplate = null,
        ?string $descriptionTemplate = null,
        ?string $knowMore = null,
        ?float $fromAmount = null,
        ?float $toAmount = null,
        ?float $step = null,
        ?string $destinationCurrency = null,
        ?bool $isActive = null,
        ?string $activeStartAt = null,
        ?string $activeEndAt = null,
        ?int $displayOrder = null,
        ?array $benefits = null,
        ?array $tags = null,
        ?array $service = null,
        ?array $validity = null,
    ) {
        $this->nameTemplate = $nameTemplate;
        $this->descriptionTemplate = $descriptionTemplate;
        $this->knowMore = $knowMore;
        $this->fromAmount = $fromAmount;
        $this->toAmount = $toAmount;
        $this->step = $step;
        $this->destinationCurrency = $destinationCurrency;
        $this->isActive = $isActive;
        $this->activeStartAt = $activeStartAt;
        $this->activeEndAt = $activeEndAt;
        $this->displayOrder = $displayOrder;
        $this->benefits = $benefits;
        $this->tags = $tags;
        $this->service = $service;
        $this->validity = $validity;
    }

    #[Assert\Callback]
    public function validateRange(ExecutionContextInterface $context): void
    {
        if ($this->fromAmount !== null && $this->toAmount !== null && $this->toAmount < $this->fromAmount) {
            $context->buildViolation('El monto final debe ser mayor o igual al monto inicial')
                ->atPath('toAmount')
                ->addViolation();
        }
    }

    #[Assert\Callback]
    public function validateBenefitsOperation(ExecutionContextInterface $context): void
    {
        BenefitOperationValidator::validate($this->benefits, $context);
    }

    public function getNameTemplate(): ?string { return $this->nameTemplate; }
    public function setNameTemplate(?string $v): void { $this->nameTemplate = $v; }

    public function getDescriptionTemplate(): ?string { return $this->descriptionTemplate; }
    public function setDescriptionTemplate(?string $v): void { $this->descriptionTemplate = $v; }

    public function getKnowMore(): ?string { return $this->knowMore; }
    public function setKnowMore(?string $v): void { $this->knowMore = $v; }

    public function getFromAmount(): ?float { return $this->fromAmount; }
    public function setFromAmount(?float $v): void { $this->fromAmount = $v; }

    public function getToAmount(): ?float { return $this->toAmount; }
    public function setToAmount(?float $v): void { $this->toAmount = $v; }

    public function getStep(): ?float { return $this->step; }
    public function setStep(?float $v): void { $this->step = $v; }

    public function getDestinationCurrency(): ?string { return $this->destinationCurrency; }
    public function setDestinationCurrency(?string $v): void { $this->destinationCurrency = $v; }

    public function getIsActive(): ?bool { return $this->isActive; }
    public function setIsActive(?bool $v): void { $this->isActive = $v; }

    public function getActiveStartAt(): ?string { return $this->activeStartAt; }
    public function setActiveStartAt(?string $v): void { $this->activeStartAt = $v; }

    public function getActiveEndAt(): ?string { return $this->activeEndAt; }
    public function setActiveEndAt(?string $v): void { $this->activeEndAt = $v; }

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
