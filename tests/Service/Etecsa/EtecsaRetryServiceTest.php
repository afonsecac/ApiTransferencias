<?php

namespace App\Tests\Service\Etecsa;

use App\Entity\Account;
use App\Entity\CommunicationSaleRecharge;
use App\Entity\Environment;
use App\Enums\CommunicationProviderEnum;
use App\Enums\CommunicationStateEnum;
use App\Exception\MyCurrentException;
use App\Service\Etecsa\EtecsaGatewayClient;
use App\Service\Etecsa\EtecsaRetryService;
use App\Service\HistoricalSaleService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @covers \App\Service\Etecsa\EtecsaRetryService
 */
class EtecsaRetryServiceTest extends TestCase
{
    private function rechargeFixture(
        CommunicationStateEnum $state = CommunicationStateEnum::FAILED,
        string $provider = 'ETECSA',
    ): CommunicationSaleRecharge {
        $environment = (new Environment())->setType('TEST')->setBasePath('https://legacy.example');
        $account = (new Account())->setEnvironment($environment);

        $recharge = (new CommunicationSaleRecharge())
            ->setTransactionId('2610080101856')
            ->setProvider($provider)
            ->setState($state)
            ->setTenant($account);

        // Doctrine normalmente asigna el id; en memoria lo forzamos por reflexión.
        (new \ReflectionProperty($recharge, 'id'))->setValue($recharge, 42);

        return $recharge;
    }

    private function emReturning(?CommunicationSaleRecharge $recharge): EntityManagerInterface
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->with(42)->willReturn($recharge);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(CommunicationSaleRecharge::class)->willReturn($repo);

        return $em;
    }

    public function testThrowsWhenSaleNotFound(): void
    {
        $service = new EtecsaRetryService(
            $this->emReturning(null),
            $this->createMock(EtecsaGatewayClient::class),
            $this->createMock(HistoricalSaleService::class),
            new NullLogger(),
        );

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionCode(404);

        $service->retry(42, 'check', 'motivo de prueba valido');
    }

    public function testRejectsNonEtecsaProvider(): void
    {
        $recharge = $this->rechargeFixture(provider: CommunicationProviderEnum::DTONE->value);
        $gatewayClient = $this->createMock(EtecsaGatewayClient::class);
        $gatewayClient->expects($this->never())->method('retrySale');

        $service = new EtecsaRetryService(
            $this->emReturning($recharge),
            $gatewayClient,
            $this->createMock(HistoricalSaleService::class),
            new NullLogger(),
        );

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionCode(422);

        $service->retry(42, 'check', 'motivo de prueba valido');
    }

    public function testRejectsStateNotPendingOrFailed(): void
    {
        $recharge = $this->rechargeFixture(state: CommunicationStateEnum::COMPLETED);

        $service = new EtecsaRetryService(
            $this->emReturning($recharge),
            $this->createMock(EtecsaGatewayClient::class),
            $this->createMock(HistoricalSaleService::class),
            new NullLogger(),
        );

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionCode(400);

        $service->retry(42, 'check', 'motivo de prueba valido');
    }

    public function testCheckModeSyncsLocalStateFromEtecsaResponse(): void
    {
        $recharge = $this->rechargeFixture(state: CommunicationStateEnum::FAILED);

        $gatewayClient = $this->createMock(EtecsaGatewayClient::class);
        $gatewayClient->expects($this->once())
            ->method('retrySale')
            ->with(
                $this->isInstanceOf(Environment::class),
                '2610080101856',
                'check',
                'motivo de prueba valido',
                'Failed',
            )
            ->willReturn([
                'status' => 200,
                'body' => [
                    'transactionId' => '2610080101856',
                    'previousStatus' => 'Failed',
                    'status' => 'Completed',
                    'action' => 'synced',
                    'etecsaStateCode' => 'OK',
                    'message' => 'ETECSA confirma la recarga como Realizada.',
                ],
            ]);

        $historicalSaleService = $this->createMock(HistoricalSaleService::class);
        $historicalSaleService->expects($this->once())
            ->method('createHistoricalCommunication')
            ->with(42, CommunicationStateEnum::COMPLETED, $this->isType('array'));

        $em = $this->emReturning($recharge);
        $em->expects($this->atLeastOnce())->method('flush');

        $service = new EtecsaRetryService($em, $gatewayClient, $historicalSaleService, new NullLogger());

        $result = $service->retry(42, 'check', 'motivo de prueba valido');

        $this->assertSame(CommunicationStateEnum::COMPLETED, $recharge->getState());
        $this->assertSame('synced', $result['action']);
        $this->assertSame('Completed', $result['status']);
    }

    public function testUnmappedStatusLeavesLocalStateUnchanged(): void
    {
        $recharge = $this->rechargeFixture(state: CommunicationStateEnum::PENDING);

        $gatewayClient = $this->createMock(EtecsaGatewayClient::class);
        $gatewayClient->method('retrySale')->willReturn([
            'status' => 200,
            'body' => ['status' => 'AuthFailed', 'action' => 'resent'],
        ]);

        $service = new EtecsaRetryService(
            $this->emReturning($recharge),
            $gatewayClient,
            $this->createMock(HistoricalSaleService::class),
            new NullLogger(),
        );

        $service->retry(42, 'resend', 'motivo de prueba valido');

        // AuthFailed no tiene case en CommunicationStateEnum: el estado local
        // no debe tocarse a ciegas.
        $this->assertSame(CommunicationStateEnum::PENDING, $recharge->getState());
    }

    public function testPropagatesProviderConflictAsMyCurrentException(): void
    {
        $recharge = $this->rechargeFixture(state: CommunicationStateEnum::FAILED);

        $gatewayClient = $this->createMock(EtecsaGatewayClient::class);
        $gatewayClient->method('retrySale')->willReturn([
            'status' => 409,
            'body' => ['message' => 'Ya se alcanzó el máximo de reenvíos'],
        ]);

        $service = new EtecsaRetryService(
            $this->emReturning($recharge),
            $gatewayClient,
            $this->createMock(HistoricalSaleService::class),
            new NullLogger(),
        );

        try {
            $service->retry(42, 'resend', 'motivo de prueba valido');
            $this->fail('Se esperaba MyCurrentException');
        } catch (MyCurrentException $e) {
            $this->assertSame(409, $e->getCode());
            $this->assertSame('Ya se alcanzó el máximo de reenvíos', $e->getMessage());
        }
    }
}
