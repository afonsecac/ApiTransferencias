<?php

namespace App\Tests\Functional\Pricing;

use App\Controller\DashboardCommunicationContractsController;
use App\Entity\CommunicationContract;
use App\Entity\CommunicationPackage;
use App\Service\Pricing\CommunicationContractService;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \App\Controller\DashboardCommunicationContractsController
 *
 * Contra Postgres real — a propósito: list() (Fase 6C) dejó de hacer
 * fetch-join de `packages` en la consulta paginada porque Doctrine no puede
 * aplicar setMaxResults() de forma fiable sobre una fetch-join de colección
 * to-many (un contrato con 2 paquetes agrega 2 filas SQL y corre la
 * paginación). Este test es el único que ejercita ese camino end-to-end
 * contra SQL real — un mock de EntityManager no puede validar el fan-out.
 */
class DashboardCommunicationContractsControllerListTest extends ProviderFunctionalTestCase
{
    private static int $counter = 0;

    private function controller(): DashboardCommunicationContractsController
    {
        $controller = new DashboardCommunicationContractsController(
            $this->em,
            self::getContainer()->get(CommunicationContractService::class),
        );

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $controller->setContainer($container);

        return $controller;
    }

    private function communicationPackage(string $name): CommunicationPackage
    {
        self::$counter++;

        $package = (new CommunicationPackage())
            ->setName($name)
            ->setDescription($name)
            ->setDestinationAmount(500.0)
            ->setDestinationCurrency('CUP');

        $this->em->persist($package);

        return $package;
    }

    public function testListDoesNotSkipEntriesWhenAContractInThePageSharesTwoPackages(): void
    {
        $packageA = $this->communicationPackage('Catálogo normal');
        $packageB = $this->communicationPackage('Promo verano');
        $shared = (new CommunicationContract())
            ->addPackage($packageA)
            ->addPackage($packageB)
            ->setDestinationAmount(500.0)
            ->setDestinationCurrency('CUP')
            ->setPrice(10.0)
            ->setCurrency('USD');
        $this->em->persist($shared);

        // Un segundo contrato, sin paquetes compartidos, para confirmar que
        // la paginación sigue trayendo AMBOS resultados (2, no 1) aunque el
        // primero haya aportado 2 filas SQL.
        $other = (new CommunicationContract())
            ->addPackage($this->communicationPackage('Otro paquete'))
            ->setDestinationAmount(700.0)
            ->setDestinationCurrency('CUP')
            ->setPrice(20.0)
            ->setCurrency('USD');
        $this->em->persist($other);
        $this->em->flush();
        $this->em->clear();

        $response = $this->controller()->list(new Request(['limit' => '10']));
        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data['results']);
        $this->assertFalse($data['hasNext']);

        $sharedResult = null;
        foreach ($data['results'] as $result) {
            if ($result['id'] === $shared->getId()) {
                $sharedResult = $result;
            }
        }
        $this->assertNotNull($sharedResult, 'El contrato compartido debe estar en la página.');
        $this->assertCount(2, $sharedResult['packages']);
        $this->assertSame(
            ['Catálogo normal', 'Promo verano'],
            array_map(static fn (array $p) => $p['name'], $sharedResult['packages']),
        );
    }

    public function testListFiltersByEitherPackageOfASharedContract(): void
    {
        $packageA = $this->communicationPackage('Catálogo normal');
        $packageB = $this->communicationPackage('Promo verano');
        $shared = (new CommunicationContract())
            ->addPackage($packageA)
            ->addPackage($packageB)
            ->setDestinationAmount(500.0)
            ->setDestinationCurrency('CUP')
            ->setPrice(10.0)
            ->setCurrency('USD');
        $this->em->persist($shared);
        $this->em->flush();
        $this->em->clear();

        $response = $this->controller()->list(new Request(['communicationPackageId' => (string) $packageB->getId()]));
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['results']);
        $this->assertSame($shared->getId(), $data['results'][0]['id']);
    }
}
