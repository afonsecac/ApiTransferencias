<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Infrastructure;

use App\Entity\CommunicationPackage;
use App\Market\Catalogo\Domain\Category;
use App\Market\Catalogo\Domain\CategoryRepository;
use App\Market\Catalogo\Domain\ProductImage;
use App\Market\Catalogo\Domain\ProductGallery;
use App\Market\Catalogo\Domain\ProductImageRepository;
use App\Market\Catalogo\Domain\ProductRepository;
use App\Market\Catalogo\Domain\Relevance;
use App\Shared\Domain\Slug;
use App\Market\Catalogo\Infrastructure\Http\ChangeProductStatusDto;
use App\Market\Catalogo\Infrastructure\Http\CreateProductDto;
use App\Market\Catalogo\Infrastructure\Http\ProductController;
use App\Market\Catalogo\Infrastructure\Http\ProductPackageController;
use App\Market\Catalogo\Infrastructure\Http\PublishProductPackageDto;
use App\Service\Catalog\MarketPackageEnricher;
use App\Service\Pricing\PackageOfferSourceEnum;
use App\Service\Pricing\ResolvedPackageOffer;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * COMR-275: los datos propios del mercado (unidad, peso, imágenes, relevancia…) se leen en vivo del producto y se añaden al
 * paquete del catálogo del comprador. Un dato sin valor no aparece, y un paquete de comunicaciones no cambia.
 *
 * @covers \App\Market\Catalogo\Infrastructure\Http\MarketPackageFacts
 */
final class MarketPackageFactsFunctionalTest extends ProviderFunctionalTestCase
{
    private const TODAY = '2026-10-15';

    private function enricher(): MarketPackageEnricher
    {
        return self::getContainer()->get(MarketPackageEnricher::class);
    }

    /** Un producto de 1 kg, ya publicado y ofrecido como paquete a 12.50 USD; devuelve su paquete con el precio resuelto. */
    /** @param list<array{name: array<string, string>, value: array<string, string>}> $attributes */
    private function offered(string $sku = 'ARR-001', array $attributes = []): CommunicationPackage
    {
        $this->authenticateAsAdmin($this->createAdminUser());
        $category = Category::create(new Slug('cat-' . strtolower($sku)), null, 'Alimentos', null);
        self::getContainer()->get(CategoryRepository::class)->save($category);
        $products = self::getContainer()->get(ProductController::class);
        $created = json_decode((string) $products->create(new CreateProductDto(
            categoryIds: [(int) $category->id()],
            sku: $sku,
            texts: ['es' => ['name' => 'Arroz blanco', 'description' => 'Bolsa de 1 kg'], 'en' => ['name' => 'White rice']],
            service: 'Gift Cards',
            subservice: 'Retail',
            area: 'Supermarket',
            contentValue: 1.0,
            contentUnit: 'kg',
            deliveryKind: 'weight',
            deliveryUnit: 'kg',
            attributes: $attributes,
        ))->getContent(), true);
        $products->changeStatus($created['id'], new ChangeProductStatusDto('published'));
        self::getContainer()->get(ProductPackageController::class)->publish($created['id'], new PublishProductPackageDto('12.50', 'USD'));
        $package = $this->em->getRepository(CommunicationPackage::class)->findOneBy(['marketProductId' => $created['id']]);
        $package->setResolvedOffer(new ResolvedPackageOffer($package, 12.5, 'USD', PackageOfferSourceEnum::DEFAULT_CONTRACT));

        return $package;
    }

    /**
     * @return array<string, mixed>
     */
    private function market(CommunicationPackage $package): array
    {
        $this->enricher()->enrich([$package], new \DateTimeImmutable(self::TODAY));

        return $package->getMarket() ?? [];
    }

    public function testItAddsTheUnitWeightTextsCategoriesAndPricePerPound(): void
    {
        $market = $this->market($this->offered());

        $this->assertSame('ARR-001', $market['sku']);
        $this->assertSame(['value' => 1.0, 'unit' => 'kg'], $market['content']);
        $this->assertSame(['g' => 1000.0, 'kg' => 1.0, 'lb' => 2.205], $market['weight']);
        $this->assertSame(5.67, $market['pricePerLb']);
        $this->assertSame(['es' => ['name' => 'Arroz blanco', 'description' => 'Bolsa de 1 kg'], 'en' => ['name' => 'White rice']], $market['texts']);
        $this->assertSame(['Alimentos'], $market['categories']);
    }

