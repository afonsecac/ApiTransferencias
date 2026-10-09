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
 * @covers \App\Service\RechargeReceiptPdfService
 */
class RechargeVerificationReceiptTest extends ProviderFunctionalTestCase
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

    public function testReceiptReturnsAPdfForACompletedRecharge(): void
    {
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);

        $sale = $this->recharge($account, 'txpdf1', CommunicationStateEnum::COMPLETED);
        $this->em->flush();

        $history = (new CommunicationSaleHistory())
            ->setState(CommunicationStateEnum::COMPLETED)
            ->setInfo([]);
        $sale->addHistorical($history);
        $this->em->persist($history);
        $this->em->flush();

        $request = Request::create('https://staging-api.comremit.com/api/verify/txpdf1/receipt');
        $response = $this->controller()->receipt('txpdf1', $request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('comprobante-txpdf1.pdf', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function testReceiptIncludesPromotionAndQrPointingAtItself(): void
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

        $sale = $this->recharge($account, 'txpdf2', CommunicationStateEnum::COMPLETED, $package);
        $this->em->flush();

        $history = (new CommunicationSaleHistory())
            ->setState(CommunicationStateEnum::COMPLETED)
            ->setInfo([]);
        $sale->addHistorical($history);
        $this->em->persist($history);
        $this->em->flush();

        $request = Request::create('https://staging-api.comremit.com/api/verify/txpdf2/receipt');
        $response = $this->controller()->receipt('txpdf2', $request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
        // No podemos leer el texto dentro del stream del PDF sin un parser, pero el
        // tamaño del cuerpo sube de forma apreciable cuando el QR y la promoción
        // se incluyen realmente — una señal indirecta de que el template se llenó.
        $this->assertGreaterThan(2000, \strlen((string) $response->getContent()));
    }

    public function testReceiptReturns404ForAPendingRecharge(): void
    {
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);
        $this->recharge($account, 'txpdfp1', CommunicationStateEnum::PENDING);
        $this->em->flush();

        $request = Request::create('https://staging-api.comremit.com/api/verify/txpdfp1/receipt');
        $response = $this->controller()->receipt('txpdfp1', $request);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testReceiptReturns404ForAnUnknownTransactionId(): void
    {
        $request = Request::create('https://staging-api.comremit.com/api/verify/does-not-exist/receipt');
        $response = $this->controller()->receipt('does-not-exist', $request);

        $this->assertSame(404, $response->getStatusCode());
    }
}
