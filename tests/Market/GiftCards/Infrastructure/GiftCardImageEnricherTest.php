<?php

declare(strict_types=1);

namespace App\Tests\Market\GiftCards\Infrastructure;

use App\Entity\CommunicationPackage;
use App\Market\GiftCards\Application\GiftCardDesign;
use App\Market\GiftCards\Domain\GiftCardProduct;
use App\Market\GiftCards\Infrastructure\Catalog\GiftCardImageEnricher;
use App\Service\Catalog\MarketPackageEnricher;
use App\Service\Pricing\PackageOfferSourceEnum;
use App\Service\Pricing\ResolvedPackageOffer;
use App\Tests\Market\GiftCards\Application\FakeGiftCardProducts;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Market\GiftCards\Infrastructure\Catalog\GiftCardImageEnricher
 */
final class GiftCardImageEnricherTest extends TestCase
{
    private string $dir;
    private FakeGiftCardProducts $products;
    private int $innerCalls = 0;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/gc-design-' . bin2hex(random_bytes(4));
        $this->products = new FakeGiftCardProducts();
        $this->products->add(new GiftCardProduct(196, 'Tarjetas de Regalo Digitales', true, false, 'USD', 10000, 100000, []));
        $this->products->add(new GiftCardProduct(108, 'Arroz largo', false, true, 'USD', 0, null, []));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->dir);
    }

    private function enricher(string $baseUrl = ''): GiftCardImageEnricher
    {
        $inner = new class($this->innerCalls) implements MarketPackageEnricher {
            public function __construct(private int &$calls) {}

            public function enrich(iterable $packages, \DateTimeImmutable $now): void
            {
                ++$this->calls;
                foreach ($packages as $package) {
                    $package->setMarket(['sku' => 'X', 'images' => [['kind' => 'etiqueta', 'urls' => ['thumb' => '/market-images/a.webp']]]]);
                }
            }
        };

        return new GiftCardImageEnricher($inner, $this->products, new GiftCardDesign(), $this->dir, '/market-images', $baseUrl);
    }

    private function package(?int $productId, float $price = 105.0, string $name = 'Tarjetas de Regalo Digitales'): CommunicationPackage
    {
        $package = (new CommunicationPackage())->setName($name)->setMarketProductId($productId);
        $package->setResolvedOffer(new ResolvedPackageOffer($package, $price, 'USD', PackageOfferSourceEnum::PRODUCT_MAX));

        return $package;
    }

    public function testItDelegatesToTheDecoratedEnricherFirst(): void
    {
        $package = $this->package(196);

        $this->enricher()->enrich([$package], new \DateTimeImmutable());

        $this->assertSame(1, $this->innerCalls);
        $this->assertSame('etiqueta', $package->getMarket()['images'][0]['kind']);
    }

    public function testAGiftCardPackageGetsItsGeneratedDesignAfterTheUploadedImages(): void
    {
        $package = $this->package(196);

        $this->enricher()->enrich([$package], new \DateTimeImmutable());

        $images = $package->getMarket()['images'];
        $this->assertCount(2, $images);
        $this->assertSame('tarjeta', $images[1]['kind']);
        $this->assertTrue($images[1]['isGenerated']);
        $url = $images[1]['urls']['detail'];
        $this->assertMatchesRegularExpression('#^/market-images/cards/[0-9a-f]{2}/[0-9a-f]{40}\.svg$#', $url);
        $this->assertSame($url, $images[1]['urls']['thumb']);
        $this->assertSame($url, $images[1]['urls']['list']);
        $file = $this->dir . substr($url, strlen('/market-images'));
        $this->assertFileExists($file);
        $this->assertStringContainsString('105.00 USD', (string) file_get_contents($file));
    }

    public function testThePackageWithoutUploadedImagesStillGetsTheDesign(): void
    {
        $inner = new class implements MarketPackageEnricher {
            public function enrich(iterable $packages, \DateTimeImmutable $now): void
            {
                foreach ($packages as $package) {
                    $package->setMarket(['sku' => 'X']);
                }
            }
        };
        $package = $this->package(196);

        (new GiftCardImageEnricher($inner, $this->products, new GiftCardDesign(), $this->dir, '/market-images', ''))->enrich([$package], new \DateTimeImmutable());

        $this->assertCount(1, $package->getMarket()['images']);
    }

    public function testTheUrlChangesWhenThePriceChangesAndItIsStableOtherwise(): void
    {
        $a = $this->package(196, 105.0);
        $b = $this->package(196, 105.0);
        $c = $this->package(196, 120.0);

        $this->enricher()->enrich([$a, $b, $c], new \DateTimeImmutable());

        $url = static fn (CommunicationPackage $p): string => $p->getMarket()['images'][1]['urls']['detail'];
        $this->assertSame($url($a), $url($b));
        $this->assertNotSame($url($a), $url($c));
    }

    public function testItUsesTheConfiguredBaseUrl(): void
    {
        $package = $this->package(196);

        $this->enricher('https://api.example.test/')->enrich([$package], new \DateTimeImmutable());

        $this->assertStringStartsWith('https://api.example.test/market-images/cards/', $package->getMarket()['images'][1]['urls']['detail']);
    }

    public function testOtherProductsAndPackagesWithoutProductAreLeftAlone(): void
    {
        $rice = $this->package(108, 10.8, 'Arroz largo');
        $plain = $this->package(null, 9.85, 'Cubacel 250 CUP');

        $this->enricher()->enrich([$rice, $plain], new \DateTimeImmutable());

        $this->assertCount(1, $rice->getMarket()['images']);
        $this->assertCount(1, $plain->getMarket()['images']);
        $this->assertDirectoryDoesNotExist($this->dir);
    }

    public function testAPackageWithoutAResolvedPriceGetsNoDesign(): void
    {
        $package = (new CommunicationPackage())->setName('Tarjetas')->setMarketProductId(196);

        $this->enricher()->enrich([$package], new \DateTimeImmutable());

        $this->assertCount(1, $package->getMarket()['images']);
    }
}
