<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

use App\DTO\IInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de PUT /dashboard/api/market/products/{id}/images/{imageId}: el tipo de imagen y su texto alternativo en español e
 * inglés. En blanco o ausente, se borra.
 */
class DescribeProductImageDto implements IInput
{
    #[Assert\Length(max: 40)]
    protected ?string $kind;

    #[Assert\Length(max: 200)]
    protected ?string $altEs;

    #[Assert\Length(max: 200)]
    protected ?string $altEn;

    public function __construct(?string $kind = null, ?string $altEs = null, ?string $altEn = null)
    {
        $this->kind = $kind;
        $this->altEs = $altEs;
        $this->altEn = $altEn;
    }

    public function getKind(): ?string
    {
        return $this->kind;
    }

    public function setKind(?string $kind): void
    {
        $this->kind = $kind;
    }

    public function getAltEs(): ?string
    {
        return $this->altEs;
    }

    public function setAltEs(?string $altEs): void
    {
        $this->altEs = $altEs;
    }

    public function getAltEn(): ?string
    {
        return $this->altEn;
    }

    public function setAltEn(?string $altEn): void
    {
        $this->altEn = $altEn;
    }
}
