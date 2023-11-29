<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use App\State\CalculatorProcessor;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/calculator',
            processor: CalculatorProcessor::class
        )
    ],
    normalizationContext: ['groups' => ['calc:read']],
    denormalizationContext: ['groups' => ['calc:write']],
)]
class Calculator
{
    #[ApiProperty]
    #[Groups(['calc:read', 'calc:write'])]
    #[Assert\Positive]
    #[Assert\NotNull]
    #[Assert\GreaterThan(value: 50, message: "The transfer has been greater than 50 USD")]
    #[Assert\LessThanOrEqual(value: 2000, message: "The transfer has been less than 2000 USD or equals")]
    private float $sendAmount = 0;
    #[Groups(['calc:read', 'calc:write'])]
    #[ApiProperty(default: 'USD', types: ['https://schema.org/priceCurrency'])]
    #[Assert\Length(exactly: 3)]
    #[Assert\Currency]
    #[Assert\NotBlank]
    #[Assert\Choice(
        choices: ['USD']
    )]
    private string $sendCurrency = 'USD';
    #[Groups(['calc:read'])]
    private ?float $receiveAmount = null;
    #[Groups(['calc:read'])]
    private ?float $feeAmount = null;
    #[Groups(['calc:read'])]
    private ?float $total = null;
    #[Groups(['calc:read'])]
    #[ApiProperty(types: ['https://schema.org/priceCurrency'])]
    #[Assert\Length(exactly: 3)]
    #[Assert\Currency]
    private ?string $totalCurrency = null;
    #[Groups(['calc:read'])]
    private ?float $rate = null;
    #[Groups(['calc:read'])]
    #[ApiProperty(types: ['https://schema.org/priceCurrency'])]
    #[Assert\Length(exactly: 3)]
    #[Assert\Currency]
    private ?string $toCurrency = null;

    public function getSendAmount(): float
    {
        return $this->sendAmount;
    }

    public function setSendAmount(float $sendAmount): void
    {
        $this->sendAmount = $sendAmount;
    }

    public function getSendCurrency(): string
    {
        return $this->sendCurrency;
    }

    public function setSendCurrency(string $sendCurrency): void
    {
        $this->sendCurrency = $sendCurrency;
    }

    public function getReceiveAmount(): ?float
    {
        return $this->receiveAmount;
    }

    public function setReceiveAmount(?float $receiveAmount): void
    {
        $this->receiveAmount = $receiveAmount;
    }

    public function getFeeAmount(): ?float
    {
        return $this->feeAmount;
    }

    public function setFeeAmount(?float $feeAmount): void
    {
        $this->feeAmount = $feeAmount;
    }

    public function getTotal(): ?float
    {
        return $this->total;
    }

    public function setTotal(?float $total): void
    {
        $this->total = $total;
    }

    public function getTotalCurrency(): ?string
    {
        return $this->totalCurrency;
    }

    public function setTotalCurrency(?string $totalCurrency): void
    {
        $this->totalCurrency = $totalCurrency;
    }

    public function getRate(): ?float
    {
        return $this->rate;
    }

    public function setRate(?float $rate): void
    {
        $this->rate = $rate;
    }

    public function getToCurrency(): ?string
    {
        return $this->toCurrency;
    }

    public function setToCurrency(?string $toCurrency): void
    {
        $this->toCurrency = $toCurrency;
    }

}
