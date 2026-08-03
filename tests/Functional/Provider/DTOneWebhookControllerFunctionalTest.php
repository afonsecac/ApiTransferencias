<?php

namespace App\Tests\Functional\Provider;

use App\Entity\CommunicationSaleRecharge;
use App\Enums\CommunicationProviderEnum;
use App\Enums\CommunicationStateEnum;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * @covers \App\Controller\DTOneWebhookController
 * @covers \App\Service\DTOne\DTOneWebhookService
 *
 * Funcional de extremo a extremo: ruta real + access_control real (el
 * firewall NO debe exigir autenticación en esta ruta) + servicio real +
 * Postgres real. El transport de CheckSaleMessage está en in-memory:// en
 * test (config/packages/messenger.yaml, when@test) para no depender de
 * RabbitMQ.
 */
class DTOneWebhookControllerFunctionalTest extends ProviderFunctionalTestCase
{
    private function callWebhook(string $token, array $body): Response
    {
        $request = Request::create(
            '/api/webhooks/dtone/' . $token,
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($body),
        );

        return self::$kernel->handle($request);
    }

    private function transport(): InMemoryTransport
    {
        $transport = self::getContainer()->get('messenger.transport.async_check_status');
        $this->assertInstanceOf(InMemoryTransport::class, $transport);

        return $transport;
    }

    public function testReturns404WhenNoTokenConfigured(): void
    {
        $response = $this->callWebhook('whatever-token', ['external_id' => 'TX-1']);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testReturns404WhenTokenDoesNotMatch(): void
    {
        $this->setSysConfig('provider.dtone.callback_token', 'the-real-token');

        $response = $this->callWebhook('wrong-token', ['external_id' => 'TX-1']);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testAcceptsValidTokenAndDispatchesCheckSaleMessageForKnownSale(): void
    {
        $this->setSysConfig('provider.dtone.callback_token', 'the-real-token');

        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);
        $package = $this->createSellablePackage($environment, $account, CommunicationProviderEnum::DTONE->value);

        $sale = new CommunicationSaleRecharge();
        $sale->setPackage($package);
        $sale->setTenant($account);
        $sale->setTransactionId('fnwbhk-tx-1');
        $sale->setClientTransactionId('functional-webhook-ctx-1');
        $sale->setAmount($package->getAmount());
        $sale->setCurrency($package->getCurrency());
        $sale->setState(CommunicationStateEnum::PENDING);
        $sale->setStateProcess(CommunicationStateEnum::PENDING->value);
        $sale->setProvider('DTONE');
        $sale->setPhoneNumber('5550001234');
        $this->em->persist($sale);
        $this->em->flush();

        $this->assertCount(0, $this->transport()->getSent());

        // eventId único por ejecución: cache.app (usado para el dedup del
        // webhook) es el adaptador de filesystem por defecto, persistente
        // entre invocaciones de PHPUnit — un literal fijo colisionaría con
        // el marcador de una corrida anterior dentro del TTL de dedup.
        $response = $this->callWebhook('the-real-token', [
            'external_id' => 'fnwbhk-tx-1',
            'id' => uniqid('evt-functional-', true),
        ]);

        $this->assertSame(202, $response->getStatusCode());

        $sent = $this->transport()->getSent();
        $this->assertCount(1, $sent);
        $this->assertSame($sale->getId(), $sent[0]->getMessage()->getSaleId());
    }

    public function testAcceptsValidTokenButDoesNotDispatchForUnknownExternalId(): void
    {
        $this->setSysConfig('provider.dtone.callback_token', 'the-real-token');

        $response = $this->callWebhook('the-real-token', ['external_id' => 'no-such-transaction']);

        $this->assertSame(202, $response->getStatusCode());
        $this->assertCount(0, $this->transport()->getSent());
    }

    public function testRejectsPayloadWithoutExternalId(): void
    {
        $this->setSysConfig('provider.dtone.callback_token', 'the-real-token');

        $response = $this->callWebhook('the-real-token', ['id' => 'evt-only']);

        $this->assertSame(400, $response->getStatusCode());
    }
}
