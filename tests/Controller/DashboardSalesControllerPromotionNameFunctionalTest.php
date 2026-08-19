<?php

namespace App\Tests\Controller;

use App\Controller\DashboardSalesController;
use App\Entity\Account;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationPromotions;
use App\Entity\CommunicationSalePackage;
use App\Enums\CommunicationStateEnum;
use App\Service\CommunicationSaleService;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @covers \App\Controller\DashboardSalesController
 *
 * Contra Postgres real — el listado usa un join a catalogPackage->promotion
 * (ManyToOne x2, sin fan-out de paginación, a diferencia del caso
 * ManyToMany de CommunicationContract) para poblar
 * CommunicationSaleInfo::getPromotionName() sin N+1; solo se verifica de
 * verdad contra el serializer + query real.
 */
class DashboardSalesControllerPromotionNameFunctionalTest extends ProviderFunctionalTestCase
{
    private function controller(): DashboardSalesController
    {
        $controller = new DashboardSalesController(
            $this->em,
            self::getContainer()->get(NormalizerInterface::class),
            self::getContainer()->get(CommunicationSaleService::class),
            self::getContainer()->get(MessageBusInterface::class),
        );
        // list() llama a $this->getUser()/isGranted() (AbstractController) —
        // requiere un container real con el security context ya autenticado.
        $controller->setContainer(self::getContainer());

        return $controller;
    }

    private function sale(Account $tenant, ?CommunicationPackage $catalogPackage, string $clientTransactionId): CommunicationSalePackage
    {
        $sale = (new CommunicationSalePackage())
            ->setTenant($tenant)
            ->setClientTransactionId($clientTransactionId)
            ->setAmount(10.0)
            ->setCurrency('USD')
            ->setTotalPrice(10.0)
            ->setState(CommunicationStateEnum::COMPLETED)
            ->setProvider('CSQ')
            ->setIdentificationNumber('12345')
            ->setName('Cliente de prueba');

        if ($catalogPackage !== null) {
            $sale->setCatalogPackage($catalogPackage);
        }

        $this->em->persist($sale);

        return $sale;
    }

    public function testListIncludesThePromotionNameForAV2PromotionalSale(): void
    {
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);
        $this->authenticateAsAdmin($this->createAdminUser());

        $promotion = (new CommunicationPromotions())
            ->setName('Promo Verano 2026')
            ->setDescription('Promoción de prueba')
            ->setStartAt(new \DateTimeImmutable('-1 day'))
            ->setEndAt(new \DateTimeImmutable('+30 days'));
        $this->em->persist($promotion);

        $package = (new CommunicationPackage())
            ->setName('Promo UI 500 CUP')->setDescription('P')
            ->setDestinationAmount(500.0)->setDestinationCurrency('CUP')
            ->setPromotion($promotion);
        $this->em->persist($package);

        $promoSale = $this->sale($account, $package, 'promo-tx-1');
        $normalSale = $this->sale($account, null, 'normal-tx-1');
        $this->em->flush();

        $response = $this->controller()->list(new Request(['accountId' => (string) $account->getId(), 'limit' => '50']));
        $data = json_decode($response->getContent(), true);

        $byId = [];
        foreach ($data['results'] as $row) {
            $byId[$row['id']] = $row;
        }

        $this->assertSame('Promo Verano 2026', $byId[$promoSale->getId()]['promotionName'] ?? null);
        $this->assertArrayNotHasKey('promotionName', $byId[$normalSale->getId()]);
    }
}
