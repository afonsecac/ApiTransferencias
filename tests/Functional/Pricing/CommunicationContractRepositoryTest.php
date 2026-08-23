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

    private function communicationPackage(float $amount = 500.0): CommunicationPackage
    {
        self::$counter++;

        $package = (new CommunicationPackage())
            ->setName("Paquete {$this::$counter}")
            ->setDescription("Paquete {$this::$counter}")
            ->setDestinationAmount($amount)
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
            ->addPackage($package)
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

    public function testFindActiveForTenantEagerLoadsAllPackagesOfAContractSharedByTwo(): void
    {
        $now = new \DateTimeImmutable();
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);

        // Dos CommunicationPackage al mismo monto, un solo contrato (ver
        // upsertContract() — Fase 6B): el fetch join no debe perder ninguno.
        $packageA = $this->communicationPackage();
        $packageB = $this->communicationPackage();
        $contract = (new CommunicationContract())
            ->addPackage($packageA)
            ->addPackage($packageB)
            ->setTenant($account)
            ->setDestinationAmount(500.0)
            ->setDestinationCurrency('CUP')
            ->setPrice(10.0)
            ->setCurrency('USD')
            ->setStartAt($now->modify('-1 day'));
        $this->em->persist($contract);
        $this->em->flush();
        $this->em->clear();

        $result = $this->repository()->findActiveForTenant($account, $now);

        $this->assertCount(1, $result);
        $this->assertCount(2, $result[0]->getPackages());
    }

    public function testFindOpenContractMatchesByTupleWithFloatingPointTolerance(): void
    {
        $now = new \DateTimeImmutable();
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);

        $package = $this->communicationPackage();
        $open = $this->contract($package, $account, $now->modify('-1 day'));
        $this->em->flush();

        $found = $this->repository()->findOpenContract($account, 500.0 + 0.001, 'cup', '|');

        $this->assertSame($open->getId(), $found?->getId());
    }

    public function testFindOpenContractReturnsNullWhenNoOpenContractMatches(): void
    {
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);

        $this->assertNull($this->repository()->findOpenContract($account, 999.0, 'CUP', '|'));
    }

    public function testFindOpenContractDistinguishesTenantFromDefault(): void
    {
        $now = new \DateTimeImmutable();
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);

        $package = $this->communicationPackage();
        $default = $this->contract($package, null, $now->modify('-1 day'));
        $this->em->flush();

        $this->assertSame($default->getId(), $this->repository()->findOpenContract(null, 500.0, 'CUP', '|')?->getId());
        $this->assertNull($this->repository()->findOpenContract($account, 500.0, 'CUP', '|'));
    }

    // ---- Fase 3: service_key es parte de la identidad del contrato ----

    public function testTwoOpenContractsCoexistForTheSameTupleWhenCategoriesDiffer(): void
    {
        // La regresión central que compra la Fase 3: antes, el índice único
        // uniq_com_contract_open_per_tenant_amount solo cubría (tenant,
        // monto, moneda) — dos contratos abiertos para la misma tupla
        // habrían violado esa restricción sin importar la categoría. Ahora
        // que service_key es parte del índice, coexisten sin conflicto.
        $now = new \DateTimeImmutable();
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);

        $mobilePackage = $this->communicationPackage();
        $mobilePackage->setService(['name' => 'Mobile', 'subservice' => ['name' => 'AIRTIME']]);
        $mobileContract = $this->contract($mobilePackage, $account, $now->modify('-1 day'))
            ->setServiceCategory('Mobile', 'AIRTIME');

        $utilitiesPackage = $this->communicationPackage();
        $utilitiesPackage->setService(['name' => 'Utilities', 'subservice' => ['name' => 'INTERNET']]);
        $utilitiesContract = $this->contract($utilitiesPackage, $account, $now->modify('-1 day'))
            ->setServiceCategory('Utilities', 'INTERNET');

        // Misma tupla monto/moneda a propósito — es justo el caso que antes
        // habría violado el índice único.
        $utilitiesContract->setDestinationAmount((float) $mobileContract->getDestinationAmount());
        $utilitiesContract->setDestinationCurrency((string) $mobileContract->getDestinationCurrency());

        $this->em->flush(); // No debe lanzar UniqueConstraintViolationException.

        $this->assertNotNull($mobileContract->getId());
        $this->assertNotNull($utilitiesContract->getId());
        $this->assertNotSame($mobileContract->getId(), $utilitiesContract->getId());
    }

    public function testFindOpenContractDistinguishesByCategoryOnTheSameTuple(): void
    {
        $now = new \DateTimeImmutable();
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);

        $mobilePackage = $this->communicationPackage();
        $mobilePackage->setService(['name' => 'Mobile', 'subservice' => ['name' => 'AIRTIME']]);
        $mobileContract = $this->contract($mobilePackage, $account, $now->modify('-1 day'))
            ->setServiceCategory('Mobile', 'AIRTIME');

        $utilitiesPackage = $this->communicationPackage();
        $utilitiesPackage->setService(['name' => 'Utilities', 'subservice' => ['name' => 'INTERNET']]);
        $utilitiesContract = $this->contract($utilitiesPackage, $account, $now->modify('-1 day'))
            ->setServiceCategory('Utilities', 'INTERNET');
        $utilitiesContract->setDestinationAmount((float) $mobileContract->getDestinationAmount());
        $utilitiesContract->setDestinationCurrency((string) $mobileContract->getDestinationCurrency());
        $this->em->flush();

        $amount = (float) $mobileContract->getDestinationAmount();
        $currency = (string) $mobileContract->getDestinationCurrency();

        $foundMobile = $this->repository()->findOpenContract($account, $amount, $currency, 'Mobile|AIRTIME');
        $foundUtilities = $this->repository()->findOpenContract($account, $amount, $currency, 'Utilities|INTERNET');

        $this->assertSame($mobileContract->getId(), $foundMobile?->getId());
        $this->assertSame($utilitiesContract->getId(), $foundUtilities?->getId());
    }

    public function testFindActiveTenantContractsForPackageReturnsOnlyContractsCoveringThatPackage(): void
    {
        $now = new \DateTimeImmutable();
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);

        $covered = $this->communicationPackage(500.0);
        $notCovered = $this->communicationPackage(600.0);
        $covering = $this->contract($covered, $account, $now->modify('-1 day'));
        $this->contract($notCovered, $account, $now->modify('-1 day'));
        $this->em->flush();

        $result = $this->repository()->findActiveTenantContractsForPackage($covered, $now);

        $this->assertCount(1, $result);
        $this->assertSame($covering->getId(), $result[0]->getId());
    }

    public function testFindActiveTenantContractsForPackageExcludesDefaultContracts(): void
    {
        $now = new \DateTimeImmutable();
        $package = $this->communicationPackage();
        // Contrato "por defecto" (tenant NULL) cubriendo el mismo paquete —
        // no debe aparecer, solo contratos propios de tenant.
        $this->contract($package, null, $now->modify('-1 day'));
        $this->em->flush();

        $this->assertSame([], $this->repository()->findActiveTenantContractsForPackage($package, $now));
    }

    public function testFindActiveTenantContractsForPackageExcludesExpiredContracts(): void
    {
        $now = new \DateTimeImmutable();
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);

        $package = $this->communicationPackage();
        $this->contract($package, $account, $now->modify('-2 days'), $now->modify('-1 day'));
        $this->em->flush();

        $this->assertSame([], $this->repository()->findActiveTenantContractsForPackage($package, $now));
    }
}
