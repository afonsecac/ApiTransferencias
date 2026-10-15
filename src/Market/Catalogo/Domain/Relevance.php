<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * La relevancia de un producto en las búsquedas (COMR-269): un nivel de 0 (sin destacar) a 3 (el más alto) y una vigencia
 * opcional por días, ambos extremos incluidos. Fuera de la vigencia la marca no tiene efecto. Se compara por día: la hora
 * se ignora. Con nivel 0 no hay vigencia.
 */
final readonly class Relevance
{
    public const MAX_LEVEL = 3;

    private function __construct(
        private int $level,
        private ?\DateTimeImmutable $from,
        private ?\DateTimeImmutable $until,
    ) {
    }

    public static function none(): self
    {
        return new self(0, null, null);
    }

    /**
     * @throws InvalidProduct nivel fuera de 0..3, o fin anterior al inicio
     */
    public static function of(int $level, ?\DateTimeImmutable $from, ?\DateTimeImmutable $until): self
    {
        if ($level < 0 || $level > self::MAX_LEVEL) {
            throw new InvalidProduct(sprintf('El nivel de relevancia va de 0 a %d.', self::MAX_LEVEL));
        }
        if ($level === 0) {
            return self::none();
        }
        $from = $from?->setTime(0, 0);
        $until = $until?->setTime(0, 0);
        if ($from !== null && $until !== null && $until < $from) {
            throw new InvalidProduct('La relevancia no puede terminar antes de empezar.');
        }

        return new self($level, $from, $until);
    }

    public function level(): int
    {
        return $this->level;
    }

    public function from(): ?\DateTimeImmutable
    {
        return $this->from;
    }

    public function until(): ?\DateTimeImmutable
    {
        return $this->until;
    }

    public function isActiveOn(\DateTimeImmutable $moment): bool
    {
        if ($this->level === 0) {
            return false;
        }
        $day = $moment->setTime(0, 0);

        return ($this->from === null || $day >= $this->from) && ($this->until === null || $day <= $this->until);
    }
}
