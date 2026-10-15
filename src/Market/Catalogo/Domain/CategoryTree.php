<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * El árbol de categorías (COMR-174): hasta tres niveles, sin ruta materializada. La profundidad y los ciclos se calculan sobre
 * las categorías que hay; son pocas, así que se recorren en memoria. Un padre que no existe se trata como raíz.
 */
final class CategoryTree
{
    public const MAX_DEPTH = 3;

    /** @var array<int, Category> */
    private array $byId = [];

    /** @var array<int|string, list<Category>> hijos por id de padre ('root' para las raíces) */
    private array $children = [];

    /**
     * @param list<Category> $categories
     */
    public function __construct(array $categories)
    {
        foreach ($categories as $category) {
            if ($category->id() !== null) {
                $this->byId[$category->id()] = $category;
            }
        }
        foreach ($categories as $category) {
            $parent = $category->parentId();
            $key = $parent !== null && isset($this->byId[$parent]) ? $parent : 'root';
            $this->children[$key][] = $category;
        }
        foreach ($this->children as &$siblings) {
            usort($siblings, static fn (Category $a, Category $b) => [$a->position(), $a->nameEs(), $a->id()] <=> [$b->position(), $b->nameEs(), $b->id()]);
        }
    }

    /**
     * @return list<Category> sus hijos directos, por posición y nombre (las raíces si no se da padre)
     */
    public function childrenOf(?int $parentId): array
    {
        return $this->children[$parentId ?? 'root'] ?? [];
    }

    /**
     * @return list<int> los ids de todas sus descendientes (hijas, nietas…), sin incluirla
     */
    public function descendantsOf(int $id): array
    {
        $ids = [];
        $pending = [$id];
        $guard = 0;
        while ($pending !== [] && $guard++ < 1000) {
            foreach ($this->childrenOf(array_shift($pending)) as $child) {
                $ids[] = (int) $child->id();
                $pending[] = (int) $child->id();
            }
        }

        return $ids;
    }

    /** Nivel de una categoría: 1 para una raíz. */
    public function depthOf(int $id): int
    {
        $depth = 1;
        $guard = 0;
        $current = $this->byId[$id] ?? null;
        while ($current !== null && $current->parentId() !== null && isset($this->byId[$current->parentId()]) && $guard++ < 100) {
            $current = $this->byId[$current->parentId()];
            ++$depth;
        }

        return $depth;
    }

    /** Niveles que tiene el subárbol que cuelga de una categoría, contando la propia: 1 si es una hoja. */
    public function heightOf(int $id): int
    {
        $height = 1;
        foreach ($this->childrenOf($id) as $child) {
            $height = max($height, 1 + $this->heightOf((int) $child->id()));
        }

        return $height;
    }

    /**
     * @throws CategoryDepthExceeded si el hijo nuevo quedaría en un cuarto nivel
     */
    public function assertCanAddChildTo(?int $parentId): void
    {
        $level = $parentId === null ? 1 : $this->depthOf($parentId) + 1;
        if ($level > self::MAX_DEPTH) {
            throw CategoryDepthExceeded::maximum();
        }
    }

    /**
     * @throws CategoryCycle si el nuevo padre es la propia categoría o una de sus descendientes
     * @throws CategoryDepthExceeded si el subárbol, ya movido, pasaría de tres niveles
     */
    public function assertCanMove(int $id, ?int $newParentId): void
    {
        if ($newParentId !== null && $this->isSelfOrDescendant($newParentId, $id)) {
            throw CategoryCycle::between($id, $newParentId);
        }
        $parentDepth = $newParentId === null ? 0 : $this->depthOf($newParentId);
        if ($parentDepth + $this->heightOf($id) > self::MAX_DEPTH) {
            throw CategoryDepthExceeded::maximum();
        }
    }

    private function isSelfOrDescendant(int $candidate, int $ancestor): bool
    {
        if ($candidate === $ancestor) {
            return true;
        }
        foreach ($this->childrenOf($ancestor) as $child) {
            if ($this->isSelfOrDescendant($candidate, (int) $child->id())) {
                return true;
            }
        }

        return false;
    }
}
