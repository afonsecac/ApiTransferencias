<?php

namespace App\Tests\Functional\Provider;

use App\Entity\Account;
use App\Entity\BalanceOperation;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationSaleHistory;
use App\Entity\CommunicationSaleInfo;
use App\Entity\CommunicationSaleRecharge;
use App\Enums\CommunicationStateEnum;
use App\Exception\MyCurrentException;
use App\Message\CheckSaleMessage;
use App\Repository\CommunicationSaleInfoRepository;
use App\Service\UncertainSaleService;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * COMR-258: resolución manual de las ventas inciertas. Contra Postgres real.
 *
 * @covers \App\Service\UncertainSaleService
 */
class UncertainSaleServiceFunctionalTest extends ProviderFunctionalTestCase
{
    private Account $account;
    private CommunicationPackage $package;
    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $environment = $this->createEnvironment();
        $this->account = $this->createAccount($this->createClient(), $environment);
        $this->package = $this->createSellableV2Package($environment, 'ETECSA');
    }

    private function sale(CommunicationStateEnum $state, string $stateProcess, float $amount = 5.0): CommunicationSaleRecharge
    {
        ++$this->sequence;
        $sale = (new CommunicationSaleRecharge())
            ->setTenant($this->account)
            ->setCatalogPackage($this->package)
            ->setPackageId($this->package->getId())
            ->setAmount($amount)
            ->setTotalPrice($amount)
            ->setCurrency('USD')
            ->setProvider('ETECSA')
            ->setClientTransactionId('unc-' . $this->sequence)
            ->setTransactionId('2610140' . str_pad((string) $this->sequence, 6, '0', STR_PAD_LEFT))
            ->setPhoneNumber('5550000000')
            ->setDestinationAmount($this->package->getDestinationAmount())
            ->setDestinationCurrency($this->package->getDestinationCurrency())
            ->setState($state)
            ->setStateProcess($stateProcess);
        $this->em->persist($sale);
        $this->em->flush();

        return $sale;
    }

    private function uncertain(float $amount = 5.0): CommunicationSaleRecharge
    {
        return $this->sale(CommunicationStateEnum::PENDING, CommunicationSaleInfo::STATE_PROCESS_UNCERTAIN, $amount);
    }

    private function service(): UncertainSaleService
    {
        return self::getContainer()->get(UncertainSaleService::class);
    }

    private function repository(): CommunicationSaleInfoRepository
    {
        $repository = $this->em->getRepository(CommunicationSaleInfo::class);
        \assert($repository instanceof CommunicationSaleInfoRepository);

        return $repository;
    }

    private function reloaded(CommunicationSaleInfo $sale): CommunicationSaleInfo
    {
        $this->em->clear();

        return $this->repository()->find($sale->getId());
    }

    private function transport(): InMemoryTransport
    {
        $transport = self::getContainer()->get('messenger.transport.async_check_status');
        \assert($transport instanceof InMemoryTransport);

        return $transport;
    }

    public function testListOnlyReturnsUncertainSalesOldestFirst(): void
    {
        $first = $this->uncertain();
        $this->sale(CommunicationStateEnum::PENDING, 'Pending');
        $this->sale(CommunicationStateEnum::COMPLETED, 'Completed');
        $second = $this->uncertain();

        $page = $this->service()->list(0, 50);

        $mine = array_values(array_filter($page['items'], fn (CommunicationSaleInfo $s): bool => $s->getTenant()?->getId() === $this->account->getId()));
        $this->assertSame([$first->getId(), $second->getId()], array_map(static fn (CommunicationSaleInfo $s): ?int => $s->getId(), $mine));
    }

    public function testRecheckQueuesAStatusCheckAndNothingElse(): void
    {
        $sale = $this->uncertain();
        $this->transport()->reset();

        $this->service()->recheck($sale->getId());

        $sent = $this->transport()->getSent();
        $this->assertCount(1, $sent);
        $this->assertInstanceOf(CheckSaleMessage::class, $sent[0]->getMessage());
        $this->assertSame($sale->getId(), $sent[0]->getMessage()->getSaleId());
    }

    public function testRecheckOfASaleThatIsNotUncertainIsRefused(): void
    {
        $sale = $this->sale(CommunicationStateEnum::PENDING, 'Pending');

        try {
            $this->service()->recheck($sale->getId());
            $this->fail('Debía lanzar SALE_NOT_UNCERTAIN');
        } catch (MyCurrentException $e) {
            $this->assertSame(409, $e->getCode());
        }
    }

    public function testResolvingAsCompletedFinishesTheSaleAndDebitsTheBalanceOnce(): void
    {
        $sale = $this->uncertain(7.0);

        $this->service()->resolve($sale->getId(), 'completed', 'El proveedor confirmó por correo la recarga', 'admin@example.com');

        $reloaded = $this->reloaded($sale);
        $this->assertSame(CommunicationStateEnum::COMPLETED, $reloaded->getState());
        $this->assertSame(CommunicationStateEnum::COMPLETED->value, $reloaded->getStateProcess());
        $operations = $this->em->getRepository(BalanceOperation::class)->findBy(['communicationSale' => $reloaded]);
        $this->assertCount(1, $operations);
        $this->assertSame(7.0, $operations[0]->getAmount());
    }

    public function testResolvingAsRejectedReleasesTheReservedBalanceWithoutDebiting(): void
    {
        $sale = $this->uncertain(9.0);
        $this->assertSame(9.0, $this->repository()->getReservedAmount($this->account->getId()));

        $this->service()->resolve($sale->getId(), 'rejected', 'El proveedor confirmó que no la procesó', 'admin@example.com');

        $reloaded = $this->reloaded($sale);
        $this->assertSame(CommunicationStateEnum::REJECTED, $reloaded->getState());
        $this->assertSame(CommunicationStateEnum::REJECTED->value, $reloaded->getStateProcess());
        $this->assertSame(0.0, $this->repository()->getReservedAmount($this->account->getId()));
        $this->assertSame([], $this->em->getRepository(BalanceOperation::class)->findBy(['communicationSale' => $reloaded]));
    }

    public function testEveryResolutionLeavesAHistoryRecordWithTheUserAndTheReason(): void
    {
        $sale = $this->uncertain();

        $this->service()->resolve($sale->getId(), 'rejected', 'No consta en el proveedor tras revisarlo', 'admin@example.com');

        $this->em->clear();
        $history = $this->em->getRepository(CommunicationSaleHistory::class)->findBy(['sale' => $sale->getId()]);
        $this->assertNotSame([], $history);
        $encoded = json_encode(array_map(static fn (CommunicationSaleHistory $h): array => $h->getInfo(), $history), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('admin@example.com', $encoded);
        $this->assertStringContainsString('No consta en el proveedor tras revisarlo', $encoded);
    }

    public function testASaleResolvedOnceCannotBeResolvedAgain(): void
    {
        $sale = $this->uncertain();
        $this->service()->resolve($sale->getId(), 'completed', 'Confirmada por el proveedor', 'admin@example.com');

        try {
            $this->service()->resolve($sale->getId(), 'rejected', 'Otro administrador la rechaza', 'otro@example.com');
            $this->fail('Debía lanzar SALE_NOT_UNCERTAIN');
        } catch (MyCurrentException $e) {
            $this->assertSame(409, $e->getCode());
        }
        $this->assertSame(CommunicationStateEnum::COMPLETED, $this->reloaded($sale)->getState());
    }

    public function testUnknownSaleIsNotFound(): void
    {
        try {
            $this->service()->resolve(999999999, 'completed', 'No existe esta venta', 'admin@example.com');
            $this->fail('Debía lanzar SALE_NOT_FOUND');
        } catch (MyCurrentException $e) {
            $this->assertSame(404, $e->getCode());
        }
    }

    public function testAnUnknownResolutionIsRefused(): void
    {
        $sale = $this->uncertain();

        try {
            $this->service()->resolve($sale->getId(), 'maybe', 'Motivo suficiente', 'admin@example.com');
            $this->fail('Debía lanzar INVALID_RESOLUTION');
        } catch (MyCurrentException $e) {
            $this->assertSame(400, $e->getCode());
        }
        $this->assertSame(CommunicationSaleInfo::STATE_PROCESS_UNCERTAIN, $this->reloaded($sale)->getStateProcess());
    }
}
