<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Domain;

use App\Market\Catalogo\Domain\InvalidSeller;
use App\Market\Catalogo\Domain\PlatformSellerProtected;
use App\Market\Catalogo\Domain\Seller;
use App\Shared\Domain\Slug;
use PHPUnit\Framework\TestCase;

/**
 * COMR-103: el vendedor cuelga de una unidad organizativa y de uno de sus centros de costo.
 *
 * @covers \App\Market\Catalogo\Domain\Seller
 * @covers \App\Market\Catalogo\Domain\Slug
 */
final class SellerTest extends TestCase
{
    private function seller(string $name = 'Garajito', string $slug = 'garajito'): Seller
    {
        return Seller::create(7, 3, new Slug($slug), $name);
    }

    public function testItIsCreatedActiveAndNeverAsThePlatform(): void
    {
        $seller = $this->seller('  Garajito  ');

        $this->assertSame('Garajito', $seller->name());
        $this->assertSame('garajito', $seller->slug()->value());
        $this->assertSame(7, $seller->orgUnitId());
        $this->assertSame(3, $seller->costCenterId());
        $this->assertTrue($seller->isActive());
        $this->assertFalse($seller->isPlatform());
    }

    public function testItNeedsAName(): void
    {
        $this->expectException(InvalidSeller::class);

        $this->seller('   ');
    }

    public function testTheNameHasALimit(): void
    {
        $this->expectException(InvalidSeller::class);

        $this->seller(str_repeat('N', 161));
    }

    public function testItCanBeRenamedAndKeepsItsSlug(): void
    {
        $seller = $this->seller();

        $seller->rename('Garajito Foods');

        $this->assertSame('Garajito Foods', $seller->name());
        $this->assertSame('garajito', $seller->slug()->value());
    }

    public function testItCanChargeAnotherCostCenter(): void
    {
        $seller = $this->seller();

        $seller->chargeTo(9);

        $this->assertSame(9, $seller->costCenterId());
    }

    public function testItCanBeDeactivatedAndActivatedAgain(): void
    {
        $seller = $this->seller();

        $seller->deactivate();
        $this->assertFalse($seller->isActive());

        $seller->activate();
        $this->assertTrue($seller->isActive());
    }

    public function testThePlatformSellerIsProtectedFromBeingDeactivated(): void
    {
        $platform = Seller::platform(1, 1, new Slug('plataforma'), 'Plataforma');

        $this->assertTrue($platform->isPlatform());

        $this->expectException(PlatformSellerProtected::class);

        $platform->deactivate();
    }

    public function testThePlatformSellerCanStillBeRenamed(): void
    {
        $platform = Seller::platform(1, 1, new Slug('plataforma'), 'Plataforma');

        $platform->rename('Comremit');

        $this->assertSame('Comremit', $platform->name());
        $this->assertTrue($platform->isActive());
    }

    public function testASlugIsLowercaseAsciiWithHyphens(): void
    {
        $this->assertSame('garajito-co', Slug::fromText('Garajito & Co.')->value());
        $this->assertSame('la-jabalina', Slug::fromText('  La   Jabalína ')->value());
        $this->assertSame('seller-2', (new Slug('seller-2'))->value());
    }

    /**
     * @dataProvider invalidSlugs
     */
    public function testAnInvalidSlugIsRejected(string $slug): void
    {
        $this->expectException(\App\Shared\Domain\InvalidSlug::class);

        new Slug($slug);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidSlugs(): iterable
    {
        yield 'vacío' => [''];
        yield 'mayúsculas' => ['Garajito'];
        yield 'espacios' => ['la jabalina'];
        yield 'guion al inicio' => ['-garajito'];
        yield 'guion doble' => ['a--b'];
        yield 'demasiado largo' => [str_repeat('a', 61)];
    }

    public function testATextWithNothingUsableCannotBecomeASlug(): void
    {
        $this->expectException(\App\Shared\Domain\InvalidSlug::class);

        Slug::fromText('¿¡!?');
    }
}
