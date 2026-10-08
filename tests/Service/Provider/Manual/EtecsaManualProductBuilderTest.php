<?php

namespace App\Tests\Service\Provider\Manual;

use App\Enums\CommunicationProviderEnum;
use App\Exception\MyCurrentException;
use App\Service\Provider\Manual\EtecsaManualProductBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Service\Provider\Manual\EtecsaManualProductBuilder
 */
class EtecsaManualProductBuilderTest extends TestCase
{
    private EtecsaManualProductBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new EtecsaManualProductBuilder();
    }

    private function baseValues(array $overrides = []): array
    {
        return array_merge([
            'packageId' => '4501',
            'packageType' => 'RECARGA',
            'price' => 12.5,
            'description' => 'Recarga 500 CUP',
            'enabled' => true,
        ], $overrides);
    }

    public function testGetCodeReturnsEtecsa(): void
    {
        $this->assertSame(CommunicationProviderEnum::ETECSA, $this->builder->getCode());
    }

    public function testBuildYieldsExactlyOneProduct(): void
    {
        $products = iterator_to_array($this->builder->build($this->baseValues()));

        $this->assertCount(1, $products);
    }

    public function testProductShapeMatchesEtecsaSyncConvention(): void
    {
        $products = iterator_to_array($this->builder->build($this->baseValues()));
        $product = $products[0];

        $this->assertSame('4501', $product->externalId);
        $this->assertSame('RECARGA', $product->productTypeRaw);
        $this->assertSame(12.5, $product->wholesalePrice);
        $this->assertSame('Recarga 500 CUP', $product->description);
        $this->assertNull($product->destinationAmount);
        $this->assertNull($product->destinationUnit);
        $this->assertNull($product->priceCurrency);
        $this->assertTrue($product->enabled);
        $this->assertTrue($product->isMobileOrInternetService);
        $this->assertSame(['name' => 'MOBILE', 'subservice' => ['name' => 'AIRTIME']], $product->service);
        $this->assertSame([], $product->requiredIdentifierFields);
    }

    public function testMissingPackageIdThrowsDomainException(): void
    {
        $values = $this->baseValues();
        unset($values['packageId']);

        $this->expectException(MyCurrentException::class);

        iterator_to_array($this->builder->build($values));
    }

    public function testMissingPriceThrowsDomainException(): void
    {
        $values = $this->baseValues();
        unset($values['price']);

        $this->expectException(MyCurrentException::class);

        iterator_to_array($this->builder->build($values));
    }

    public function testFormSchemaExposesPackageIdAndPrice(): void
    {
        $keys = array_map(fn ($f) => $f->key, $this->builder->getFormSchema());

        $this->assertContains('packageId', $keys);
        $this->assertContains('price', $keys);
    }
}
