<?php

namespace App\DTO;

use App\DTO\Validation\BenefitOperationValidator;
use App\DTO\Validation\BenefitScheduleValidator;
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
 * compartido, vigente solo durante [startAt, endAt]. NO tiene precio: el
 * precio vive en CommunicationContract, un concepto aparte que se gestiona
 * por su propio flujo administrativo (createSingle()/createBatch()/
 * createByRange() en CommunicationContractService) — createV2() solo se
 * encarga de generar los paquetes y sus beneficios; sin contrato propio de
 * un tenant que ya cubra el paquete regular equivalente (ver
 * linkTenantContractsToPromotionPackages()), el paquete recién creado
 * queda visible al precio derivado del catálogo (PackageCatalogResolver,
 * MAX de producto + margen) hasta que se le cree un contrato. Tampoco
 * tiene productId: las equivalencias por proveedor se resuelven aparte
 * (Fase 5C/5D) — sin equivalencia explícita, ningún proveedor despacha ese
 * tramo.
 */
class CreatePromotionV2Dto implements IInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    protected ?string $name;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    protected ?string $description;

    protected ?string $infoDescription;

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
                // BenefitOperationResolver. Sin operation, amount.base/
                // promotion_bonus se usan tal cual (comportamiento previo).
                // Con operation, `value` es obligatorio y amount.base/
                // promotion_bonus se recalculan EN VIVO al servir el
                // catálogo (no al crear), sobrescribiendo lo que se haya
                // enviado.
                'operation' => ['type' => 'string', 'nullable' => true, 'enum' => ['MULTIPLY', 'ADD', 'SET']],
                'value' => ['type' => 'number', 'nullable' => true, 'example' => 6],
                // Franja horaria diaria de vigencia de este beneficio (uso
                // pensado para DATA/ILIM — "internet ilimitado de 01:00 a
                // 06:00"). Ambos null = vigente las 24h. Solo informativa:
                // no se valida contra ningún otro campo ni se aplica en
                // lógica de despacho.
                'schedule' => ['type' => 'object', 'nullable' => true, 'default' => null, 'properties' => [
                    'start' => ['type' => 'string', 'nullable' => true, 'example' => '01:00'],
                    'end' => ['type' => 'string', 'nullable' => true, 'example' => '06:00'],
                ]],
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
        ?string $name = null,
        ?string $description = null,
        ?string $infoDescription = null,
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
        ?int $displayOrder = null,
        ?array $benefits = null,
        ?array $tags = null,
        ?array $service = null,
        ?array $validity = null,
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->infoDescription = $infoDescription;
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

    /**
     * `schedule` es opcional y puramente informativo (ver docblock de la
     * clase) — no se valida contra el `type`/`unit` del beneficio, solo su
     * propia forma: ambos extremos null (24h) o ambos presentes en formato
     * HH:mm y distintos entre sí (una franja de largo cero no tiene
     * sentido).
     */
    #[Assert\Callback]
    public function validateBenefitsSchedule(ExecutionContextInterface $context): void
    {
        BenefitScheduleValidator::validate($this->benefits, $context);
    }

    #[Assert\Callback]
    public function validateBenefitsOperation(ExecutionContextInterface $context): void
    {
        BenefitOperationValidator::validate($this->benefits, $context);
    }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $v): void { $this->name = $v; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): void { $this->description = $v; }

    public function getInfoDescription(): ?string { return $this->infoDescription; }
    public function setInfoDescription(?string $v): void { $this->infoDescription = $v; }

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
