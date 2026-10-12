<?php

namespace App\Tests\Functional\Provider;

use App\Entity\ClientProviderRouting;
use App\Enums\CommunicationProviderEnum;
use App\Repository\ClientProviderRoutingRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * COMR-264 (C1): el routing entiende un área opcional. Sin área nada cambia: la clave de categoría es la de
 * siempre y no hace falta ningún backfill.
 *
 * @covers \App\Entity\ClientProviderRouting
 * @covers \App\Repository\ClientProviderRoutingRepository::findActiveRouteScopesForClient
 */
class ClientProviderRoutingAreaFunctionalTest extends ProviderFunctionalTestCase
{
    private function repository(): ClientProviderRoutingRepository
    {
        return self::getContainer()->get(ClientProviderRoutingRepository::class);
    }

    public function testARoutingWithoutAreaKeepsTheKeyOfToday(): void
    {
        $routing = $this->createRouting($this->createClient(), CommunicationProviderEnum::CSQ->value, serviceName: 'Mobile', subserviceName: 'AIRTIME');

        $this->assertNull($routing->getAreaName());
        $this->assertSame('Mobile|AIRTIME', $routing->getServiceKey());
    }

    public function testSetServiceCategoryWithTwoArgumentsStillWorksAndClearsTheArea(): void
    {
        $routing = (new ClientProviderRouting())->setServiceCategory('Mobile', 'AIRTIME', 'Retail');
        $routing->setServiceCategory('Mobile', 'AIRTIME');

        $this->assertNull($routing->getAreaName());
        $this->assertSame('Mobile|AIRTIME', $routing->getServiceKey());
    }

    public function testTheAreaIsPartOfTheKeyAndIsPersisted(): void
    {
        $routing = $this->createRouting($this->createClient(), CommunicationProviderEnum::CSQ->value, serviceName: 'Mobile', subserviceName: 'AIRTIME', areaName: 'Retail');
        $id = $routing->getId();
        $this->em->clear();

        $reloaded = $this->em->getRepository(ClientProviderRouting::class)->find($id);

        $this->assertSame('Retail', $reloaded->getAreaName());
        $this->assertSame('Mobile|AIRTIME|Retail', $reloaded->getServiceKey());
    }

    public function testTheProjectionExposesTheArea(): void
    {
        $client = $this->createClient();
        $this->createRouting($client, CommunicationProviderEnum::CSQ->value, serviceName: 'Mobile', subserviceName: 'AIRTIME', areaName: 'Retail');
        $this->createRouting($client, CommunicationProviderEnum::DTONE->value, saleType: 'sale');

        $rows = $this->repository()->findActiveRouteScopesForClient($client->getId());

        $this->assertSame('Retail', $rows[0]['areaName']);
        $this->assertNull($rows[1]['areaName']);
    }

    public function testTwoRowsOfTheSameScopeAndCategoryWithDifferentAreasCoexist(): void
    {
        $client = $this->createClient();

        $this->createRouting($client, CommunicationProviderEnum::CSQ->value, serviceName: 'Gift', subserviceName: 'Card', areaName: 'Gift Cards');
        $this->createRouting($client, CommunicationProviderEnum::DTONE->value, serviceName: 'Gift', subserviceName: 'Card', areaName: 'Retail');
        $this->createRouting($client, CommunicationProviderEnum::ETECSA->value, serviceName: 'Gift', subserviceName: 'Card');

        $this->assertCount(3, $this->repository()->findActiveRouteScopesForClient($client->getId()));
    }

    public function testTheDatabaseStillRejectsTwoActiveRowsForTheSameScopeCategoryAndArea(): void
    {
        $client = $this->createClient();
        $this->createRouting($client, CommunicationProviderEnum::CSQ->value, serviceName: 'Gift', subserviceName: 'Card', areaName: 'Retail');

        $this->expectException(UniqueConstraintViolationException::class);

        $this->createRouting($client, CommunicationProviderEnum::DTONE->value, serviceName: 'Gift', subserviceName: 'Card', areaName: 'Retail');
    }
}
