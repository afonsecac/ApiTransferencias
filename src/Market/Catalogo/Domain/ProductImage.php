<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Una imagen de la galería de un producto (COMR-274). Guarda lo subido y su huella de archivo (con la que se nombran los
 * tamaños generados), no los bytes: el almacenamiento lo gestiona un puerto. La galería es una lista ordenada con una imagen
 * de referencia (la de la tarjeta del catálogo y la miniatura en los combos); el orden y la referencia los mantiene
 * ProductGallery. El tipo (frente, etiqueta, pack, ambiente) es una sugerencia libre, y el texto alternativo va en español
 * e inglés.
 */
#[ORM\Entity]
#[ORM\Table(name: 'market_product_image')]
#[ORM\Index(name: 'idx_market_product_image_product', columns: ['product_id'])]
class ProductImage
{
    public const MIMES = ['image/jpeg', 'image/png', 'image/webp'];
    public const KIND_MAX = 40;
    public const ALT_MAX = 200;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'product_id', type: 'integer')]
    private int $productId;

    /** La huella con la que se nombran los archivos de los tamaños; no cambia aunque se reordene o se describa. */
    #[ORM\Column(type: 'string', length: 64)]
    private string $file;

    #[ORM\Column(name: 'original_name', type: 'string', length: 255)]
    private string $originalName;

    #[ORM\Column(type: 'string', length: 20)]
    private string $mime;

    #[ORM\Column(type: 'integer')]
    private int $bytes;

    #[ORM\Column(type: 'integer')]
    private int $width;

    #[ORM\Column(type: 'integer')]
    private int $height;

    #[ORM\Column(type: 'string', length: 40, nullable: true)]
    private ?string $kind = null;

    #[ORM\Column(name: 'alt_es', type: 'string', length: 200, nullable: true)]
    private ?string $altEs = null;

    #[ORM\Column(name: 'alt_en', type: 'string', length: 200, nullable: true)]
    private ?string $altEn = null;

    #[ORM\Column(type: 'integer')]
    private int $position = 0;

    #[ORM\Column(name: 'is_reference', type: 'boolean')]
    private bool $isReference = false;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    private function __construct(int $productId, string $file, string $originalName, string $mime, int $bytes, int $width, int $height)
    {
        if (!in_array($mime, self::MIMES, true)) {
            throw InvalidProductImage::unsupportedMime($mime);
        }
        if ($bytes < 1 || $width < 1 || $height < 1) {
            throw InvalidProductImage::empty();
        }
        $this->productId = $productId;
        $this->file = $file;
        $this->originalName = mb_substr($originalName, 0, 255);
        $this->mime = $mime;
        $this->bytes = $bytes;
        $this->width = $width;
        $this->height = $height;
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * @throws InvalidProductImage
     */
    public static function create(int $productId, string $file, string $originalName, string $mime, int $bytes, int $width, int $height): self
    {
        return new self($productId, $file, $originalName, $mime, $bytes, $width, $height);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function productId(): int
    {
        return $this->productId;
    }

    public function file(): string
    {
        return $this->file;
    }

    public function originalName(): string
    {
        return $this->originalName;
    }

    public function mime(): string
    {
        return $this->mime;
    }

    public function bytes(): int
    {
        return $this->bytes;
    }

    public function width(): int
    {
        return $this->width;
    }

    public function height(): int
    {
        return $this->height;
    }

    public function kind(): ?string
    {
        return $this->kind;
    }

    public function altEs(): ?string
    {
        return $this->altEs;
    }

    public function altEn(): ?string
    {
        return $this->altEn;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function isReference(): bool
    {
        return $this->isReference;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Un valor en blanco se borra.
     *
     * @throws InvalidProductImage un texto demasiado largo
     */
    public function describe(?string $kind, ?string $altEs, ?string $altEn): void
    {
        $kind = self::clean($kind, 'El tipo', self::KIND_MAX);
        $altEs = self::clean($altEs, 'El texto alternativo en español', self::ALT_MAX);
        $altEn = self::clean($altEn, 'El texto alternativo en inglés', self::ALT_MAX);

        $this->kind = $kind;
        $this->altEs = $altEs;
        $this->altEn = $altEn;
    }

    /**
     * @throws InvalidProductImage
     */
    public function moveTo(int $position): void
    {
        if ($position < 1) {
            throw InvalidProductImage::badPosition();
        }
        $this->position = $position;
    }

    public function markAsReference(): void
    {
        $this->isReference = true;
    }

    public function unmarkAsReference(): void
    {
        $this->isReference = false;
    }

    private static function clean(?string $value, string $field, int $max): ?string
    {
        $value = $value === null ? '' : trim($value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $max) {
            throw InvalidProductImage::tooLong($field, $max);
        }

        return $value;
    }
}
