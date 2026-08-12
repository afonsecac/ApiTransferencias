<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Edición de un CommunicationContract existente (V2) — precio/moneda/
 * vigencia, y opcionalmente el paquete al que apunta. El tenant es la otra
 * mitad de la identidad del contrato y no se reasigna (crear uno nuevo si
 * hace falta cambiarlo). El paquete SÍ puede reasignarse, pero nunca por id
 * — se referencia por su `destinationAmount` (+ `destinationCurrency`
 * opcional si cambia de moneda/unidad), resuelto contra el catálogo vigente
 * vía CommunicationPackageRepository::findByDestination().
 */
class UpdateCommunicationContractDto implements IInput
{
    #[Assert\PositiveOrZero]
    protected ?float $price;

    #[Assert\Length(exactly: 3)]
    protected ?string $currency;

    protected ?string $startAt;

    protected ?string $endAt;

    #[Assert\Positive]
    protected ?float $destinationAmount;

    #[Assert\Length(max: 10)]
    protected ?string $destinationCurrency;

    public function __construct(
        ?float $price = null,
        ?string $currency = null,
        ?string $startAt = null,
        ?string $endAt = null,
        ?float $destinationAmount = null,
        ?string $destinationCurrency = null,
    ) {
        $this->price = $price;
        $this->currency = $currency;
        $this->startAt = $startAt;
        $this->endAt = $endAt;
        $this->destinationAmount = $destinationAmount;
        $this->destinationCurrency = $destinationCurrency;
    }

    public function getPrice(): ?float { return $this->price; }
    public function setPrice(?float $v): void { $this->price = $v; }

    public function getCurrency(): ?string { return $this->currency; }
    public function setCurrency(?string $v): void { $this->currency = $v; }

    public function getStartAt(): ?string { return $this->startAt; }
    public function setStartAt(?string $v): void { $this->startAt = $v; }

    public function getEndAt(): ?string { return $this->endAt; }
    public function setEndAt(?string $v): void { $this->endAt = $v; }

    public function getDestinationAmount(): ?float { return $this->destinationAmount; }
    public function setDestinationAmount(?float $v): void { $this->destinationAmount = $v; }

    public function getDestinationCurrency(): ?string { return $this->destinationCurrency; }
    public function setDestinationCurrency(?string $v): void { $this->destinationCurrency = $v; }
}
