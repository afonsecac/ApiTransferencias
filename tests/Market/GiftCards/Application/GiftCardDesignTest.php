<?php

declare(strict_types=1);

namespace App\Tests\Market\GiftCards\Application;

use App\Market\GiftCards\Application\GiftCardDesign;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Market\GiftCards\Application\GiftCardDesign
 */
final class GiftCardDesignTest extends TestCase
{
    public function testItDrawsTheCardAsAValidSvgWithTheProductAndTheValue(): void
    {
        $svg = (new GiftCardDesign())->svg('Tarjetas de Regalo Digitales', '105.00 USD');

        $xml = simplexml_load_string($svg);
        $this->assertNotFalse($xml, 'El SVG debe ser XML válido');
        $this->assertSame('svg', $xml->getName());
        $this->assertStringContainsString('GIFT CARD', $svg);
        $this->assertStringContainsString('Tarjetas de Regalo Digitales', $svg);
        $this->assertStringContainsString('105.00 USD', $svg);
    }

    public function testItNeverShowsACardNumberNorAPin(): void
    {
        $svg = (new GiftCardDesign())->svg('Tarjeta', '50.00 USD');

        $this->assertStringContainsString('•••• •••• •••• ••••', $svg);
        $this->assertDoesNotMatchRegularExpression('/\d{4}\s?\d{4}\s?\d{4}\s?\d{4}/', $svg);
    }

    public function testItEscapesTheTextSoTheProductNameCannotInjectMarkup(): void
    {
        $svg = (new GiftCardDesign())->svg('<script>alert(1)</script> & "co"', '1 USD');

        $this->assertNotFalse(simplexml_load_string($svg));
        $this->assertStringNotContainsString('<script>', $svg);
        $this->assertStringContainsString('&lt;script&gt;', $svg);
    }

    public function testALongNameIsShortenedWithAnEllipsis(): void
    {
        $svg = (new GiftCardDesign())->svg(str_repeat('Nombre ', 12), '1 USD');

        $this->assertStringContainsString('…', $svg);
        $this->assertStringNotContainsString(str_repeat('Nombre ', 12), $svg);
    }

    public function testTheSameInputGivesTheSameSvg(): void
    {
        $design = new GiftCardDesign();

        $this->assertSame($design->svg('A', '1 USD'), $design->svg('A', '1 USD'));
        $this->assertNotSame($design->svg('A', '1 USD'), $design->svg('A', '2 USD'));
    }

    public function testItUsesNoCssSoItRendersEvenWhenOpenedDirectlyUnderAStrictCsp(): void
    {
        $svg = (new GiftCardDesign())->svg('A', '1 USD');

        $this->assertStringNotContainsString('<style', $svg);
        $this->assertStringNotContainsString('style="', $svg);
    }
}
