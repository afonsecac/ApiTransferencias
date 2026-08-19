<?php

namespace App\Tests\Service\Pricing;

use App\Entity\CommunicationPackage;
use App\Repository\CommunicationPackageRepository;
use App\Service\Pricing\BenefitOperationResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Service\Pricing\BenefitOperationResolver
 */
class BenefitOperationResolverTest extends TestCase
{
    private CommunicationPackageRepository&MockObject $packageRepository;
    private BenefitOperationResolver $resolver;

    protected function setUp(): void
    {
        $this->packageRepository = $this->createMock(CommunicationPackageRepository::class);
        $this->resolver = new BenefitOperationResolver($this->packageRepository);
    }

    private function package(float $amount = 600.0, string $currency = 'CUP'): CommunicationPackage
    {
        return (new CommunicationPackage())
            ->setName('P')->setDescription('P')
            ->setDestinationAmount($amount)->setDestinationCurrency($currency);
    }

    public function testMultiplyForACreditsCurrencyBenefitUsesTheOwnDestinationAmountAsBaselineWithoutQueryingTheRepository(): void
    {
        $package = $this->package(600.0, 'CUP')->setBenefits([[
            'type' => 'CREDITS', 'unit' => 'CUP', 'unit_type' => 'CURRENCY',
            'operation' => 'MULTIPLY', 'value' => 6,
        ]]);

        $this->packageRepository->expects($this->never())->method('findByDestination');

        $benefit = $this->resolver->resolve($package)[0];

        $this->assertSame(600, $benefit['amount']['base']);
        $this->assertSame(3000, $benefit['amount']['promotion_bonus']);
        $this->assertSame(3600, $benefit['amount']['total_excluding_tax']);
        $this->assertSame(3600, $benefit['amount']['total_including_tax']);
    }

    public function testAddForANonCurrencyBenefitUsesTheRegularEquivalentPackagesBenefitAsBaseline(): void
    {
        $package = $this->package(600.0, 'CUP')->setBenefits([[
            'type' => 'DATA', 'unit' => 'GB', 'unit_type' => 'DATA',
            'operation' => 'ADD', 'value' => 20,
        ]]);

        $regularPackage = $this->package(600.0, 'CUP')->setBenefits([[
            'type' => 'DATA', 'unit' => 'GB', 'unit_type' => 'DATA',
            'amount' => ['base' => 5],
        ]]);
        $this->packageRepository->expects($this->once())
            ->method('findByDestination')
            ->with(600.0, 'CUP')
            ->willReturn($regularPackage);

        $benefit = $this->resolver->resolve($package)[0];

        $this->assertSame(5, $benefit['amount']['base']);
        $this->assertSame(20, $benefit['amount']['promotion_bonus']);
        $this->assertSame(25, $benefit['amount']['total_excluding_tax']);
    }

    public function testSetIgnoresAnyBaselineEvenWhenARegularEquivalentExists(): void
    {
        $package = $this->package(600.0, 'CUP')->setBenefits([[
            'type' => 'DATA', 'unit' => 'GB', 'unit_type' => 'DATA',
            'operation' => 'SET', 'value' => 100,
        ]]);

        $regularPackage = $this->package(600.0, 'CUP')->setBenefits([[
            'type' => 'DATA', 'unit' => 'GB', 'unit_type' => 'DATA',
            'amount' => ['base' => 5],
        ]]);
        $this->packageRepository->method('findByDestination')->willReturn($regularPackage);

        $benefit = $this->resolver->resolve($package)[0];

        $this->assertSame(100, $benefit['amount']['base']);
        $this->assertSame(0, $benefit['amount']['promotion_bonus']);
    }

    public function testFallsBackToZeroBaselineWhenNoRegularEquivalentPackageExists(): void
    {
        $package = $this->package(600.0, 'CUP')->setBenefits([[
            'type' => 'DATA', 'unit' => 'GB', 'unit_type' => 'DATA',
            'operation' => 'ADD', 'value' => 20,
        ]]);
        $this->packageRepository->method('findByDestination')->willReturn(null);

        $benefit = $this->resolver->resolve($package)[0];

        $this->assertSame(0, $benefit['amount']['base']);
        $this->assertSame(20, $benefit['amount']['promotion_bonus']);
    }

    public function testFallsBackToZeroBaselineWhenTheRegularPackageHasNoMatchingBenefitTypeOrUnit(): void
    {
        $package = $this->package(600.0, 'CUP')->setBenefits([[
            'type' => 'DATA', 'unit' => 'GB', 'unit_type' => 'DATA',
            'operation' => 'ADD', 'value' => 20,
        ]]);

        $regularPackage = $this->package(600.0, 'CUP')->setBenefits([[
            'type' => 'MINUTES', 'unit' => 'MIN', 'unit_type' => 'MINUTES',
            'amount' => ['base' => 100],
        ]]);
        $this->packageRepository->method('findByDestination')->willReturn($regularPackage);

        $benefit = $this->resolver->resolve($package)[0];

        $this->assertSame(0, $benefit['amount']['base']);
    }

    public function testLeavesABenefitUntouchedWhenNoOperationIsSpecified(): void
    {
        $package = $this->package()->setBenefits([[
            'type' => 'DATA', 'unit' => 'GB', 'unit_type' => 'DATA',
            'amount' => ['base' => 5, 'promotion_bonus' => 0],
        ]]);
        $this->packageRepository->expects($this->never())->method('findByDestination');

        $benefit = $this->resolver->resolve($package)[0];

        $this->assertSame(['base' => 5, 'promotion_bonus' => 0], $benefit['amount']);
    }

    public function testLeavesABenefitUntouchedWhenOperationIsSetButValueIsMissing(): void
    {
        $package = $this->package()->setBenefits([[
            'type' => 'DATA', 'unit' => 'GB', 'unit_type' => 'DATA',
            'operation' => 'ADD',
            'amount' => ['base' => 5],
        ]]);

        $benefit = $this->resolver->resolve($package)[0];

        $this->assertSame(['base' => 5], $benefit['amount']);
    }

    public function testResolvesTheRegularPackageOnlyOnceForMultipleBenefitsOfTheSamePackage(): void
    {
        $package = $this->package(600.0, 'CUP')->setBenefits([
            ['type' => 'DATA', 'unit' => 'GB', 'unit_type' => 'DATA', 'operation' => 'ADD', 'value' => 20],
            ['type' => 'MINUTES', 'unit' => 'MIN', 'unit_type' => 'MINUTES', 'operation' => 'ADD', 'value' => 100],
        ]);

        $regularPackage = $this->package(600.0, 'CUP')->setBenefits([
            ['type' => 'DATA', 'unit' => 'GB', 'unit_type' => 'DATA', 'amount' => ['base' => 5]],
            ['type' => 'MINUTES', 'unit' => 'MIN', 'unit_type' => 'MINUTES', 'amount' => ['base' => 50]],
        ]);
        $this->packageRepository->expects($this->once())
            ->method('findByDestination')
            ->willReturn($regularPackage);

        $benefits = $this->resolver->resolve($package);

        $this->assertSame(25, $benefits[0]['amount']['total_excluding_tax']);
        $this->assertSame(150, $benefits[1]['amount']['total_excluding_tax']);
    }
}
