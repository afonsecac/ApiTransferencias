<?php

namespace App\Tests\Entity;

use App\Entity\CommunicationPackage;
use App\Entity\CommunicationPromotions;
use App\Entity\CommunicationSalePackage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * @covers \App\Entity\CommunicationSaleInfo
 *
 * getPromotionName() alimenta el badge "Promoción" del listado de ventas
 * en el dashboard (Balance operativo > Paquetes) — alcance V2 solamente
 * (decisión explícita: CommunicationClientPackage::$promotionItems, V1, se
 * filtra por fecha actual y no es un snapshot histórico confiable).
 */
class CommunicationSaleInfoPromotionNameTest extends TestCase
{
    public function testReturnsNullWhenSaleHasNoCatalogPackage(): void
    {
        $sale = new CommunicationSalePackage();

        $this->assertNull($sale->getPromotionName());
    }

    public function testReturnsNullWhenCatalogPackageHasNoPromotion(): void
    {
        $package = (new CommunicationPackage())
            ->setName('P')->setDescription('P')
            ->setDestinationAmount(500.0)->setDestinationCurrency('CUP');

        $sale = (new CommunicationSalePackage())->setCatalogPackage($package);

        $this->assertNull($sale->getPromotionName());
    }

    public function testReturnsThePromotionNameWhenCatalogPackageComesFromAPromotion(): void
    {
        $promotion = (new CommunicationPromotions())->setName('Promo Verano 2026');
        $package = (new CommunicationPackage())
            ->setName('Promo UI 500 CUP')->setDescription('P')
            ->setDestinationAmount(500.0)->setDestinationCurrency('CUP')
            ->setPromotion($promotion);

        $sale = (new CommunicationSalePackage())->setCatalogPackage($package);

        $this->assertSame('Promo Verano 2026', $sale->getPromotionName());
    }

    public function testGetPromotionNameIsExposedInListAndDetailGroups(): void
    {
        $reflection = new \ReflectionMethod(CommunicationSalePackage::class, 'getPromotionName');
        $attributes = $reflection->getAttributes(Groups::class);

        $this->assertNotEmpty($attributes, 'getPromotionName() debería tener un atributo #[Groups].');

        /** @var Groups $groups */
        $groups = $attributes[0]->newInstance();

        $this->assertContains('sale:list', $groups->groups);
        $this->assertContains('sale:detail', $groups->groups);
    }
}
