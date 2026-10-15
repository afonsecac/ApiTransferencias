<?php

declare(strict_types=1);

namespace App\Service\Catalog;

use App\Entity\CommunicationPackage;

/**
 * Añade a los paquetes del mercado sus datos propios (COMR-275): unidad, peso, volumen, imágenes, relevancia vigente… Los de
 * comunicaciones no se tocan. Los datos se leen en vivo del producto, no se guardan en el paquete, y un dato sin valor no
 * aparece. La implementación vive en el contexto del mercado; el catálogo solo conoce este puerto.
 */
interface MarketPackageEnricher
{
    /**
     * @param iterable<CommunicationPackage> $packages
     */
    public function enrich(iterable $packages, \DateTimeImmutable $now): void;
}
