<?php

namespace App\State;

use ApiPlatform\State\Pagination\ArrayPaginator;
use ApiPlatform\State\Pagination\HasNextPagePaginatorInterface;
use ApiPlatform\State\Pagination\PaginatorInterface;

/**
 * ArrayPaginator con las claves de la página renumeradas desde 0.
 *
 * ArrayPaginator recorre el arreglo completo con un LimitIterator y conserva sus claves, así que la página 2 con 100 por
 * página sale con claves 100…199 y el serializador JSON la emite como objeto ({"100": …}) en lugar de lista. Los clientes
 * que esperan un arreglo (la primera página sí lo era) fallaban o leían mal a partir de la segunda.
 */
final class ListPaginator implements \IteratorAggregate, PaginatorInterface, HasNextPagePaginatorInterface
{
    private readonly ArrayPaginator $inner;

    public function __construct(array $results, int $firstResult, int $maxResults)
    {
        $this->inner = new ArrayPaginator($results, $firstResult, $maxResults);
    }

    public function getCurrentPage(): float
    {
        return $this->inner->getCurrentPage();
    }

    public function getLastPage(): float
    {
        return $this->inner->getLastPage();
    }

    public function getItemsPerPage(): float
    {
        return $this->inner->getItemsPerPage();
    }

    public function getTotalItems(): float
    {
        return $this->inner->getTotalItems();
    }

    public function count(): int
    {
        return $this->inner->count();
    }

    public function hasNextPage(): bool
    {
        return $this->inner->hasNextPage();
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator(array_values(iterator_to_array($this->inner->getIterator())));
    }
}
