<?php

namespace App\Tests\Service\Pricing;

use App\Entity\Account;
use App\Entity\Client;
use App\Entity\Environment;
use App\Service\Pricing\TargetAccountResolver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Service\Pricing\TargetAccountResolver
 *
 * Extraído de CommunicationPromotionService::createPackagesForPromotion(),
 * que ya implementaba este criterio para promociones — se cubre aquí como
 * pieza independiente porque PackageContractService (modo "rate") también
 * lo consume.
 */
class TargetAccountResolverTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private TargetAccountResolver $resolver;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->resolver = new TargetAccountResolver($this->em);
    }

    private function accountWithClient(int $accountId, ?int $clientId): Account&MockObject
    {
        $account = $this->createMock(Account::class);
        $account->method('getId')->willReturn($accountId);

        if ($clientId === null) {
            $account->method('getClient')->willReturn(null);
        } else {
            $client = $this->createMock(Client::class);
            $client->method('getId')->willReturn($clientId);
            $account->method('getClient')->willReturn($client);
        }

        return $account;
    }

    public function testEmptyClientIdsReturnsAllActiveAccountsOfTheEnvironment(): void
    {
        $environment = $this->createMock(Environment::class);
        $accounts = [
            $this->accountWithClient(1, 10),
            $this->accountWithClient(2, 20),
        ];

        $repo = $this->createMock(EntityRepository::class);
        $repo->expects($this->once())
            ->method('findBy')
            ->with(['environment' => $environment, 'isActive' => true])
            ->willReturn($accounts);
        $this->em->method('getRepository')->with(Account::class)->willReturn($repo);

        $result = $this->resolver->resolve($environment, []);

        $this->assertSame($accounts, $result);
    }

    public function testNonEmptyClientIdsFiltersToOnlyThoseAccounts(): void
    {
        $environment = $this->createMock(Environment::class);
        $wanted = $this->accountWithClient(1, 10);
        $unwanted = $this->accountWithClient(2, 20);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')->willReturn([$wanted, $unwanted]);
        $this->em->method('getRepository')->with(Account::class)->willReturn($repo);

        $result = $this->resolver->resolve($environment, [10]);

        $this->assertSame([$wanted], array_values($result));
    }

    public function testAccountWithoutClientIsNeverMatchedByAFilter(): void
    {
        $environment = $this->createMock(Environment::class);
        $orphan = $this->accountWithClient(1, null);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')->willReturn([$orphan]);
        $this->em->method('getRepository')->with(Account::class)->willReturn($repo);

        $result = $this->resolver->resolve($environment, [10]);

        $this->assertSame([], $result);
    }

    public function testResultIsAlwaysAListEvenAfterFiltering(): void
    {
        // array_filter() preserva claves — resolve() debe reindexar, si no
        // json_encode() de una respuesta HTTP con huecos de índice
        // produciría un objeto en vez de un array.
        $environment = $this->createMock(Environment::class);
        $accounts = [
            $this->accountWithClient(1, 10),
            $this->accountWithClient(2, 20),
            $this->accountWithClient(3, 30),
        ];

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')->willReturn($accounts);
        $this->em->method('getRepository')->with(Account::class)->willReturn($repo);

        $result = $this->resolver->resolve($environment, [30]);

        $this->assertSame([0], array_keys($result));
    }

    public function testResolveOneReturnsTheMatchingActiveAccount(): void
    {
        $environment = $this->createMock(Environment::class);
        $wanted = $this->accountWithClient(1, 10);
        $other = $this->accountWithClient(2, 20);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')->willReturn([$wanted, $other]);
        $this->em->method('getRepository')->with(Account::class)->willReturn($repo);

        $result = $this->resolver->resolveOne($environment, 10);

        $this->assertSame($wanted, $result);
    }

    public function testResolveOneReturnsNullWhenClientHasNoActiveAccountInEnvironment(): void
    {
        $environment = $this->createMock(Environment::class);
        $other = $this->accountWithClient(2, 20);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')->willReturn([$other]);
        $this->em->method('getRepository')->with(Account::class)->willReturn($repo);

        $result = $this->resolver->resolveOne($environment, 999);

        $this->assertNull($result);
    }
}
