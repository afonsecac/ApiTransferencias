<?php

namespace App\Tests\Service;

use App\DTO\CreateCommunicationPackageBatchDto;
use App\DTO\CreatePromotionV2Dto;
use App\DTO\UpdateCommunicationPackageDto;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationPromotions;
use App\Entity\Environment;
use App\Exception\MyCurrentException;
use App\Repository\CommunicationPackageRepository;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use App\Service\CommunicationPromotionService;
use App\Service\Pricing\CommunicationContractService;
use App\Service\Pricing\CommunicationPackageAdminService;
use App\Service\Pricing\CommunicationPromotionEquivalenceService;
use App\Service\Pricing\PromotionEquivalenceResult;
use App\Service\Pricing\TargetAccountResolver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @covers \App\Service\CommunicationPromotionService::createV2
 *
 * Cubre la generación de CommunicationPackage por rango para una promoción
 * V2 (Fase 5B) — a diferencia de createPackagesForPromotion() (legacy), no
 * depende de un producto de origen ni genera nada por cliente.
 */
class CommunicationPromotionServiceV2Test extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private CommunicationPackageAdminService&MockObject $packageAdminService;
    private CommunicationContractService&MockObject $contractService;
    private CommunicationPromotionEquivalenceService&MockObject $equivalenceService;
    private CommunicationPackageRepository&MockObject $packageRepository;
    private CommunicationPromotionService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->packageAdminService = $this->createMock(CommunicationPackageAdminService::class);
        $this->contractService = $this->createMock(CommunicationContractService::class);
        $this->equivalenceService = $this->createMock(CommunicationPromotionEquivalenceService::class);
        $this->packageRepository = $this->createMock(CommunicationPackageRepository::class);

        $this->service = new CommunicationPromotionService(
            $this->em,
            $this->createMock(Security::class),
            $this->createMock(ParameterBagInterface::class),
            $this->createMock(MailerInterface::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(UserPasswordHasherInterface::class),
            $this->createMock(EnvironmentRepository::class),
            $this->createMock(SysConfigRepository::class),
            $this->createMock(SerializerInterface::class),
            $this->createMock(MessageBusInterface::class),
            $this->createMock(TargetAccountResolver::class),
            $this->packageAdminService,
            $this->contractService,
            $this->equivalenceService,
            $this->packageRepository,
        );
    }

    private function dto(?int $environmentId = 4): CreatePromotionV2Dto
    {
        return new CreatePromotionV2Dto(
            name: '24h Datos Ilimitados',
            description: 'Réplica DTOne',
            packageNameTemplate: 'Cubacel {monto} CUP',
            packageDescriptionTemplate: 'Cubacel {monto} CUP',
            startAt: '2026-08-18T00:00:00+00:00',
            endAt: '2026-08-25T23:59:00+00:00',
            environmentId: $environmentId,
            destinationCurrency: 'CUP',
            amountFrom: 500.0,
            amountTo: 1250.0,
            amountStep: 25.0,
        );
    }

    public function testThrowsWhenEnvironmentDoesNotExist(): void
    {
        $envRepo = $this->createMock(EntityRepository::class);
        $envRepo->method('find')->willReturn(null);
        $this->em->method('getRepository')->with(Environment::class)->willReturn($envRepo);

        $this->expectException(MyCurrentException::class);

        $this->service->createV2($this->dto());
    }

    public function testGeneratesPackagesAndContractsForTheRange(): void
    {
        $environment = $this->createMock(Environment::class);
        $envRepo = $this->createMock(EntityRepository::class);
        $envRepo->method('find')->with(4)->willReturn($environment);
        $this->em->method('getRepository')->with(Environment::class)->willReturn($envRepo);

        $packages = [
            (new CommunicationPackage())->setName('p1')->setDescription('p1')->setDestinationAmount(500.0)->setDestinationCurrency('CUP'),
            (new CommunicationPackage())->setName('p2')->setDescription('p2')->setDestinationAmount(525.0)->setDestinationCurrency('CUP'),
        ];

        $this->packageAdminService->expects($this->once())
            ->method('createBatch')
            ->with($this->callback(function (CreateCommunicationPackageBatchDto $dto) {
                return $dto->getFromAmount() === 500.0
                    && $dto->getToAmount() === 1250.0
                    && $dto->getStep() === 25.0
                    && $dto->getDestinationCurrency() === 'CUP'
                    && $dto->getIsActive() === true;
            }))
            ->willReturn($packages);

        $this->contractService->expects($this->once())
            ->method('linkTenantContractsToPromotionPackages')
            ->with($packages)
            ->willReturn(3);

        $equivalencesResult = new PromotionEquivalenceResult([['provider' => 'DTONE', 'matched' => 2, 'error' => null]], []);
        $this->equivalenceService->expects($this->once())
            ->method('populateEquivalences')
            ->with($this->isInstanceOf(\App\Entity\CommunicationPromotions::class), $packages)
            ->willReturn($equivalencesResult);

        $result = $this->service->createV2($this->dto());

        $this->assertSame($packages, $result->packages);
        $this->assertSame(3, $result->tenantContractsLinked);
        $this->assertSame($equivalencesResult, $result->equivalences);
        foreach ($result->packages as $package) {
            $this->assertSame($result->promotion, $package->getPromotion());
        }
        $this->assertSame('24h Datos Ilimitados', $result->promotion->getName());
        $this->assertNull($result->promotion->getProduct());
    }

    public function testAllowsASinglePackagePromotionWhenFromEqualsTo(): void
    {
        $environment = $this->createMock(Environment::class);
        $envRepo = $this->createMock(EntityRepository::class);
        $envRepo->method('find')->willReturn($environment);
        $this->em->method('getRepository')->with(Environment::class)->willReturn($envRepo);

        $single = [(new CommunicationPackage())->setName('p')->setDescription('p')->setDestinationAmount(500.0)->setDestinationCurrency('CUP')];

        $this->packageAdminService->method('createBatch')->willReturn($single);
        $this->contractService->method('linkTenantContractsToPromotionPackages')->willReturn(0);
        $this->equivalenceService->method('populateEquivalences')->willReturn(new PromotionEquivalenceResult([], []));

        $dto = new CreatePromotionV2Dto(
            name: 'Bono único',
            description: 'Bono único',
            packageNameTemplate: 'Bono {monto} CUP',
            packageDescriptionTemplate: 'Bono {monto} CUP',
            startAt: '2026-08-18T00:00:00+00:00',
            endAt: '2026-08-25T23:59:00+00:00',
            environmentId: 4,
            destinationCurrency: 'CUP',
            amountFrom: 500.0,
            amountTo: 500.0,
            amountStep: 1.0,
        );

        $result = $this->service->createV2($dto);

        $this->assertCount(1, $result->packages);
    }

    public function testSetsInfoDescriptionWhenProvided(): void
    {
        $environment = $this->createMock(Environment::class);
        $envRepo = $this->createMock(EntityRepository::class);
        $envRepo->method('find')->willReturn($environment);
        $this->em->method('getRepository')->with(Environment::class)->willReturn($envRepo);

        $single = [(new CommunicationPackage())->setName('p')->setDescription('p')->setDestinationAmount(500.0)->setDestinationCurrency('CUP')];
        $this->packageAdminService->method('createBatch')->willReturn($single);
        $this->contractService->method('linkTenantContractsToPromotionPackages')->willReturn(0);
        $this->equivalenceService->method('populateEquivalences')->willReturn(new PromotionEquivalenceResult([], []));

        $dto = new CreatePromotionV2Dto(
            name: 'Con detalle',
            description: 'Con detalle',
            infoDescription: '<p>Detalle completo de la promoción</p>',
            packageNameTemplate: 'Bono {monto} CUP',
            packageDescriptionTemplate: 'Bono {monto} CUP',
            startAt: '2026-08-18T00:00:00+00:00',
            endAt: '2026-08-25T23:59:00+00:00',
            environmentId: 4,
            destinationCurrency: 'CUP',
            amountFrom: 500.0,
            amountTo: 500.0,
            amountStep: 1.0,
        );

        $result = $this->service->createV2($dto);

        $this->assertSame('<p>Detalle completo de la promoción</p>', $result->promotion->getInfoDescription());
    }

    public function testInfoDescriptionStaysNullWhenNotProvided(): void
    {
        $environment = $this->createMock(Environment::class);
        $envRepo = $this->createMock(EntityRepository::class);
        $envRepo->method('find')->willReturn($environment);
        $this->em->method('getRepository')->with(Environment::class)->willReturn($envRepo);

        $single = [(new CommunicationPackage())->setName('p')->setDescription('p')->setDestinationAmount(500.0)->setDestinationCurrency('CUP')];
        $this->packageAdminService->method('createBatch')->willReturn($single);
        $this->contractService->method('linkTenantContractsToPromotionPackages')->willReturn(0);
        $this->equivalenceService->method('populateEquivalences')->willReturn(new PromotionEquivalenceResult([], []));

        $result = $this->service->createV2($this->dto());

        $this->assertNull($result->promotion->getInfoDescription());
    }

    /**
     * @covers \App\Service\CommunicationPromotionService::getPackageBenefits
     * @covers \App\Service\CommunicationPromotionService::updatePackageBenefits
     */
    public function testGetPackageBenefitsReturnsTheBenefitsOfTheFirstLinkedPackage(): void
    {
        $promotion = $this->v2Promotion();
        $package = (new CommunicationPackage())->setBenefits([['type' => 'CREDITS', 'unit' => 'CUP', 'unit_type' => 'CURRENCY']]);
        $this->packageRepository->method('findByPromotion')->with($promotion)->willReturn([$package]);

        $this->assertSame([['type' => 'CREDITS', 'unit' => 'CUP', 'unit_type' => 'CURRENCY']], $this->service->getPackageBenefits($promotion));
    }

    public function testGetPackageBenefitsReturnsEmptyArrayWhenNoPackagesLinkedYet(): void
    {
        $promotion = $this->v2Promotion();
        $this->packageRepository->method('findByPromotion')->with($promotion)->willReturn([]);

        $this->assertSame([], $this->service->getPackageBenefits($promotion));
    }

    public function testUpdatePackageBenefitsPropagatesToEveryLinkedPackage(): void
    {
        $promotion = $this->v2Promotion();
        $packages = [new CommunicationPackage(), new CommunicationPackage(), new CommunicationPackage()];
        $this->packageRepository->method('findByPromotion')->with($promotion)->willReturn($packages);

        $newBenefits = [['type' => 'DATA', 'unit' => 'ILIM', 'unit_type' => 'DATA', 'operation' => 'ADD', 'value' => 20]];

        $this->packageAdminService->expects($this->exactly(3))
            ->method('update')
            ->with(
                $this->isInstanceOf(CommunicationPackage::class),
                $this->callback(fn (UpdateCommunicationPackageDto $dto) => $dto->getBenefits() === $newBenefits)
            );

        $count = $this->service->updatePackageBenefits($promotion, $newBenefits);

        $this->assertSame(3, $count);
    }

    /**
     * El dashboard ya no distingue "V1"/"V2" al editar — una promoción sin
     * ningún CommunicationPackage vinculado (ej. una promoción legacy,
     * product != null) simplemente no tiene nada que actualizar: no-op
     * seguro, nunca un error.
     */
    public function testUpdatePackageBenefitsIsANoOpForAPromotionWithoutLinkedPackages(): void
    {
        $promotion = new CommunicationPromotions();
        $reflection = new \ReflectionProperty(CommunicationPromotions::class, 'product');
        $reflection->setAccessible(true);
        $reflection->setValue($promotion, $this->createMock(\App\Entity\CommunicationProduct::class));
        $this->packageRepository->method('findByPromotion')->with($promotion)->willReturn([]);

        $this->packageAdminService->expects($this->never())->method('update');

        $count = $this->service->updatePackageBenefits($promotion, [['type' => 'CREDITS']]);

        $this->assertSame(0, $count);
    }

    /**
     * Round-trip de los campos V2 de UpdatePromotionDto (cargar → editar →
     * volver a leer) — el test que faltaba en el bug de 2026-08-27 (ver
     * CLAUDE.md regla 12): packageNameTemplate/packageDescriptionTemplate
     * se renderizan por paquete contra su propio destinationAmount, igual
     * que al crear el batch original.
     */
    public function testUpdatePropagatesNameAndDescriptionTemplatesRenderedPerPackage(): void
    {
        $promotion = $this->v2Promotion();
        $package500 = (new CommunicationPackage())->setDestinationAmount(500.0);
        $package1000 = (new CommunicationPackage())->setDestinationAmount(1000.0);
        $this->packageRepository->method('findByPromotion')->with($promotion)->willReturn([$package500, $package1000]);
        $this->packageAdminService->method('renderTemplate')
            ->willReturnCallback(fn (string $template, float $amount) => str_replace('{monto}', (string) (int) $amount, $template));

        $calls = [];
        $this->packageAdminService->expects($this->exactly(2))
            ->method('update')
            ->willReturnCallback(function ($package, UpdateCommunicationPackageDto $dto) use (&$calls) {
                $calls[] = [$dto->getName(), $dto->getDescription()];

                return $package;
            });

        $dto = new \App\DTO\UpdatePromotionDto(packageNameTemplate: 'Cubacel {monto} CUP', packageDescriptionTemplate: 'Recarga {monto} CUP');
        $this->service->update($promotion, $dto);

        $this->assertSame([
            ['Cubacel 500 CUP', 'Recarga 500 CUP'],
            ['Cubacel 1000 CUP', 'Recarga 1000 CUP'],
        ], $calls);
    }

    public function testUpdatePropagatesTagsServiceAndDisplayOrderToEveryLinkedPackage(): void
    {
        $promotion = $this->v2Promotion();
        $packages = [new CommunicationPackage(), new CommunicationPackage()];
        $this->packageRepository->method('findByPromotion')->with($promotion)->willReturn($packages);

        $this->packageAdminService->expects($this->exactly(2))
            ->method('update')
            ->with(
                $this->isInstanceOf(CommunicationPackage::class),
                $this->callback(fn (UpdateCommunicationPackageDto $dto) => $dto->getTags() === ['DATA']
                    && $dto->getService() === ['name' => 'Mobile', 'subservice' => ['name' => 'DATA']]
                    && $dto->getDisplayOrder() === 5)
            );

        $dto = new \App\DTO\UpdatePromotionDto(
            tags: ['DATA'],
            service: ['name' => 'Mobile', 'subservice' => ['name' => 'DATA']],
            displayOrder: 5,
        );
        $this->service->update($promotion, $dto);
    }

    public function testUpdateMetadataFieldsIsANoOpForAPromotionWithoutLinkedPackages(): void
    {
        $promotion = new CommunicationPromotions();
        $reflection = new \ReflectionProperty(CommunicationPromotions::class, 'product');
        $reflection->setAccessible(true);
        $reflection->setValue($promotion, $this->createMock(\App\Entity\CommunicationProduct::class));
        $this->packageRepository->method('findByPromotion')->with($promotion)->willReturn([]);

        $this->packageAdminService->expects($this->never())->method('update');

        $result = $this->service->update($promotion, new \App\DTO\UpdatePromotionDto(tags: ['DATA']));

        $this->assertSame($promotion, $result);
    }

    /**
     * El rango [amountFrom, amountTo, amountStep] + destinationCurrency
     * define qué paquetes existen — cambiarlo tras crear la promoción
     * invalidaría los vínculos por proveedor ya hechos. No se ignora en
     * silencio (ese es justo el patrón de pérdida de datos que motivó la
     * regla de TDD, ver CLAUDE.md): se rechaza con 400 explícito.
     */
    public function testUpdateRejectsRangeFieldsAsImmutable(): void
    {
        $promotion = $this->v2Promotion();
        $this->em->expects($this->never())->method('flush');

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('rango');

        $this->service->update($promotion, new \App\DTO\UpdatePromotionDto(amountFrom: 600.0));
    }

    public function testUpdateRejectsDestinationCurrencyAsImmutable(): void
    {
        $promotion = $this->v2Promotion();

        $this->expectException(MyCurrentException::class);

        $this->service->update($promotion, new \App\DTO\UpdatePromotionDto(destinationCurrency: 'USD'));
    }

    private function v2Promotion(): CommunicationPromotions
    {
        // isV2() === true cuando $product es null — estado por defecto de una
        // promoción recién construida, sin necesidad de reflection aquí.
        return new CommunicationPromotions();
    }
}
