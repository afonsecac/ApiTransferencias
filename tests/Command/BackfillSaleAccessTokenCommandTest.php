<?php

namespace App\Tests\Command;

use App\Command\BackfillSaleAccessTokenCommand;
use App\Entity\CommunicationSaleInfo;
use App\Entity\CommunicationSaleRecharge;
use App\Enums\CommunicationStateEnum;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Backfill de communication_sale_info.access_token para ventas creadas
 * antes de que el campo existiera — PrePersist solo lo asigna en inserts
 * nuevos, así que las filas históricas se quedan con NULL y nunca pueden
 * mostrar el botón "Ver comprobante" en el dashboard.
 *
 * @covers \App\Command\BackfillSaleAccessTokenCommand
 */
class BackfillSaleAccessTokenCommandTest extends ProviderFunctionalTestCase
{
    public function testFillsOnlyRowsWithMissingAccessToken(): void
    {
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);

        $withToken = $this->createSale($account, 'bftok1', 'ETC-bftok1');
        $originalToken = $withToken->getAccessToken();
        $this->assertNotNull($originalToken);

        $withoutToken = $this->createSale($account, 'bftok2', 'ETC-bftok2');
        $this->forceAccessTokenNull($withoutToken->getId());
        $this->em->clear();

        $tester = new CommandTester(new BackfillSaleAccessTokenCommand($this->em));
        $exit = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exit);
        $this->assertStringContainsString('1', $tester->getDisplay());

        $this->em->clear();

        /** @var CommunicationSaleInfo $refilled */
        $refilled = $this->em->getRepository(CommunicationSaleInfo::class)->find($withoutToken->getId());
        $this->assertNotNull($refilled->getAccessToken());
        $this->assertSame(64, strlen($refilled->getAccessToken()));

        /** @var CommunicationSaleInfo $untouched */
        $untouched = $this->em->getRepository(CommunicationSaleInfo::class)->find($withToken->getId());
        $this->assertSame($originalToken, $untouched->getAccessToken());
    }

    public function testIsIdempotentAndReportsZeroWhenNothingToFill(): void
    {
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);
        $this->createSale($account, 'bftok3', 'ETC-bftok3');

        $tester = new CommandTester(new BackfillSaleAccessTokenCommand($this->em));
        $tester->execute([]);

        $this->assertStringContainsString('0', $tester->getDisplay());
    }

    public function testGeneratesDistinctTokensForEachBackfilledRow(): void
    {
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);

        $first = $this->createSale($account, 'bftok4', 'ETC-bftok4');
        $second = $this->createSale($account, 'bftok5', 'ETC-bftok5');
        $this->forceAccessTokenNull($first->getId());
        $this->forceAccessTokenNull($second->getId());
        $this->em->clear();

        (new CommandTester(new BackfillSaleAccessTokenCommand($this->em)))->execute([]);

        $this->em->clear();
        $repo = $this->em->getRepository(CommunicationSaleInfo::class);
        $tokenA = $repo->find($first->getId())->getAccessToken();
        $tokenB = $repo->find($second->getId())->getAccessToken();

        $this->assertNotNull($tokenA);
        $this->assertNotNull($tokenB);
        $this->assertNotSame($tokenA, $tokenB);
    }

    private function createSale(\App\Entity\Account $account, string $transactionId, string $transactionOrder): CommunicationSaleRecharge
    {
        $sale = (new CommunicationSaleRecharge())
            ->setTenant($account)
            ->setClientTransactionId('ctx-' . $transactionId)
            ->setTransactionId($transactionId)
            ->setTransactionOrder($transactionOrder)
            ->setAmount(20.0)
            ->setCurrency('USD')
            ->setTotalPrice(20.0)
            ->setState(CommunicationStateEnum::COMPLETED)
            ->setProvider('ETECSA')
            ->setPhoneNumber('5358831337');
        $this->em->persist($sale);
        $this->em->flush();

        return $sale;
    }

    private function forceAccessTokenNull(int $id): void
    {
        $this->em->getConnection()->executeStatement(
            'UPDATE communication_sale_info SET access_token = NULL WHERE id = :id',
            ['id' => $id]
        );
    }
}
