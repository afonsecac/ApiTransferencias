<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Infrastructure\Http;

use App\DTO\IInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de PUT /dashboard/api/market/warehouses/{id}/delivery-currency.
 */
class SetDeliveryCurrencyDto implements IInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 3)]
    protected ?string $currency;

    public function __construct(?string $currency = null)
    {
        $this->currency = $currency;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): void
    {
        $this->currency = $currency;
    }
}
