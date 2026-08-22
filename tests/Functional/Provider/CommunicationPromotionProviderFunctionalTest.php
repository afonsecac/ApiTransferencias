<?php

namespace App\Tests\Functional\Provider;

use ApiPlatform\Metadata\GetCollection;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationProduct;
use App\Entity\CommunicationPromotions;
use App\State\CommunicationPromotionProvider;

/**
 * @covers \App\State\CommunicationPromotionProvider
 * @covers \App\Entity\CommunicationPromotions
 *
 * Regresión del incidente en prod del 2026-08-22:
 * 1. `products` se serializaba como objeto JSON en vez de array cuando el
 *    filtro por tenant dejaba huecos en las keys del ArrayCollection interno
 *    (Collection::filter() preserva claves).
 * 2. Un fix posterior para poblar `products` en promociones V2 metió arrays
 *    planos en una relación Doctrine ManyToMany tipada, y
 *    AbstractItemNormalizer::normalizeCollectionOfRelations() de API
 *    Platform lanzaba UnexpectedValueException("Unexpected non-object
 *    element in to-many relation") — 500 en toda página con al menos una
 *    promoción V2.
 *
 * Ejercita Provider + Entity + el serializer real de API Platform (mismo
 * servicio que usa el kernel HTTP) contra Postgres real — ninguna de las dos
 * roturas era detectable con un mock del serializer.
 */
class CommunicationPromotionProviderFunctionalTest extends ProviderFunctionalTestCase
{
    private function provider(): CommunicationPromotionProvider
    {
        return self::getContainer()->get(CommunicationPromotionProvider::class);
    }

    private function normalize(CommunicationPromotions $promotion): array
    {
        $serializer = self::getContainer()->get('api_platform.serializer');

        return $serializer->normalize($promotion, 'json', ['groups' => ['comProm:read']]);
    }

    /**
     * @return CommunicationPromotions[]
     */
    private function listPromotions(): array
    {
        $result = $this->provider()->provide(new GetCollection(class: CommunicationPromotions::class));

        return iterator_to_array($result);
    }

    public function testV1PromotionProductsSurviveAsAJsonArrayEvenWithGapsAfterTenantFilter(): void
    {
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);

        $otherAccount = $this->createAccount($this->createClient(), $environment);

        $product = (new CommunicationProduct())
            ->setEnvironment($environment)
            ->setPackageId(900001)
            ->setPackageType('RECHARGE')
            ->setPrice(5.0)
            ->setEnabled(true)
            ->setDescription('Producto promo V1 funcional')
            ->setProvider('CSQ')
            ->setExternalRef('900001');
        $this->em->persist($product);

        $promotion = (new CommunicationPromotions())
            ->setName('Promo V1 funcional')
            ->setDescription('Promo V1 funcional')
            ->setProduct($product)
            ->setEnvironment($environment)
            ->setStartAt(new \DateTimeImmutable('-1 day'))
            ->setEndAt(new \DateTimeImmutable('+5 days'));
        $this->em->persist($promotion);

        // Intercala un paquete de OTRO tenant entre los del tenant
        // autenticado: el filtro por tenant deja un hueco en la key central
        // del ArrayCollection interno — exactamente el escenario que
        // serializaba como {"0":..,"2":..} en vez de [...] antes del fix.
        $ownPackage1 = $this->createSellablePackage($environment, $account, 'CSQ', 5.0);
        $foreignPackage = $this->createSellablePackage($environment, $otherAccount, 'CSQ', 5.0);
        $ownPackage2 = $this->createSellablePackage($environment, $account, 'CSQ', 5.0);

        $promotion->addProduct($ownPackage1);
        $promotion->addProduct($foreignPackage);
        $promotion->addProduct($ownPackage2);
        $this->em->flush();

        $this->authenticateAs($account);

        $found = null;
        foreach ($this->listPromotions() as $item) {
            if ($item->getId() === $promotion->getId()) {
                $found = $item;
            }
        }
        $this->assertNotNull($found, 'La promoción V1 debe aparecer en el listado.');

        $normalized = $this->normalize($found);

        $this->assertIsArray($normalized['products']);
        $this->assertTrue(array_is_list($normalized['products']), 'products debe serializar como lista JSON, no como objeto con keys sparse.');
        $this->assertCount(2, $normalized['products']);

        $ids = array_column($normalized['products'], 'id');
        $this->assertContains($ownPackage1->getId(), $ids);
        $this->assertContains($ownPackage2->getId(), $ids);
        $this->assertNotContains($foreignPackage->getId(), $ids, 'No debe filtrarse el paquete de otro tenant.');

        $json = json_encode($normalized);
        $this->assertStringContainsString('"products":[', $json);
    }

    public function testV2PromotionProductsAreResolvedFromCommunicationPackageWithoutBreakingTheApiPlatformSerializer(): void
    {
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);

        $promotion = (new CommunicationPromotions())
            ->setName('Promo V2 funcional')
            ->setDescription('Promo V2 funcional')
            ->setEnvironment($environment)
            ->setStartAt(new \DateTimeImmutable('-1 day'))
            ->setEndAt(new \DateTimeImmutable('+5 days'));
        $this->em->persist($promotion);
        $this->em->flush();

        $this->assertTrue($promotion->isV2());

        $package = (new CommunicationPackage())
            ->setName('Paquete V2 funcional')
            ->setDescription('Paquete V2 funcional')
            ->setDestinationAmount(600.0)
            ->setDestinationCurrency('CUP')
            ->setPromotion($promotion);
        $this->em->persist($package);
        $this->em->flush();

        $this->authenticateAs($account);

        $found = null;
        foreach ($this->listPromotions() as $item) {
            if ($item->getId() === $promotion->getId()) {
                $found = $item;
            }
        }
        $this->assertNotNull($found, 'La promoción V2 debe aparecer en el listado.');

        // Antes del fix, esta llamada lanzaba UnexpectedValueException
        // ("Unexpected non-object element in to-many relation") — la misma
        // excepción vista en prod.
        $normalized = $this->normalize($found);

        $this->assertIsArray($normalized['products']);
        $this->assertTrue(array_is_list($normalized['products']));
        $this->assertCount(1, $normalized['products']);
        $this->assertSame($package->getId(), $normalized['products'][0]['id']);
        $this->assertSame('Paquete V2 funcional', $normalized['products'][0]['name']);

        $json = json_encode($normalized);
        $this->assertStringContainsString('"products":[', $json);
    }
}
