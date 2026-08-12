<?php

namespace App\Tests\Repository;

use App\Entity\CommunicationPackage;
use App\Entity\CommunicationPackageProviderProduct;
use App\Entity\CommunicationProduct;
use App\Entity\Environment;
use App\Repository\CommunicationPackageProviderProductRepository;
use App\Tests\Functional\FunctionalTestCase;

/**
 * @covers \App\Repository\CommunicationPackageProviderProductRepository
 */
class CommunicationPackageProviderProductRepositoryTest extends FunctionalTestCase
{
    private function repo(): CommunicationPackageProviderProductRepository
    {
        return self::getContainer()->get(CommunicationPackageProviderProductRepository::class);
    }

    private function package(float $amount): CommunicationPackage
    {
        $package = (new CommunicationPackage())
            ->setName("P {$amount}")->setDescription("P {$amount}")
            ->setDestinationAmount($amount)->setDestinationCurrency('CUP');
        $this->em->persist($package);

        return $package;
    }

    private function product(): CommunicationProduct
    {
        $environment = (new Environment())
            ->setType('TEST')
            ->setBasePath('https://example.test')
            ->setProviderName('CSQ-test')
            ->setClientSecret('secret')
            ->setClientId('client')
            ->setDiscount(0.0)
            ->setDiscountType('%')
            ->setIsActive(true);
        $this->em->persist($environment);

        $product = (new CommunicationProduct())
            ->setEnvironment($environment)
            ->setPackageId(1)
            ->setPackageType('RECHARGE')
            ->setPrice(10.0)
            ->setEnabled(true)
            ->setProvider('CSQ')
            ->setExternalRef('ext-1');
        $this->em->persist($product);

        return $product;
    }

    public function testFindPackageIdsWithBindingsReturnsOnlyPackagesWithAtLeastOneBinding(): void
    {
        $bound = $this->package(100.0);
        $unbound = $this->package(200.0);
        $product = $this->product();
        $this->em->flush();

        $binding = (new CommunicationPackageProviderProduct())
            ->setCommunicationPackage($bound)
            ->setProvider('CSQ')
            ->setProduct($product);
        $this->em->persist($binding);
        $this->em->flush();

        $result = $this->repo()->findPackageIdsWithBindings([$bound->getId(), $unbound->getId()]);

        $this->assertSame([$bound->getId()], $result);
    }

    public function testFindPackageIdsWithBindingsReturnsEmptyArrayForEmptyInput(): void
    {
        $this->assertSame([], $this->repo()->findPackageIdsWithBindings([]));
    }
}
