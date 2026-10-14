<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

use App\DTO\IInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de PUT /dashboard/api/market/products/{id}/images/order: el orden nuevo, con todas las imágenes de la galería.
 */
class ReorderProductImagesDto implements IInput
{
    /** @var list<int>|null */
    #[Assert\NotNull]
    #[Assert\Type('array')]
    #[Assert\Count(max: 100)]
    #[Assert\All([new Assert\Type('int'), new Assert\Positive()])]
    protected ?array $ids;

    /**
     * @param list<int>|null $ids
     */
    public function __construct(?array $ids = null)
    {
        $this->ids = $ids;
    }

    /**
     * @return list<int>|null
     */
    public function getIds(): ?array
    {
        return $this->ids;
    }

    /**
     * @param list<int>|null $ids
     */
    public function setIds(?array $ids): void
    {
        $this->ids = $ids;
    }
}
