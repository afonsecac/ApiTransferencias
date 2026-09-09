<?php

namespace App\Tests\Service\Provider\Manual;

use App\Enums\CommunicationProviderEnum;
use App\Exception\MyCurrentException;
use App\Provider\Contract\ProviderProductDto;
use App\Service\Provider\Manual\CsqManualProductBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Service\Provider\Manual\CsqManualProductBuilder
 */
class CsqManualProductBuilderTest extends TestCase
{
    private CsqManualProductBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new CsqManualProductBuilder();
    }

    private function baseValues(array $overrides = []): array
    {
        return array_merge([
            'articleId' => '8142',
            'name' => 'Cubacel',
            'fromAmount' => 600.0,
            'toAmount' => 1250.0,
            'step' => 25.0,
            'exchangeRate' => 22.727,
            'destinationUnit' => 'CUP',
            'priceCurrency' => 'USD',
            'topupType' => 'Bundles',
            'requiredIdentifierField' => 'phoneNumber',
            'description' => 'Promo 25GB + Datos Ilimitados',
            'enabled' => true,
        ], $overrides);
    }

    public function testGetCodeReturnsCsq(): void
    {
        $this->assertSame(CommunicationProviderEnum::CSQ, $this->builder->getCode());
    }

    public function testBuildExpandsRangeIntoOneProductPerAmount(): void
    {
        $products = iterator_to_array($this->builder->build($this->baseValues()));

        // 600..1250 step 25 => (1250-600)/25 + 1 = 27
        $this->assertCount(27, $products);
        $this->assertContainsOnlyInstancesOf(ProviderProductDto::class, $products);
    }

    public function testFirstAndLastProductMatchPromotionEmailNumbers(): void
    {
        $products = iterator_to_array($this->builder->build($this->baseValues()));

        $first = $products[0];
        $last = $products[array_key_last($products)];

        $this->assertSame('8142-600', $first->externalId);
        $this->assertSame(600.0, $first->destinationAmount);
        $this->assertSame(26.4, $first->wholesalePrice);

        $this->assertSame('8142-1250', $last->externalId);
        $this->assertSame(1250.0, $last->destinationAmount);
        $this->assertSame(55.0, $last->wholesalePrice);
    }

    public function testProductShapeMatchesCsqSyncConvention(): void
    {
        $products = iterator_to_array($this->builder->build($this->baseValues()));
        $product = $products[0];

        $this->assertSame('Cubacel - 600 CUP', $product->name);
        $this->assertSame('USD', $product->priceCurrency);
        $this->assertSame('CUP', $product->destinationUnit);
        $this->assertTrue($product->enabled);
        $this->assertTrue($product->isMobileOrInternetService);
        $this->assertSame(['name' => 'Mobile', 'subservice' => ['name' => 'Bundle']], $product->service);
        $this->assertSame([['phoneNumber']], $product->requiredIdentifierFields);
    }

    public function testTopupTypeDataMapsToUtilitiesInternet(): void
    {
        $products = iterator_to_array($this->builder->build($this->baseValues([
            'topupType' => 'Data',
            'requiredIdentifierField' => 'accountIdentifier',
        ])));

        $product = $products[0];
        $this->assertSame(['name' => 'Utilities', 'subservice' => ['name' => 'Internet']], $product->service);
        $this->assertSame([['accountIdentifier']], $product->requiredIdentifierFields);
    }

    public function testValidFromAndValidToAreParsedFromDates(): void
    {
        $products = iterator_to_array($this->builder->build($this->baseValues([
            'initialDate' => '2026-09-07T00:00:00+00:00',
            'endDateAt' => '2026-09-14T00:00:00+00:00',
        ])));

        $product = $products[0];
        $this->assertEquals(new \DateTimeImmutable('2026-09-07T00:00:00+00:00'), $product->validFrom);
        $this->assertEquals(new \DateTimeImmutable('2026-09-14T00:00:00+00:00'), $product->validTo);
    }

    public function testSingleAmountRangeYieldsExactlyOneProduct(): void
    {
        $products = iterator_to_array($this->builder->build($this->baseValues([
            'fromAmount' => 600.0,
            'toAmount' => 600.0,
            'step' => 25.0,
        ])));

        $this->assertCount(1, $products);
    }

    public function testInvalidRangeThrowsDomainException(): void
    {
        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('rango');

        iterator_to_array($this->builder->build($this->baseValues([
            'fromAmount' => 1250.0,
            'toAmount' => 600.0,
        ])));
    }

    public function testNonPositiveStepThrowsDomainException(): void
    {
        $this->expectException(MyCurrentException::class);

        iterator_to_array($this->builder->build($this->baseValues(['step' => 0.0])));
    }

    public function testRangeGeneratingTooManyProductsIsRejected(): void
    {
        $this->expectException(MyCurrentException::class);

        iterator_to_array($this->builder->build($this->baseValues([
            'fromAmount' => 0.0,
            'toAmount' => 1000.0,
            'step' => 1.0, // 1001 productos > MAX_BATCH_SIZE
        ])));
    }

    public function testUnknownTopupTypeIsRejected(): void
    {
        $this->expectException(MyCurrentException::class);

        iterator_to_array($this->builder->build($this->baseValues(['topupType' => 'GiftCard'])));
    }

    public function testMissingRequiredFieldThrowsDomainException(): void
    {
        $values = $this->baseValues();
        unset($values['articleId']);

        $this->expectException(MyCurrentException::class);

        iterator_to_array($this->builder->build($values));
    }

    public function testFormSchemaExposesArticleIdAndRangeFields(): void
    {
        $keys = array_map(fn ($f) => $f->key, $this->builder->getFormSchema());

        $this->assertContains('articleId', $keys);
        $this->assertContains('fromAmount', $keys);
        $this->assertContains('toAmount', $keys);
        $this->assertContains('step', $keys);
        $this->assertContains('exchangeRate', $keys);
    }
}
