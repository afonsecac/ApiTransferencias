<?php

namespace App\Tests\Controller;

use App\Controller\DashboardUncertainSalesController;
use App\DTO\ResolveUncertainSaleDto;
use App\Entity\CommunicationSaleInfo;
use App\Entity\CommunicationSaleRecharge;
use App\Enums\CommunicationStateEnum;
use App\Service\UncertainSaleService;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * COMR-258: contrato HTTP de la pantalla «ventas que requieren revisión».
 *
 * @covers \App\Controller\DashboardUncertainSalesController
 */
class DashboardUncertainSalesControllerFunctionalTest extends ProviderFunctionalTestCase
{
    private function controller(): DashboardUncertainSalesController
    {
        $controller = new DashboardUncertainSalesController(self::getContainer()->get(UncertainSaleService::class));
        $controller->setContainer(self::getContainer());

        return $controller;
    }

    private function uncertainSale(): CommunicationSaleRecharge
    {
        $environment = $this->createEnvironment();
        $account = $this->createAccount($this->createClient(), $environment);
        $package = $this->createSellableV2Package($environment, 'ETECSA');
        $sale = (new CommunicationSaleRecharge())
            ->setTenant($account)
            ->setCatalogPackage($package)
            ->setPackageId($package->getId())
            ->setAmount(6.5)
            ->setTotalPrice(6.5)
            ->setCurrency('USD')
            ->setProvider('ETECSA')
            ->setClientTransactionId('ctl-unc-1')
            ->setTransactionId('261014000001')
            ->setPhoneNumber('5550000000')
            ->setDestinationAmount($package->getDestinationAmount())
            ->setDestinationCurrency($package->getDestinationCurrency())
            ->setState(CommunicationStateEnum::PENDING)
            ->setStateProcess(CommunicationSaleInfo::STATE_PROCESS_UNCERTAIN)
            ->setTransactionStatus(['outcome' => 'UNKNOWN', 'providerCode' => 'INTERNAL_PROVIDER_NOT_FOUND_UNCERTAIN', 'message' => 'Not found in the provider']);
        $this->em->persist($sale);
        $this->em->flush();

        return $sale;
    }

    public function testListShowsWhatTheReviewerNeedsToDecide(): void
    {
        $this->authenticateAsAdmin($this->createAdminUser());
        $sale = $this->uncertainSale();

        $response = $this->controller()->list(new Request(['limit' => '50']));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $row = null;
        foreach ($data['results'] as $candidate) {
            if ($candidate['id'] === $sale->getId()) {
                $row = $candidate;
            }
        }
        $this->assertNotNull($row, 'La venta incierta aparece en el listado.');
        $this->assertSame('ETECSA', $row['provider']);
        $this->assertSame('261014000001', $row['transactionId']);
        $this->assertSame(6.5, $row['reservedAmount']);
        $this->assertSame('USD', $row['currency']);
        $this->assertSame('INTERNAL_PROVIDER_NOT_FOUND_UNCERTAIN', $row['lastStatus']['code']);
        $this->assertSame('Not found in the provider', $row['reason']);
        $this->assertArrayHasKey('ageMinutes', $row);
        $this->assertArrayHasKey('hasNext', $data);
    }

    public function testRecheckAnswersThatTheCheckWasQueued(): void
    {
        $this->authenticateAsAdmin($this->createAdminUser());
        $sale = $this->uncertainSale();

        $response = $this->controller()->recheck($sale->getId());

        $this->assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
        $this->assertTrue(json_decode($response->getContent(), true)['queued']);
    }

    public function testResolveRecordsTheLoggedUserAndAnswersTheNewState(): void
    {
        $admin = $this->createAdminUser();
        $this->authenticateAsAdmin($admin);
        $sale = $this->uncertainSale();

        $response = $this->controller()->resolve($sale->getId(), new ResolveUncertainSaleDto('rejected', 'El proveedor no la conoce tras revisarlo'));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('Rejected', $data['state']);
        $this->em->clear();
        $history = $this->em->getRepository(\App\Entity\CommunicationSaleHistory::class)->findBy(['sale' => $sale->getId()]);
        $this->assertStringContainsString($admin->getEmail(), json_encode(array_map(static fn ($h): array => $h->getInfo(), $history)));
    }

    public function testResolveOfASaleThatIsNotUncertainAnswersConflictAsJson(): void
    {
        $this->authenticateAsAdmin($this->createAdminUser());
        $sale = $this->uncertainSale();
        $this->controller()->resolve($sale->getId(), new ResolveUncertainSaleDto('completed', 'Confirmada por el proveedor'));

        $response = $this->controller()->resolve($sale->getId(), new ResolveUncertainSaleDto('rejected', 'Otro intento de resolverla'));

        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        $this->assertArrayHasKey('error', json_decode($response->getContent(), true));
    }

    public function testRecheckOfAnUnknownSaleAnswersNotFound(): void
    {
        $this->authenticateAsAdmin($this->createAdminUser());

        $response = $this->controller()->recheck(999999999);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }
}
