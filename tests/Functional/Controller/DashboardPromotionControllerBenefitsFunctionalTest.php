<?php

namespace App\Tests\Functional\Controller;

use App\Controller\DashboardPromotionController;
use App\DTO\CreatePromotionV2Dto;
use App\DTO\UpdatePromotionDto;
use App\Repository\CommunicationPackageRepository;
use App\Repository\CommunicationPromotionsRepository;
use App\Service\CommunicationPromotionService;
use App\Service\Pricing\CommunicationContractService;
use App\Service\Pricing\CommunicationPromotionBindingService;
use App\Service\Pricing\CommunicationPromotionEquivalenceService;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @covers \App\Controller\DashboardPromotionController::show
 * @covers \App\Controller\DashboardPromotionController::update
 * @covers \App\Service\CommunicationPromotionService::getPackageBenefits
 * @covers \App\Service\CommunicationPromotionService::updatePackageBenefits
 *
 * Contra Postgres real, a propósito: la parte que importa (que
 * CommunicationPackageAdminService::applyCreditBenefitDefaults() recalcule
 * additional_information/amount.base contra el destinationAmount PROPIO de
 * cada paquete, no solo copie el array recibido) solo se puede confirmar con
 * varios CommunicationPackage reales de montos distintos — un mock de
 * CommunicationPackageAdminService::update() no ejercitaría esa lógica.
 */
class DashboardPromotionControllerBenefitsFunctionalTest extends ProviderFunctionalTestCase
{
    private function controller(): DashboardPromotionController
    {
        $controller = new DashboardPromotionController(
            self::getContainer()->get(CommunicationPromotionsRepository::class),
            $this->em,
            self::getContainer()->get(NormalizerInterface::class),
            self::getContainer()->get(CommunicationPromotionService::class),
            self::getContainer()->get(CommunicationPromotionBindingService::class),
            self::getContainer()->get(CommunicationPromotionEquivalenceService::class),
            self::getContainer()->get(CommunicationContractService::class),
        );

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $controller->setContainer($container);

        return $controller;
    }

    public function testShowExposesTheBenefitsOfALinkedPackageForAV2Promotion(): void
    {
        $environment = $this->createEnvironment();

        /** @var CommunicationPromotionService $promotionService */
        $promotionService = self::getContainer()->get(CommunicationPromotionService::class);
        $result = $promotionService->createV2(new CreatePromotionV2Dto(
            name: 'Sextuplica',
            description: 'Sextuplica',
            packageNameTemplate: 'Promo {monto}',
            packageDescriptionTemplate: 'Promo {monto}',
            startAt: (new \DateTimeImmutable('-1 day'))->format('c'),
            endAt: (new \DateTimeImmutable('+5 days'))->format('c'),
            environmentId: $environment->getId(),
            destinationCurrency: 'CUP',
            amountFrom: 100.0,
            amountTo: 100.0,
            amountStep: 1.0,
            benefits: [[
                'type' => 'CREDITS', 'unit' => 'CUP', 'unit_type' => 'CURRENCY',
                'operation' => 'MULTIPLY', 'value' => 6,
            ]],
        ));
        $this->em->clear();

        $response = $this->controller()->show($result->promotion->getId());
        $data = json_decode($response->getContent(), true);

        $this->assertSame('MULTIPLY', $data['benefits'][0]['operation']);
        $this->assertSame(6, $data['benefits'][0]['value']);
        // Symfony serializa isV2() como "v2" por convención (strip "is" +
        // lowercase primera letra) salvo que se fuerce el nombre — el
        // frontend depende de la clave EXACTA "isV2" (promotion-list y
        // promotion-form la leen así) para decidir qué UI mostrar.
        $this->assertArrayHasKey('isV2', $data, 'isV2 debe serializarse bajo esa clave exacta, no "v2"');
        $this->assertTrue($data['isV2']);
    }

