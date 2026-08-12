<?php

namespace App\Tests\Functional\Pricing;

use App\Entity\Account;
use App\Entity\CommunicationContract;
use App\Entity\CommunicationPackage;
use App\Repository\CommunicationContractRepository;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;

/**
 * @covers \App\Repository\CommunicationContractRepository
 *
 * Reutiliza los fixtures de cuenta/cliente de ProviderFunctionalTestCase
 * (Account real, no mock) — CommunicationContract::$tenant es Account.
 */
class CommunicationContractRepositoryTest extends ProviderFunctionalTestCase
{
    private static int $counter = 0;

    private function repository(): CommunicationContractRepository
    {
        return self::getContainer()->get(CommunicationContractRepository::class);
    }

    private function communicationPackage(): CommunicationPackage
    {
        self::$counter++;

        $package = (new CommunicationPackage())
            ->setName("Paquete {$this::$counter}")
            ->setDescription("Paquete {$this::$counter}")
            ->setDestinationAmount(500.0)
            ->setDestinationCurrency('CUP');

        $this->em->persist($package);

        return $package;
    }

    private function contract(
        CommunicationPackage $package,
        ?Account $tenant,
        \DateTimeImmutable $startAt,
        ?\DateTimeImmutable $endAt = null,
        float $price = 10.0,
    ): CommunicationContract {
        $contract = (new CommunicationContract())
            ->setCommunicationPackage($package)
            ->setTenant($tenant)
            ->setDestinationAmount($package->getDestinationAmount())
            ->setDestinationCurrency($package->getDestinationCurrency())
            ->setPrice($price)
            ->setCurrency('USD')
            ->setStartAt($startAt);

        if ($endAt !== null) {
            $contract->setEndAt($endAt);
        }

        $this->em->persist($contract);

        return $contract;
    }

    public function testFindActiveForTenantReturnsOnlyThatTenantsOpenContracts(): void
    {
        $now = new \DateTimeImmutable();
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        // unique_environment_by_client exige (environment, client) único por
        // cuenta: un segundo entorno para la otra cuenta del mismo cliente.
        $otherEnvironment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);
        $otherAccount = $this->createAccount($client, $otherEnvironment);

        $package = $this->communicationPackage();
        $own = $this->contract($package, $account, $now->modify('-1 day'));
        $this->contract($package, $otherAccount, $now->modify('-1 day'));
        $this->em->flush();

        $result = $this->repository()->findActiveForTenant($account, $now);

        $this->assertCount(1, $result);
        $this->assertSame($own->getId(), $result[0]->getId());
    }

    public function testFindActiveForTenantExcludesExpiredContracts(): void
    {
        $now = new \DateTimeImmutable();
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);

        $package = $this->communicationPackage();
        $this->contract($package, $account, $now->modify('-2 days'), $now->modify('-1 day'));
        $this->em->flush();

        $this->assertSame([], $this->repository()->findActiveForTenant($account, $now));
    }

    public function testFindActiveForTenantExcludesContractsNotYetStarted(): void
    {
        $now = new \DateTimeImmutable();
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);

        $package = $this->communicationPackage();
        $this->contract($package, $account, $now->modify('+1 day'));
        $this->em->flush();

        $this->assertSame([], $this->repository()->findActiveForTenant($account, $now));
    }

    public function testFindActiveForTenantDoesNotReturnDefaultContracts(): void
    {
        $now = new \DateTimeImmutable();
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);

        $package = $this->communicationPackage();
        // Contrato "por defecto" (tenant NULL): findActiveForTenant() no debe traerlo.
        $this->contract($package, null, $now->modify('-1 day'));
        $this->em->flush();

        $this->assertSame([], $this->repository()->findActiveForTenant($account, $now));
    }

    public function testFindActiveDefaultsReturnsOnlyTenantNullContracts(): void
    {
        $now = new \DateTimeImmutable();
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);

        $package = $this->communicationPackage();
        $default = $this->contract($package, null, $now->modify('-1 day'));
        $this->contract($package, $account, $now->modify('-1 day'));
        $this->em->flush();

        $result = $this->repository()->findActiveDefaults($now);

        $this->assertCount(1, $result);
        $this->assertSame($default->getId(), $result[0]->getId());
    }

    public function testTieBreakPicksMostRecentContractFirst(): void
    {
        $now = new \DateTimeImmutable();
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);

        $package = $this->communicationPackage();
        // uniq_com_contract_open_per_tenant_package prohíbe dos contratos
        // ABIERTOS (endAt NULL) simultáneos del mismo (tenant, paquete) — el
        // más viejo se cierra en el futuro lejano (sigue vigente ahora) para
        // poder tener dos contratos vigentes a la vez sin violarla.
        $older = $this->contract($package, $account, $now->modify('-10 days'), $now->modify('+30 days'), price: 5.0);
        $newer = $this->contract($package, $account, $now->modify('-1 day'), null, price: 8.0);
        $this->em->flush();

        $result = $this->repository()->findActiveForTenant($account, $now);

        // Ambos contratos están vigentes (sin endAt) — el más reciente
        // (mayor startAt) va primero, ya que es el que debería ganar en
        // PackageCatalogResolver (Fase 2).
        $this->assertCount(2, $result);
        $this->assertSame($newer->getId(), $result[0]->getId());
        $this->assertSame($older->getId(), $result[1]->getId());
    }
}
