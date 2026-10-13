<?php

namespace App\Tests\Controller;

use App\Controller\DashboardSalesController;
use App\DTO\RetrySaleDto;
use App\Entity\CommunicationSaleInfo;
use App\Entity\CommunicationSalePackage;
use App\Enums\CommunicationStateEnum;
use App\Service\CommunicationSaleService;
use App\Service\Etecsa\EtecsaRetryService;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * COMR-257: el reintento de una venta incierta no puede ser un reenvío a ciegas. Si el proveedor sí la
 * procesó, reenviarla cobraría dos veces; solo el reintento seguro de ETECSA queda permitido.
 *
 * @covers \App\Controller\DashboardSalesController::retry
 */
class DashboardSalesControllerRetryUncertainFunctionalTest extends ProviderFunctionalTestCase
{
    private function controller(): DashboardSalesController
    {
        $controller = new DashboardSalesController(
            $this->em,
            self::getContainer()->get(NormalizerInterface::class),
            self::getContainer()->get(CommunicationSaleService::class),
            self::getContainer()->get(MessageBusInterface::class),
            self::getContainer()->get(EtecsaRetryService::class),
        );
        $controller->setContainer(self::getContainer());

        return $controller;
    }

    private function uncertainPackageSale(): CommunicationSalePackage
    {
        $account = $this->createAccount($this->createClient(), $this->createEnvironment());
        $sale = (new CommunicationSalePackage())
            ->setTenant($account)
            ->setClientTransactionId('uncertain-pkg-1')
            ->setAmount(10.0)
            ->setCurrency('USD')
            ->setTotalPrice(10.0)
            ->setState(CommunicationStateEnum::PENDING)
            ->setProvider('CSQ')
            ->setIdentificationNumber('12345')
            ->setName('Cliente de prueba');
        $sale->markUncertain();
        $this->em->persist($sale);
        $this->em->flush();

        return $sale;
    }

    public function testRetryOfAnUncertainSaleWithoutASafeRetryIsRefusedAndNothingIsResent(): void
    {
        $this->authenticateAsAdmin($this->createAdminUser());
        $sale = $this->uncertainPackageSale();

        $response = $this->controller()->retry($sale->getId(), new RetrySaleDto());

        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        $this->em->clear();
        $reloaded = $this->em->getRepository(CommunicationSaleInfo::class)->find($sale->getId());
        $this->assertSame(CommunicationSaleInfo::STATE_PROCESS_UNCERTAIN, $reloaded->getStateProcess(), 'La venta no se devuelve a CREATED ni se reenvía.');
    }
}
