<?php

namespace App\Tests\Service\Pricing;

use App\Entity\Account;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationProduct;
use App\Entity\Environment;
use App\Repository\CommunicationClientPackageRepository;
use App\Service\Pricing\PackageMaterializationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Service\Pricing\PackageMaterializationService
 */
class PackageMaterializationServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private CommunicationClientPackageRepository&MockObject $repo;
    private PackageMaterializationService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->repo = $this->createMock(CommunicationClientPackageRepository::class);
        $this->em->method('getRepository')->with(CommunicationClientPackage::class)->willReturn($this->repo);

        $this->service = new PackageMaterializationService($this->em);
    }

    private function referencePackage(): CommunicationClientPackage
    {
        $product = $this->createMock(CommunicationProduct::class);
        $environment = $this->createMock(Environment::class);

        return (new CommunicationClientPackage())
            ->setProduct($product)
            ->setEnvironment($environment)
            ->setName('Paquete referencia')
            ->setDescription('Descripción referencia')
            ->setBenefits([['type' => 'CREDITS']])
            ->setTags(['AIRTIME'])
            ->setService(['name' => 'Mobile'])
            ->setDestination(['amount' => 5.0, 'unit' => 'CUP', 'unit_type' => 'CURRENCY'])
            ->setValidity(['quantity' => null, 'unit' => null])
            ->setKnowMore('nota')
            ->setActiveStartAt(new \DateTimeImmutable('2026-01-01'))
            ->setActiveEndAt(new \DateTimeImmutable('2030-01-02 04:59:59'));
    }

    public function testReturnsExistingMaterializationInsteadOfDuplicating(): void
    {
        $reference = $this->referencePackage();
        $tenant = $this->createMock(Account::class);
        $existing = new CommunicationClientPackage();

        $this->repo->method('findOneBy')
            ->with(['tenant' => $tenant, 'referencePackage' => $reference])
            ->willReturn($existing);

        $this->em->expects($this->never())->method('persist');

        $result = $this->service->materializeForTenant($reference, $tenant);

        $this->assertSame($existing, $result);
    }

    public function testClonesStructureFieldsFromReference(): void
    {
        $reference = $this->referencePackage();
        $tenant = $this->createMock(Account::class);

        $this->repo->method('findOneBy')->willReturn(null);

        $persisted = null;
        $this->em->expects($this->once())->method('persist')
            ->with($this->callback(function ($entity) use (&$persisted) {
                $persisted = $entity;

                return $entity instanceof CommunicationClientPackage;
            }));

        $result = $this->service->materializeForTenant($reference, $tenant);

        $this->assertSame($persisted, $result);
        $this->assertSame($tenant, $result->getTenant());
        $this->assertSame($reference, $result->getReferencePackage());
        $this->assertSame($reference->getProduct(), $result->getProduct());
        $this->assertSame($reference->getEnvironment(), $result->getEnvironment());
        $this->assertSame('Paquete referencia', $result->getName());
        $this->assertSame('Descripción referencia', $result->getDescription());
        $this->assertSame([['type' => 'CREDITS']], $result->getBenefits());
        $this->assertSame(['AIRTIME'], $result->getTags());
        $this->assertSame(['name' => 'Mobile'], $result->getService());
        $this->assertSame(['amount' => 5.0, 'unit' => 'CUP', 'unit_type' => 'CURRENCY'], $result->getDestination());
        $this->assertSame(['quantity' => null, 'unit' => null], $result->getValidity());
        $this->assertSame('nota', $result->getKnowMore());
        $this->assertEquals($reference->getActiveStartAt(), $result->getActiveStartAt());
        $this->assertEquals($reference->getActiveEndAt(), $result->getActiveEndAt());
        // La copia no fija contrato: el precio lo resuelve
        // PackageSalePriceResolver en el momento, no aquí.
        $this->assertNull($result->getPriceClientPackage());
    }

    public function testDoesNotFlush(): void
    {
        // El llamador decide cuándo flushear (igual que
        // PackagePriceService::createPackageClient() antes) — importa
        // porque tanto ClientCatalogImportService como
        // CommunicationClientPackageProvider agrupan varias
        // materializaciones en un solo flush().
        $reference = $this->referencePackage();
        $tenant = $this->createMock(Account::class);
        $this->repo->method('findOneBy')->willReturn(null);

        $this->em->expects($this->never())->method('flush');

        $this->service->materializeForTenant($reference, $tenant);
    }
}
