<?php

namespace App\Tests\Entity;

use App\Entity\CommunicationPackage;
use App\Service\Pricing\PackageOfferSourceEnum;
use App\Service\Pricing\ResolvedPackageOffer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Entity\CommunicationPackage
 */
class CommunicationPackageTest extends TestCase
{
    private function package(float $amount = 500.0, string $currency = 'CUP'): CommunicationPackage
    {
        return (new CommunicationPackage())
            ->setName('Paquete de prueba')
            ->setDescription('Paquete de prueba')
            ->setDestinationAmount($amount)
            ->setDestinationCurrency($currency);
    }

    public function testGetDestinationIsComputedDirectlyFromOrmFields(): void
    {
        $package = $this->package(500.0, 'CUP');

        $this->assertSame([
            'amount' => 500.0,
            'unit' => 'CUP',
            'unit_type' => 'CURRENCY',
        ], $package->getDestination());
    }

    public function testGetAmountAndCurrencyAreNullUntilResolvedOfferIsInjected(): void
    {
        $package = $this->package();

        $this->assertNull($package->getAmount());
        $this->assertNull($package->getCurrency());
    }

    public function testGetAmountAndCurrencyReadFromResolvedOfferOnceInjected(): void
    {
        $package = $this->package();
        $offer = new ResolvedPackageOffer(
            package: $package,
            price: 12.5,
            currency: 'USD',
            source: PackageOfferSourceEnum::PRODUCT_MAX,
        );

        $package->setResolvedOffer($offer);

        $this->assertSame(12.5, $package->getAmount());
        $this->assertSame('USD', $package->getCurrency());
        $this->assertSame($offer, $package->getResolvedOffer());
    }

    public function testLifecycleCallbacksStampTimestamps(): void
    {
        $package = $this->package();

        $this->assertNull($package->getCreatedAt());
        $this->assertNull($package->getUpdatedAt());

        $package->onCreated();
        $package->onUpdated();

        $this->assertInstanceOf(\DateTimeImmutable::class, $package->getCreatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $package->getUpdatedAt());
    }
}