    public function testUpdatePropagatesNewBenefitsToEveryPackageAndRecalculatesEachOwnAmount(): void
    {
        $environment = $this->createEnvironment();

        /** @var CommunicationPromotionService $promotionService */
        $promotionService = self::getContainer()->get(CommunicationPromotionService::class);
        $result = $promotionService->createV2(new CreatePromotionV2Dto(
            name: 'Rango de montos',
            description: 'Rango de montos',
            packageNameTemplate: 'Promo {monto}',
            packageDescriptionTemplate: 'Promo {monto}',
            startAt: (new \DateTimeImmutable('-1 day'))->format('c'),
            endAt: (new \DateTimeImmutable('+5 days'))->format('c'),
            environmentId: $environment->getId(),
            destinationCurrency: 'CUP',
            // 3 paquetes: 100, 200, 300 CUP.
            amountFrom: 100.0,
            amountTo: 300.0,
            amountStep: 100.0,
            benefits: [[
                'type' => 'CREDITS', 'unit' => 'CUP', 'unit_type' => 'CURRENCY',
                'amount' => ['base' => 0, 'promotion_bonus' => 0],
            ]],
        ));
        $promotionId = $result->promotion->getId();
        $this->em->clear();

        $newBenefits = [[
            'type' => 'CREDITS', 'unit' => 'CUP', 'unit_type' => 'CURRENCY',
            'operation' => 'MULTIPLY', 'value' => 6,
        ]];

        $response = $this->controller()->update($promotionId, new UpdatePromotionDto(benefits: $newBenefits));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('MULTIPLY', $data['benefits'][0]['operation']);

        $this->em->clear();
        /** @var CommunicationPackageRepository $packageRepository */
        $packageRepository = self::getContainer()->get(CommunicationPackageRepository::class);
        $packages = $packageRepository->createQueryBuilder('p')
            ->andWhere('p.promotion = :promo')
            ->setParameter('promo', $promotionId)
            ->orderBy('p.destinationAmount', 'ASC')
            ->getQuery()
            ->getResult();

        $this->assertCount(3, $packages);
        // Cada paquete recalculó amount.base contra SU PROPIO destinationAmount
        // (100/200/300), no copió un valor fijo del array recibido — prueba
        // que se reutilizó CommunicationPackageAdminService::update() por
        // paquete (applyCreditBenefitDefaults()), no un setBenefits() a ciegas.
        $expectedBase = [100 => 100, 200 => 200, 300 => 300];
        foreach ($packages as $package) {
            $benefit = $package->getBenefits()[0];
            $this->assertSame('MULTIPLY', $benefit['operation']);
            $this->assertSame($expectedBase[(int) $package->getDestinationAmount()], $benefit['amount']['base']);
        }
    }

    public function testUpdateWithoutBenefitsKeyLeavesExistingPackageBenefitsUntouched(): void
    {
        $environment = $this->createEnvironment();

        /** @var CommunicationPromotionService $promotionService */
        $promotionService = self::getContainer()->get(CommunicationPromotionService::class);
        $result = $promotionService->createV2(new CreatePromotionV2Dto(
            name: 'Sin cambios de beneficios',
            description: 'Sin cambios de beneficios',
            packageNameTemplate: 'Promo {monto}',
            packageDescriptionTemplate: 'Promo {monto}',
            startAt: (new \DateTimeImmutable('-1 day'))->format('c'),
            endAt: (new \DateTimeImmutable('+5 days'))->format('c'),
            environmentId: $environment->getId(),
            destinationCurrency: 'CUP',
            amountFrom: 150.0,
            amountTo: 150.0,
            amountStep: 1.0,
            benefits: [[
                'type' => 'CREDITS', 'unit' => 'CUP', 'unit_type' => 'CURRENCY',
                'operation' => 'ADD', 'value' => 50,
            ]],
        ));
        $promotionId = $result->promotion->getId();
        $this->em->clear();

        // Solo cambia el name — sin `benefits` en el DTO.
        $response = $this->controller()->update($promotionId, new UpdatePromotionDto(name: 'Renombrada'));

        $this->assertSame(200, $response->getStatusCode());

        $this->em->clear();
        /** @var CommunicationPackageRepository $packageRepository */
        $packageRepository = self::getContainer()->get(CommunicationPackageRepository::class);
        $packages = $packageRepository->createQueryBuilder('p')
            ->andWhere('p.promotion = :promo')
            ->setParameter('promo', $promotionId)
            ->getQuery()
            ->getResult();

        $this->assertSame('ADD', $packages[0]->getBenefits()[0]['operation']);
        $this->assertSame(50, $packages[0]->getBenefits()[0]['value']);
    }
}
