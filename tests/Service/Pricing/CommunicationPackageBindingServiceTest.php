<?php

namespace App\Tests\Service\Pricing;

use App\Entity\CommunicationPackage;
use App\Entity\CommunicationPackageProviderProduct;
use App\Entity\CommunicationProduct;
use App\Enums\CommunicationProviderEnum;
use App\Exception\MyCurrentException;
use App\Provider\Contract\CommunicationProviderInterface;
use App\Provider\ProviderRegistry;
use App\Repository\CommunicationPackageProviderProductRepository;
use App\Repository\CommunicationProductRepository;
use App\Service\Pricing\CommunicationPackageBindingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Service\Pricing\CommunicationPackageBindingService
 *
 * ProviderRegistry es `final` — PHPUnit no puede doblarlo (ClassIsFinalException).
 * Se construye una instancia real con adaptadores fake mínimos en vez de
 * mockearla, igual que exige su propio constructor (iterable de
 * CommunicationProviderInterface).
 */
class CommunicationPackageBindingServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private CommunicationPackageProviderProductRepository&MockObject $bindingRepo;
    private CommunicationProductRepository&MockObject $productRepository;
    private CommunicationPackageBindingService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->bindingRepo = $this->createMock(CommunicationPackageProviderProductRepository::class);
        $this->productRepository = $this->createMock(CommunicationProductRepository::class);

        $this->service = new CommunicationPackageBindingService(
            $this->em,
            $this->bindingRepo,
            $this->productRepository,
            $this->providerRegistry([CommunicationProviderEnum::ETECSA, CommunicationProviderEnum::CSQ]),
        );
    }

    /**
     * @param list<CommunicationProviderEnum> $codes
     */
    private function providerRegistry(array $codes): ProviderRegistry
    {
        $fakes = array_map(static function (CommunicationProviderEnum $code) {
            return new class($code) implements CommunicationProviderInterface {
                public function __construct(private readonly CommunicationProviderEnum $code) {}
                public function getCode(): CommunicationProviderEnum { return $this->code; }
                public function getCapabilities(): array { return []; }
                public function getConfigSchema(): array { return []; }
            };
        }, $codes);

        return new ProviderRegistry($fakes);
    }

    private function package(): CommunicationPackage
    {
        return (new CommunicationPackage())
            ->setName('P')->setDescription('P')
            ->setDestinationAmount(500.0)->setDestinationCurrency('CUP');
    }

    public function testListBindingsReturnsOneRowPerRegisteredProviderWithCandidatesAndBoundProduct(): void
    {
        $package = $this->package();

        $boundProduct = $this->createMock(CommunicationProduct::class);
        $binding = $this->createMock(CommunicationPackageProviderProduct::class);
        $binding->method('getProvider')->willReturn('ETECSA');
        $binding->method('getProduct')->willReturn($boundProduct);
        $this->bindingRepo->method('findAllForPackage')->with($package)->willReturn([$binding]);

        $csqCandidate = $this->createMock(CommunicationProduct::class);
        $csqCandidate->method('getProvider')->willReturn('CSQ');
        $this->productRepository->method('findMatchingDestination')
            ->with(500.0, 'CUP', null)
            ->willReturn([$csqCandidate]);
        // ETECSA no matcheó por tupla — el fallback a catálogo completo no
        // encuentra nada tampoco (proveedor sin productos habilitados en
        // este escenario).
        $this->productRepository->method('findAllByProvider')->with('ETECSA', null)->willReturn([]);

        $rows = $this->service->listBindings($package, null);

        $this->assertCount(2, $rows);
        $this->assertSame('ETECSA', $rows[0]['provider']);
        $this->assertSame($boundProduct, $rows[0]['boundProduct']);
        $this->assertSame([], $rows[0]['candidates']);
        $this->assertFalse($rows[0]['autoMatched']);
        $this->assertSame('CSQ', $rows[1]['provider']);
        $this->assertNull($rows[1]['boundProduct']);
        $this->assertSame([$csqCandidate], $rows[1]['candidates']);
        $this->assertTrue($rows[1]['autoMatched']);
    }

    public function testListBindingsFallsBackToFullProviderCatalogWhenNoTupleMatch(): void
    {
        $package = $this->package();
        $this->bindingRepo->method('findAllForPackage')->willReturn([]);
        // Ningún proveedor matchea por tupla (ej. ETECSA: sin
        // destinationAmount/destinationUnit poblados).
        $this->productRepository->method('findMatchingDestination')->willReturn([]);

        $allEtecsa = [$this->createMock(CommunicationProduct::class), $this->createMock(CommunicationProduct::class)];
        $this->productRepository->method('findAllByProvider')
            ->willReturnCallback(fn ($provider) => $provider === 'ETECSA' ? $allEtecsa : []);

        $rows = $this->service->listBindings($package, null);

        $etecsaRow = $rows[0];
        $this->assertSame('ETECSA', $etecsaRow['provider']);
        $this->assertSame($allEtecsa, $etecsaRow['candidates']);
        $this->assertFalse($etecsaRow['autoMatched']);
    }

    public function testSetBindingCreatesANewBindingWhenNoneExists(): void
    {
        $package = $this->package();
        $product = $this->createMock(CommunicationProduct::class);

        $this->productRepository->method('find')->with(7)->willReturn($product);
        $this->bindingRepo->method('findForPackageAndProvider')->willReturn(null);

        $this->em->expects($this->once())->method('persist')->with($this->isInstanceOf(CommunicationPackageProviderProduct::class));
        $this->em->expects($this->once())->method('flush');

        $binding = $this->service->setBinding($package, 'CSQ', 7);

        $this->assertSame($package, $binding->getCommunicationPackage());
        $this->assertSame('CSQ', $binding->getProvider());
        $this->assertSame($product, $binding->getProduct());
    }

    public function testSetBindingUpdatesTheExistingBindingInsteadOfDuplicating(): void
    {
        $package = $this->package();
        $newProduct = $this->createMock(CommunicationProduct::class);
        $existing = new CommunicationPackageProviderProduct();

        $this->productRepository->method('find')->willReturn($newProduct);
        $this->bindingRepo->method('findForPackageAndProvider')->willReturn($existing);

        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $binding = $this->service->setBinding($package, 'CSQ', 7);

        $this->assertSame($existing, $binding);
        $this->assertSame($newProduct, $binding->getProduct());
    }

    public function testSetBindingThrowsWhenProviderIsNotRegistered(): void
    {
        $package = $this->package();

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('no está registrado');

        // El registro de setUp() solo tiene ETECSA/CSQ — DTONE no está registrado.
        $this->service->setBinding($package, 'DTONE', 7);
    }

    public function testSetBindingThrowsWhenProductNotFound(): void
    {
        $package = $this->package();
        $this->productRepository->method('find')->willReturn(null);

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('Communication product not found');

        $this->service->setBinding($package, 'CSQ', 999);
    }

    public function testRemoveBindingDeletesTheExistingBinding(): void
    {
        $package = $this->package();
        $binding = new CommunicationPackageProviderProduct();

        $this->bindingRepo->method('findForPackageAndProvider')->willReturn($binding);

        $this->em->expects($this->once())->method('remove')->with($binding);
        $this->em->expects($this->once())->method('flush');

        $this->service->removeBinding($package, 'CSQ');
    }

    public function testRemoveBindingIsANoOpWhenNoBindingExists(): void
    {
        $package = $this->package();
        $this->bindingRepo->method('findForPackageAndProvider')->willReturn(null);

        $this->em->expects($this->never())->method('remove');
        $this->em->expects($this->never())->method('flush');

        $this->service->removeBinding($package, 'CSQ');
    }
}
