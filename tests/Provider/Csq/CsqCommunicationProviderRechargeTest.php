<?php

namespace App\Tests\Provider\Csq;

use App\Enums\CommunicationProviderEnum;
use App\Enums\ProviderOutcomeEnum;
use App\Exception\MyCurrentException;
use App\Provider\Contract\ProviderContext;
use App\Provider\Contract\ProviderStatusQuery;
use App\Provider\Contract\RechargeRequest;
use App\Provider\Csq\CsqCommunicationProvider;
use App\Provider\Csq\CsqHttpClient;
use App\Provider\Csq\CsqStatusMapper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @covers \App\Provider\Csq\CsqCommunicationProvider::recharge
 * @covers \App\Provider\Csq\CsqCommunicationProvider::fetchRechargeStatus
 * @covers \App\Provider\Csq\CsqCommunicationProvider::getPlatformBalance
 */
class CsqCommunicationProviderRechargeTest extends TestCase
{
    private CsqHttpClient&MockObject $client;
    private CsqCommunicationProvider $provider;

    protected function setUp(): void
    {
        $this->client = $this->createMock(CsqHttpClient::class);
        $this->provider = new CsqCommunicationProvider($this->client, new CsqStatusMapper(), new NullLogger());
    }

    private function context(): ProviderContext
    {
        return new ProviderContext(provider: CommunicationProviderEnum::CSQ, environmentType: 'TEST');
    }

    // ---- recharge() ----

    public function testRechargeParsesArticleIdAndAmountFromExternalRef(): void
    {
        // externalRef "7951-2200" -> articleId=7951 (operatorId en la URL de
        // Purchase), amount=2200 CUP -> destinationAmountX100=220000.
        $this->client->expects($this->once())
            ->method('purchase')
            ->with(
                $this->anything(),
                7951,
                100042, // deriveLocalReference("2608100100042") = "0100042" -> 100042
                '53500000',
                220000,
            )
            ->willReturn([
                'rc' => 0,
                'items' => [['resultcode' => '10', 'resultmessage' => 'OK', 'supplierreference' => 'X1']],
            ]);

        $request = new RechargeRequest(
            transactionId: '2608100100042',
            phoneNumber: '53500000',
            productExternalId: '7951-2200',
            destinationAmount: 2200.0,
            destinationUnit: 'CUP',
        );

        $result = $this->provider->recharge($this->context(), $request);

        $this->assertSame(ProviderOutcomeEnum::COMPLETED, $result->outcome);
        $this->assertSame('X1', $result->providerReference);
    }

    /**
     * Nauta CUP (7855) exige la cuenta Nauta ("Nauta email"), no un número
     * de teléfono — confirmado contra el sandbox real de CSQ el 2026-08-31
     * (GET /pre-paid/recharge/parameters). CommunicationSaleService solo
     * puebla accountIdentifier cuando el producto lo exige (ver
     * CommunicationProduct::$requiredIdentifierFields), así que aquí basta
     * con preferirlo sobre phoneNumber cuando venga presente.
     */
    public function testRechargeSendsAccountIdentifierAsAccountWhenPresent(): void
    {
        $this->client->expects($this->once())
            ->method('purchase')
            ->with($this->anything(), 7855, $this->anything(), 'usuario@nauta.com.cu', $this->anything())
            ->willReturn(['rc' => 0, 'items' => [['resultcode' => '10', 'resultmessage' => 'OK', 'supplierreference' => 'X2']]]);

        $request = new RechargeRequest(
            transactionId: '2608100100043',
            phoneNumber: null,
            productExternalId: '7855-1000',
            destinationAmount: 1000.0,
            destinationUnit: 'CUP',
            accountIdentifier: 'usuario@nauta.com.cu',
        );

        $this->provider->recharge($this->context(), $request);
    }

    public function testRechargeReturnsRejectedWithClearMessageOnMalformedExternalRef(): void
    {
        $this->client->expects($this->never())->method('purchase');

        $request = new RechargeRequest(
            transactionId: '2608100100042',
            phoneNumber: '53500000',
            productExternalId: 'not-a-valid-format-at-all-extra',
            destinationAmount: 2200.0,
            destinationUnit: 'CUP',
        );

        $result = $this->provider->recharge($this->context(), $request);

        $this->assertSame(ProviderOutcomeEnum::REJECTED, $result->outcome);
        $this->assertStringContainsString('productExternalId de CSQ', $result->message);
    }

    public function testRechargeReturnsUnknownOnTransportFailure(): void
    {
        $this->client->method('purchase')->willThrowException(
            new MyCurrentException('CSQ_REQUEST_FAILED', 'timeout', 502),
        );

        $request = new RechargeRequest(
            transactionId: '2608100100042',
            phoneNumber: '53500000',
            productExternalId: '7951-2200',
            destinationAmount: 2200.0,
            destinationUnit: 'CUP',
        );

        $result = $this->provider->recharge($this->context(), $request);

        $this->assertSame(ProviderOutcomeEnum::UNKNOWN, $result->outcome);
    }

    // ---- fetchRechargeStatus() ----

    public function testFetchRechargeStatusDerivesCreationDateFromTransactionId(): void
    {
        $this->client->expects($this->once())
            ->method('getTransactionInfo')
            ->with(
                $this->anything(),
                100042,
                $this->callback(fn (\DateTimeImmutable $d) => $d->format('Ymd') === '20260810'),
            )
            ->willReturn(['rc' => -1, 'items' => [['resultcode' => '900', 'resultmessage' => 'Invalid parameters']]]);

        $result = $this->provider->fetchRechargeStatus($this->context(), new ProviderStatusQuery(transactionId: '2608100100042'));

        // Ver CsqStatusMapper: rc!==0 en status query nunca es REJECTED.
        $this->assertSame(ProviderOutcomeEnum::UNKNOWN, $result->outcome);
    }

    public function testFetchRechargeStatusReturnsUnknownOnTransportFailure(): void
    {
        $this->client->method('getTransactionInfo')->willThrowException(
            new MyCurrentException('CSQ_REQUEST_FAILED', 'timeout', 502),
        );

        $result = $this->provider->fetchRechargeStatus($this->context(), new ProviderStatusQuery(transactionId: '2608100100042'));

        $this->assertSame(ProviderOutcomeEnum::UNKNOWN, $result->outcome);
    }

    // ---- getPlatformBalance() ----

    public function testGetPlatformBalanceMapsBalanceToUsd(): void
    {
        $this->client->method('getBalances')->willReturn([
            'rc' => 0,
            'items' => [['id' => 173103, 'balance' => 465.92]],
        ]);

        $result = $this->provider->getPlatformBalance($this->context());

        $this->assertSame(['USD' => 465.92], $result->amounts);
    }
}
