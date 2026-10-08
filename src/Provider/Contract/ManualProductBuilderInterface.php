<?php

namespace App\Provider\Contract;

use App\Enums\CommunicationProviderEnum;

/**
 * Alta manual de CommunicationProduct por proveedor, sin llamar a la API del
 * proveedor — para catálogo/promociones que el proveedor no publica en su
 * endpoint de catálogo (ver docs de la ventana "Alta manual de productos").
 * Cada implementación decide qué campos le hacen falta a SU proveedor
 * (`getFormSchema()`) y cómo traducirlos a `ProviderProductDto` (`build()`),
 * el mismo tipo que produce `ProviderCatalogInterface::fetchProducts()` — así
 * el alta manual reutiliza el upsert de `CommunicationCatalogSyncService` y
 * genera filas indistinguibles de las que produciría el sync automático.
 * No todo proveedor tiene una implementación (ver ManualProductBuilderRegistry).
 */
interface ManualProductBuilderInterface
{
    public function getCode(): CommunicationProviderEnum;

    /**
     * @return list<ManualProductFormField>
     */
    public function getFormSchema(): array;

    /**
     * @param array<string, mixed> $values
     * @return iterable<ProviderProductDto>
     *
     * @throws \App\Exception\MyCurrentException si los valores no son
     *         coherentes para este proveedor (rango inválido, campo
     *         requerido ausente, opción de select desconocida, etc.)
     */
    public function build(array $values): iterable;
}
