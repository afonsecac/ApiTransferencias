<?php

namespace App\Tests\Service;

use App\DTO\CreateCommunicationPackageBatchDto;
use App\DTO\CreatePromotionV2Dto;
use App\Entity\CommunicationPackage;
use App\Entity\Environment;
use App\Exception\MyCurrentException;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use App\Service\CommunicationPromotionService;
use App\Service\Pricing\CommunicationContractService;
use App\Service\Pricing\CommunicationPackageAdminService;
use App\Service\Pricing\CommunicationPromotionEquivalenceService;
use App\Service\Pricing\ContractRangeResult;
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
    private CommunicationPromotionService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->packageAdminService = $this->createMock(CommunicationPackageAdminService::class);
        $this->contractService = $this->createMock(CommunicationContractService::class);
        $this->equivalenceService = $this->createMock(CommunicationPromotionEquivalenceService::class);

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
            priceFrom: 19.7,
            priceTo: 49.25,
            priceCurrency: 'USD',
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

        $contractResult = new ContractRangeResult(2, 0, 0, [10, 11], []);
        $this->contractService->expects($this->once())
            ->method('createForPromotionPackages')
            ->with($packages, 19.7, 49.25, 'USD', '2026-08-18T00:00:00+00:00', '2026-08-25T23:59:00+00:00')
            ->willReturn($contractResult);

        $equivalencesResult = new PromotionEquivalenceResult([['provider' => 'DTONE', 'matched' => 2, 'error' => null]], []);
        $this->equivalenceService->expects($this->once())
            ->method('populateEquivalences')
            ->with($this->isInstanceOf(\App\Entity\CommunicationPromotions::class), $packages)
            ->willReturn($equivalencesResult);

        $result = $this->service->createV2($this->dto());

        $this->assertSame($packages, $result->packages);
        $this->assertSame($contractResult, $result->contracts);
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
        $this->contractService->method('createForPromotionPackages')->willReturn(new ContractRangeResult(1, 0, 0, [1], []));
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
            priceFrom: 19.7,
            priceTo: 19.7,
            priceCurrency: 'USD',
        );

        $result = $this->service->createV2($dto);

        $this->assertCount(1, $result->packages);
    }
}
