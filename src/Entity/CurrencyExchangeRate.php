<?php

namespace App\Entity;

use App\Repository\CurrencyExchangeRateRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Fila histórica de tasa de cambio (Frankfurter, base EUR). Nunca se
 * actualiza in-place: cada sincronización inserta una fila nueva por
 * rate_date, conservando el histórico completo (ver Version20260801180000
 * y CurrencyExchangeRateSyncService).
 */
#[ORM\Entity(repositoryClass: CurrencyExchangeRateRepository::class)]
#[ORM\UniqueConstraint(
    name: 'uniq_currency_exchange_rate_scope',
    fields: ['baseCurrency', 'targetCurrency', 'rateDate'],
)]
class CurrencyExchangeRate
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 3)]
    private ?string $baseCurrency = null;

    #[ORM\Column(length: 3)]
    private ?string $targetCurrency = null;

    #[ORM\Column]
    private ?float $rate = null;

    /**
     * Fecha de referencia que reporta Frankfurter (su campo `date`) — el
     * día hábil al que corresponde la tasa, no cuándo la consultamos.
     */
    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $rateDate = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $fetchedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBaseCurrency(): ?string
    {
        return $this->baseCurrency;
    }

    public function setBaseCurrency(string $baseCurrency): static
    {
        $this->baseCurrency = $baseCurrency;

        return $this;
    }

    public function getTargetCurrency(): ?string
    {
        return $this->targetCurrency;
    }

    public function setTargetCurrency(string $targetCurrency): static
    {
        $this->targetCurrency = $targetCurrency;

        return $this;
    }

    public function getRate(): ?float
    {
        return $this->rate;
    }

    public function setRate(float $rate): static
    {
        $this->rate = $rate;

        return $this;
    }

    public function getRateDate(): ?\DateTimeImmutable
    {
        return $this->rateDate;
    }

    public function setRateDate(\DateTimeImmutable $rateDate): static
    {
        $this->rateDate = $rateDate;

        return $this;
    }

    public function getFetchedAt(): ?\DateTimeImmutable
    {
        return $this->fetchedAt;
    }

    public function setFetchedAt(\DateTimeImmutable $fetchedAt): static
    {
        $this->fetchedAt = $fetchedAt;

        return $this;
    }
}
