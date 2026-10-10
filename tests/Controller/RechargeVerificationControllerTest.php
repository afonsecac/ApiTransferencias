<?php

namespace App\Tests\Controller;

use App\Controller\RechargeVerificationController;
use App\Entity\Account;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationPromotions;
use App\Entity\CommunicationSaleHistory;
use App\Entity\CommunicationSaleRecharge;
use App\Enums\CommunicationStateEnum;
use App\Service\RechargeReceiptPdfService;
use App\Service\RechargeVerificationService;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \App\Controller\RechargeVerificationController
 * @covers \App\Service\RechargeVerificationService
 */
class RechargeVerificationControllerTest extends ProviderFunctionalTestCase
{
    private function controller(): RechargeVerificationController
    {
        $controller = new RechargeVerificationController(
            self::getContainer()->get(RechargeVerificationService::class),
            self::getContainer()->get(RechargeReceiptPdfService::class),
        );
        $controller->setContainer(self::getContainer());

        return $controller;
    }

    private function requestWithToken(?string $token): Request
    {
        return new Request($token !== null ? ['token' => $token] : []);
    }

    private function recharge(
        Account $tenant,
        string $transactionId,
        CommunicationStateEnum $state,
        ?CommunicationPackage $catalogPackage = null,
    ): CommunicationSaleRecharge {
        $sale = (new CommunicationSaleRecharge())
            ->setTenant($tenant)
            ->setClientTransactionId('ctx-' . $transactionId)
            ->setTransactionId($transactionId)
            ->setTransactionOrder('ETC-' . $transactionId)
            ->setAmount(20.0)
            ->setCurrency('USD')
            ->setTotalPrice(20.0)
            ->setState($state)
            ->setProvider('ETECSA')
            ->setPhoneNumber('5358831337')
            ->setDestinationAmount(625.0)
            ->setDestinationCurrency('CUP');

        if ($catalogPackage !== null) {
            $sale->setCatalogPackage($catalogPackage);
        }

        $this->em->persist($sale);

        return $sale;
    }

    public function testVerifyReturnsDataForACompletedRecharge(): void
    {
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);

        $sale = $this->recharge($account, 'txc1', CommunicationStateEnum::COMPLETED);
        $this->em->flush();

        $history = (new CommunicationSaleHistory())
            ->setState(CommunicationStateEnum::COMPLETED)
            ->setInfo([]);
        $sale->addHistorical($history);
        $this->em->persist($history);
        $this->em->flush();

        $response = $this->controller()->verify('txc1', $this->requestWithToken($sale->getAccessToken()));
        $data = json_decode((string) $response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('txc1', $data['transactionId']);
        $this->assertSame('ETC-txc1', $data['etecsaOrderId']);
        $this->assertSame($client->getId(), $data['clientId']);
        $this->assertSame('Completed', $data['state']);
        $this->assertEquals(625.0, $data['destinationAmount']);
        $this->assertSame('CUP', $data['destinationCurrency']);
        $this->assertSame('5358831337', $data['phone']);
        $this->assertNull($data['promotion']);
        $this->assertCount(1, $data['history']);
        $this->assertSame('Completed', $data['history'][0]['state']);
    }

    public function testVerifyReturns404ForAPendingRecharge(): void
    {
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);
        $sale = $this->recharge($account, 'txp1', CommunicationStateEnum::PENDING);
        $this->em->flush();

        $response = $this->controller()->verify('txp1', $this->requestWithToken($sale->getAccessToken()));

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testVerifyReturns404ForAnUnknownTransactionId(): void
    {
        $response = $this->controller()->verify('does-not-exist', $this->requestWithToken('any-token'));

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testVerifyReturns404WhenTokenIsMissing(): void
    {
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);
        $this->recharge($account, 'txnotoken1', CommunicationStateEnum::COMPLETED);
        $this->em->flush();

        $response = $this->controller()->verify('txnotoken1', $this->requestWithToken(null));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringNotContainsString('5358831337', (string) $response->getContent());
    }

    public function testVerifyReturns404WhenTokenDoesNotMatch(): void
    {
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);
        $this->recharge($account, 'txwrongtk1', CommunicationStateEnum::COMPLETED);
        $this->em->flush();

        $response = $this->controller()->verify('txwrongtk1', $this->requestWithToken('wrong-token'));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringNotContainsString('5358831337', (string) $response->getContent());
    }

    public function testVerifyIncludesPromotionDetailWhenTheSaleHasOne(): void
    {
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);

        $promotion = (new CommunicationPromotions())
            ->setName('Promo Internet Ilimitado 10 dias')
            ->setDescription('Datos ilimitados por 10 dias')
            ->setStartAt(new \DateTimeImmutable('-1 day'))
            ->setEndAt(new \DateTimeImmutable('+30 days'));
        $this->em->persist($promotion);

        $package = (new CommunicationPackage())
            ->setName('Promo Internet Ilimitado 10 dias')
            ->setDescription('P')
            ->setDestinationAmount(525.0)
            ->setDestinationCurrency('CUP')
            ->setPromotion($promotion);
        $this->em->persist($package);

        $sale = $this->recharge($account, 'txpr1', CommunicationStateEnum::COMPLETED, $package);
        $this->em->flush();

        $response = $this->controller()->verify('txpr1', $this->requestWithToken($sale->getAccessToken()));
        $data = json_decode((string) $response->getContent(), true);

        $this->assertSame('Promo Internet Ilimitado 10 dias', $data['promotion']['name'] ?? null);
        $this->assertSame('Datos ilimitados por 10 dias', $data['promotion']['description'] ?? null);
    }
}
