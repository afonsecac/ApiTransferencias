<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

use App\DTO\IInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de PUT /dashboard/api/market/products/{id}/package (COMR-275): el precio mínimo con el que se ofrece el producto al
 * comprador, como texto con punto decimal (nunca coma flotante), y su moneda. Provisional hasta las tarifas del hito 3.
 */
class PublishProductPackageDto implements IInput
{
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{1,12}(\.\d{1,2})?$/', message: 'Debe ser un importe positivo con hasta dos decimales y punto decimal.')]
    protected ?string $price;

    #[Assert\NotBlank]
    #[Assert\Length(exactly: 3)]
    protected ?string $currency;

    public function __construct(?string $price = null, ?string $currency = null)
    {
        $this->price = $price;
        $this->currency = $currency;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(?string $price): void
    {
        $this->price = $price;
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
