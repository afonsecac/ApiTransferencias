<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Alta en lote de CommunicationContract (V2) por RANGO de montos — para un
 * mismo tenant (o por defecto), crea un contrato por cada
 * CommunicationPackage cuyo destino caiga en [fromAmount, toAmount]
 * saltando de `step` en `step`, con el precio interpolado linealmente entre
 * `priceFrom` (para fromAmount) y `priceTo` (para toAmount). Un monto del
 * rango sin CommunicationPackage correspondiente se omite (no aborta el
 * alta) y se reporta en la respuesta — ver
 * CommunicationContractService::createByRange().
 */
class CreateCommunicationContractRangeDto implements IInput
{
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

    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    protected ?float $priceFrom;

    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    protected ?float $priceTo;

    #[Assert\NotBlank]
    #[Assert\Length(exactly: 3)]
    protected ?string $currency;

    /**
     * Client.id (mismo espacio que `clients` en
     * CreateCommunicationContractBatchDto), NO Account.id — se resuelve a
     * la cuenta ACTIVA de ese cliente en `environmentId` vía
     * TargetAccountResolver. null = contrato "por defecto" (aplica a
     * cualquier cuenta sin contrato propio).
     */
    #[Assert\Positive]
    protected ?int $tenantId;

    /** Obligatorio cuando se da `tenantId`. */
    #[Assert\Positive]
    protected ?int $environmentId;

    protected ?string $startAt;

    protected ?string $endAt;

    public function __construct(
        ?float $fromAmount = null,
        ?float $toAmount = null,
        ?float $step = null,
        ?string $destinationCurrency = null,
        ?float $priceFrom = null,
        ?float $priceTo = null,
        ?string $currency = null,
        ?int $tenantId = null,
        ?int $environmentId = null,
        ?string $startAt = null,
        ?string $endAt = null,
    ) {
        $this->fromAmount = $fromAmount;
        $this->toAmount = $toAmount;
        $this->step = $step;
        $this->destinationCurrency = $destinationCurrency;
        $this->priceFrom = $priceFrom;
        $this->priceTo = $priceTo;
        $this->currency = $currency;
        $this->tenantId = $tenantId;
        $this->environmentId = $environmentId;
        $this->startAt = $startAt;
        $this->endAt = $endAt;
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

    public function getFromAmount(): ?float { return $this->fromAmount; }
    public function setFromAmount(?float $v): void { $this->fromAmount = $v; }

    public function getToAmount(): ?float { return $this->toAmount; }
    public function setToAmount(?float $v): void { $this->toAmount = $v; }

    public function getStep(): ?float { return $this->step; }
    public function setStep(?float $v): void { $this->step = $v; }

    public function getDestinationCurrency(): ?string { return $this->destinationCurrency; }
    public function setDestinationCurrency(?string $v): void { $this->destinationCurrency = $v; }

    public function getPriceFrom(): ?float { return $this->priceFrom; }
    public function setPriceFrom(?float $v): void { $this->priceFrom = $v; }

    public function getPriceTo(): ?float { return $this->priceTo; }
    public function setPriceTo(?float $v): void { $this->priceTo = $v; }

    public function getCurrency(): ?string { return $this->currency; }
    public function setCurrency(?string $v): void { $this->currency = $v; }

    public function getTenantId(): ?int { return $this->tenantId; }
    public function setTenantId(?int $v): void { $this->tenantId = $v; }

    public function getEnvironmentId(): ?int { return $this->environmentId; }
    public function setEnvironmentId(?int $v): void { $this->environmentId = $v; }

    public function getStartAt(): ?string { return $this->startAt; }
    public function setStartAt(?string $v): void { $this->startAt = $v; }

    public function getEndAt(): ?string { return $this->endAt; }
    public function setEndAt(?string $v): void { $this->endAt = $v; }
}