    public function testADataWithoutValueDoesNotAppear(): void
    {
        $market = $this->market($this->offered());

        foreach (['volume', 'unitWeight', 'unitVolume', 'dimensions', 'brand', 'relevance', 'images', 'barcode', 'attributes'] as $absent) {
            $this->assertArrayNotHasKey($absent, $market);
        }
    }

    public function testTheAttributesComeWithTheirTextInEveryLanguage(): void
    {
        $attributes = [
            ['name' => ['es' => 'Presentación', 'en' => 'Packaging'], 'value' => ['es' => 'Bolsa', 'en' => 'Bag']],
            ['name' => ['es' => 'Origen'], 'value' => ['es' => 'España']],
        ];

        $market = $this->market($this->offered('ARR-ATTR', $attributes));

        $this->assertSame($attributes, $market['attributes']);
    }

    public function testRelevanceAppearsOnlyWhileItIsInForce(): void
    {
        $package = $this->offered();
        $repository = self::getContainer()->get(ProductRepository::class);
        $product = $repository->findById((int) $package->getMarketProductId());

        $product->setRelevance(Relevance::of(2, new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-10-31')));
        $repository->save($product);
        $this->assertSame(['level' => 2, 'from' => '2026-10-01', 'until' => '2026-10-31'], $this->market($package)['relevance']);

        $product->setRelevance(Relevance::of(2, new \DateTimeImmutable('2026-11-01'), null));
        $repository->save($product);
        $this->assertArrayNotHasKey('relevance', $this->market($package));
    }

    public function testItListsTheImagesInOrderWithTheirUrlsAndTheReferenceFlag(): void
    {
        $package = $this->offered();
        $images = self::getContainer()->get(ProductImageRepository::class);
        $productId = (int) $package->getMarketProductId();
        $gallery = ProductGallery::of([], 8);
        $gallery->add(ProductImage::create($productId, str_repeat('a', 40), 'frente.png', 'image/png', 100, 10, 10));
        $gallery->add(ProductImage::create($productId, str_repeat('b', 40), 'etiqueta.png', 'image/png', 100, 10, 10));
        $images->saveAll($gallery->images());

        $list = $this->market($package)['images'];

        $this->assertCount(2, $list);
        $this->assertTrue($list[0]['isReference']);
        $this->assertArrayNotHasKey('isReference', $list[1], 'Solo la de referencia lleva la marca.');
        $this->assertArrayHasKey('urls', $list[0]);
        $this->assertStringContainsString(str_repeat('a', 40), $list[0]['urls']['400'] ?? reset($list[0]['urls']));
    }

    public function testAPackageOfCommunicationsIsLeftAsItIs(): void
    {
        $package = (new CommunicationPackage())->setName('Recarga')->setDescription('Recarga')->setDestinationAmount(10.0)->setDestinationCurrency('USD');

        $this->enricher()->enrich([$package], new \DateTimeImmutable(self::TODAY));

        $this->assertNull($package->getMarket());
    }

    public function testTheJsonOmitsMarketWhenThereIsNoneAndIncludesItWhenThereIs(): void
    {
        $serializer = self::getContainer()->get('serializer');
        \assert($serializer instanceof SerializerInterface);
        $comms = (new CommunicationPackage())->setName('Recarga')->setDescription('Recarga')->setDestinationAmount(10.0)->setDestinationCurrency('USD');
        $this->em->persist($comms);
        $this->em->flush();
        $market = $this->offered();
        $this->enricher()->enrich([$market], new \DateTimeImmutable(self::TODAY));

        $plain = json_decode($serializer->serialize($comms, 'json', ['groups' => ['comPackage:read']]), true);
        $withMarket = json_decode($serializer->serialize($market, 'json', ['groups' => ['comPackage:read']]), true);

        $this->assertArrayNotHasKey('market', $plain);
        $this->assertArrayHasKey('knowMore', $plain, 'El resto de campos nulos de comunicaciones siguen apareciendo como hoy.');
        $this->assertSame('ARR-001', $withMarket['market']['sku']);
    }
}
