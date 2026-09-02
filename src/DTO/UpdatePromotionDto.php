<?php

namespace App\DTO;

use App\DTO\Validation\BenefitOperationValidator;
use App\DTO\Validation\BenefitScheduleValidator;
use App\OpenApi\Attribute\OAProperty;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class UpdatePromotionDto implements IInput
{
    protected ?string $name;

    protected ?string $description;

    protected ?string $infoDescription;

    protected ?string $knowMore;

    /**
     * Solo para promociones V1 (legacy) — ver CommunicationClientPackage::
     * mergeBenefitsWithPromotions(). Para V2 usar `benefits` en su lugar;
     * enviar `terms` a una promoción V2 no tiene ningún efecto (nada lo lee).
     */
    protected ?array $terms;

    /**
     * Solo para promociones V2 (catálogo compartido) — mismo shape que
     * CreatePromotionV2Dto::$benefits. CommunicationPromotionService::
     * updatePackageBenefits() lo propaga TAL CUAL a cada CommunicationPackage
     * vinculado a esta promoción (mismo criterio que al crear el batch
     * original): CommunicationPackageAdminService::update() recalcula
     * additional_information/amount.base de los beneficios CREDITS/CURRENCY
     * contra el destinationAmount propio de cada paquete, así que el mismo
     * array "plantilla" produce el texto correcto en cada uno. Enviar
     * `benefits` a una promoción V1 (con producto de origen) lanza
     * PROMOTION_BENEFITS_NOT_APPLICABLE.
     */
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
                'operation' => ['type' => 'string', 'nullable' => true, 'enum' => ['MULTIPLY', 'ADD', 'SET']],
                'value' => ['type' => 'number', 'nullable' => true, 'example' => 6],
                'schedule' => ['type' => 'object', 'nullable' => true, 'default' => null, 'properties' => [
                    'start' => ['type' => 'string', 'nullable' => true, 'example' => '01:00'],
                    'end' => ['type' => 'string', 'nullable' => true, 'example' => '06:00'],
                ]],
            ],
        ],
    ])]
    protected ?array $benefits;

    protected ?array $validityInfo;

    protected ?string $startAt;

    protected ?string $endAt;

    #[Assert\Positive]
    protected ?int $productId;

    protected ?int $environmentId;

    protected ?string $priority;

    /**
     * Solo V2 — mismo "{monto}" que CreatePromotionV2Dto::$packageNameTemplate,
     * renderizado por CommunicationPromotionService::updatePackageMetadata()
     * contra el destinationAmount propio de cada paquete generado.
     */
    #[Assert\Length(max: 255)]
    protected ?string $packageNameTemplate;

    #[Assert\Length(max: 255)]
    protected ?string $packageDescriptionTemplate;

    protected ?array $tags;

    protected ?array $service;

    #[Assert\PositiveOrZero]
    protected ?int $displayOrder;

    /**
     * El rango de una promoción V2 (montos, salto, moneda) define QUÉ
     * paquetes existen — cambiarlo después de creada invalidaría los
     * vínculos por proveedor ya hechos sobre los paquetes actuales. No son
     * editables: si llegan, update() rechaza explícitamente en vez de
     * ignorarlos en silencio (ver PROMOTION_V2_RANGE_IMMUTABLE).
     */
    protected ?float $amountFrom;

    protected ?float $amountTo;

    protected ?float $amountStep;

    protected ?string $destinationCurrency;

    public function __construct(
        ?string $name = null,
        ?string $description = null,
        ?string $infoDescription = null,
        ?string $knowMore = null,
        ?array $terms = null,
        ?array $benefits = null,
        ?array $validityInfo = null,
        ?string $startAt = null,
        ?string $endAt = null,
        ?int $productId = null,
        ?int $environmentId = null,
        ?string $priority = null,
        ?string $packageNameTemplate = null,
        ?string $packageDescriptionTemplate = null,
        ?array $tags = null,
        ?array $service = null,
        ?int $displayOrder = null,
        ?float $amountFrom = null,
        ?float $amountTo = null,
        ?float $amountStep = null,
        ?string $destinationCurrency = null,
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->infoDescription = $infoDescription;
        $this->knowMore = $knowMore;
        $this->terms = $terms;
        $this->benefits = $benefits;
        $this->validityInfo = $validityInfo;
        $this->startAt = $startAt;
        $this->endAt = $endAt;
        $this->productId = $productId;
        $this->environmentId = $environmentId;
        $this->priority = $priority;
        $this->packageNameTemplate = $packageNameTemplate;
        $this->packageDescriptionTemplate = $packageDescriptionTemplate;
        $this->tags = $tags;
        $this->service = $service;
        $this->displayOrder = $displayOrder;
        $this->amountFrom = $amountFrom;
        $this->amountTo = $amountTo;
        $this->amountStep = $amountStep;
        $this->destinationCurrency = $destinationCurrency;
    }

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

    public function getKnowMore(): ?string { return $this->knowMore; }
    public function setKnowMore(?string $v): void { $this->knowMore = $v; }

    public function getTerms(): ?array { return $this->terms; }
    public function setTerms(?array $v): void { $this->terms = $v; }

    public function getBenefits(): ?array { return $this->benefits; }
    public function setBenefits(?array $v): void { $this->benefits = $v; }

    public function getValidityInfo(): ?array { return $this->validityInfo; }
    public function setValidityInfo(?array $v): void { $this->validityInfo = $v; }

    public function getStartAt(): ?string { return $this->startAt; }
    public function setStartAt(?string $v): void { $this->startAt = $v; }

    public function getEndAt(): ?string { return $this->endAt; }
    public function setEndAt(?string $v): void { $this->endAt = $v; }

    public function getProductId(): ?int { return $this->productId; }
    public function setProductId(?int $v): void { $this->productId = $v; }

    public function getEnvironmentId(): ?int { return $this->environmentId; }
    public function setEnvironmentId(?int $v): void { $this->environmentId = $v; }

    public function getPriority(): ?string { return $this->priority; }
    public function setPriority(?string $v): void { $this->priority = $v; }

    public function getPackageNameTemplate(): ?string { return $this->packageNameTemplate; }
    public function setPackageNameTemplate(?string $v): void { $this->packageNameTemplate = $v; }

    public function getPackageDescriptionTemplate(): ?string { return $this->packageDescriptionTemplate; }
    public function setPackageDescriptionTemplate(?string $v): void { $this->packageDescriptionTemplate = $v; }

    public function getTags(): ?array { return $this->tags; }
    public function setTags(?array $v): void { $this->tags = $v; }

    public function getService(): ?array { return $this->service; }
    public function setService(?array $v): void { $this->service = $v; }

    public function getDisplayOrder(): ?int { return $this->displayOrder; }
    public function setDisplayOrder(?int $v): void { $this->displayOrder = $v; }

    public function getAmountFrom(): ?float { return $this->amountFrom; }
    public function setAmountFrom(?float $v): void { $this->amountFrom = $v; }

    public function getAmountTo(): ?float { return $this->amountTo; }
    public function setAmountTo(?float $v): void { $this->amountTo = $v; }

    public function getAmountStep(): ?float { return $this->amountStep; }
    public function setAmountStep(?float $v): void { $this->amountStep = $v; }

    public function getDestinationCurrency(): ?string { return $this->destinationCurrency; }
    public function setDestinationCurrency(?string $v): void { $this->destinationCurrency = $v; }
}
