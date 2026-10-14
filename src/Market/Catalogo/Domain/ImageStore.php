<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * Dónde viven los archivos de las imágenes (COMR-274): hoy un volumen servido por nginx; mañana, almacenamiento de
 * objetos, sin cambiar a quien lo usa. Genera los tamaños (miniatura, listado y detalle) y los nombra con una huella.
 */
interface ImageStore
{
    /**
     * Lee el archivo, comprueba que es una imagen JPEG, PNG o WebP y guarda sus tamaños.
     *
     * @throws InvalidProductImage si no es una imagen válida
     */
    public function store(string $sourcePath): StoredImage;

    /** Borra todos los tamaños de una imagen. */
    public function delete(string $file): void;
}
