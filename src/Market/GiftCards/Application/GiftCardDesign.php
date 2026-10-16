<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Application;

/**
 * El diseño de la gift card como SVG, con el mismo aspecto que la tarjeta digital del dashboard (degradado, chip, «GIFT CARD»,
 * nombre del producto, número y valor), para el catálogo del comprador. Es el diseño del producto, no el de una tarjeta emitida:
 * el número va enmascarado y nunca lleva el PIN. Sin CSS (solo atributos de presentación) para que se vea también al abrirlo
 * directamente bajo una política de contenido estricta. La misma entrada da siempre el mismo SVG.
 */
final class GiftCardDesign
{
    private const WIDTH = 480;
    private const HEIGHT = 303;
    private const MAX_NAME = 30;
    private const MASKED_NUMBER = '•••• •••• •••• ••••';

    public function svg(string $productName, string $valueLabel): string
    {
        $name = $this->escape($this->shorten($productName));
        $value = $this->escape($valueLabel);
        $number = self::MASKED_NUMBER;
        $width = self::WIDTH;
        $height = self::HEIGHT;

        return <<<SVG
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$height}" viewBox="0 0 {$width} {$height}" role="img" aria-label="Gift card {$name}">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#3730a3"/>
      <stop offset="0.5" stop-color="#6d28d9"/>
      <stop offset="1" stop-color="#c026d3"/>
    </linearGradient>
  </defs>
  <rect width="{$width}" height="{$height}" rx="24" fill="url(#bg)"/>
  <text x="28" y="48" fill="#ffffff" font-family="Arial, Helvetica, sans-serif" font-size="16" font-weight="700" letter-spacing="5">GIFT CARD</text>
  <text x="452" y="48" fill="#ddd6fe" font-family="Arial, Helvetica, sans-serif" font-size="13" text-anchor="end">{$name}</text>
  <rect x="28" y="78" width="52" height="38" rx="7" fill="#fcd34d"/>
  <text x="28" y="172" fill="#ffffff" font-family="'Courier New', monospace" font-size="26" letter-spacing="3">{$number}</text>
  <text x="452" y="262" fill="#ffffff" font-family="Arial, Helvetica, sans-serif" font-size="34" font-weight="700" text-anchor="end">{$value}</text>
</svg>
SVG;
    }

    private function shorten(string $name): string
    {
        $name = trim($name);

        return mb_strlen($name) > self::MAX_NAME ? rtrim(mb_substr($name, 0, self::MAX_NAME - 1)) . '…' : $name;
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
