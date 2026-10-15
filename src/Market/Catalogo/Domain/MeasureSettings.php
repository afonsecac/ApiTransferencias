<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * La configuración de las medidas del catálogo (COMR-281): en qué unidad se guardan el peso y el volumen (la unidad base) y el
 * divisor del peso volumétrico. Quién lo implementa es la infraestructura, que sabe de dónde salen los valores. No hay valores
 * por defecto: si la configuración falta o es inválida, el sistema falla en lugar de interpretar datos guardados en otra base.
 */
interface MeasureSettings
{
    /** La unidad en que se guardan los pesos; siempre una unidad de peso (hoy el kilogramo). */
    public function weightBase(): MeasureUnit;

    /** La unidad en que se guardan los volúmenes; siempre una unidad de volumen (hoy el litro). */
    public function volumeBase(): MeasureUnit;

    /** Centímetros cúbicos por kilogramo del peso volumétrico (L×A×H ÷ divisor); positivo (hoy 6000, el estándar IATA). */
    public function volumetricDivisor(): int;
}
