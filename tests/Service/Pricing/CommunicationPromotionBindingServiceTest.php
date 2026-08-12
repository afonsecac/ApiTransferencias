<?php

namespace App\Tests\Service\Pricing;

use App\Entity\CommunicationProduct;
use App\Entity\CommunicationPromotionProviderProduct;
use App\Entity\CommunicationPromotions;
use App\Enums\CommunicationProviderEnum;
use App\Exception\MyCurrentException;
use App\Provider\Contract\CommunicationProviderInterface;
use App\Provider\ProviderRegistry;
use App\Repository\CommunicationProductRepository;
use App\Repository\CommunicationPromotionProviderProductRepository;
use App\Service\Pricing\CommunicationPromotionBindingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Service\Pricing\CommunicationPromotionBindingService
 *
 * ProviderRegistry es `final` — PHPUnit no puede doblarlo. Se construye una
 * instancia real con adaptadores fake mínimos, igual que
 * CommunicationPackageBindingServiceTest.
 */
class CommunicationPromotionBindingServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private CommunicationPromotionProviderProductRepository&MockObject $bindingRepo;
    private CommunicationProductRepository&MockObject $productRepository;
    private CommunicationPromotionBindingService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->bindingRepo = $this->createMock(CommunicationPromotionProviderProductRepository::class);
        $this->productRepository = $this->createMock(CommunicationProductRepository::class);

        $this->service = new CommunicationPromotionBindingService(
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

    private function promotion(): CommunicationPromotions
    {
        return new CommunicationPromotions();
    }

    public function testListBindingsReturnsOneRowPerRegisteredProviderWithCandidatesAndBoundProduct(): void
    {
        $promotion = $this->promotion();

        $boundProduct = $this->createMock(CommunicationProduct::class);
        $binding = $this->createMock(CommunicationPromotionProviderProduct::class);
        $binding->method('getProvider')->willReturn('ETECSA');
        $binding->method('getProduct')->willReturn($boundProduct);
        $this->bindingRepo->method('findAllForPromotion')->with($promotion)->willReturn([$binding]);

        $etecsaCatalog = [$this->createMock(CommunicationProduct::class)];
        $csqCatalog = [$this->createMock(CommunicationProduct::class), $this->createMock(CommunicationProduct::class)];
        $this->productRepository->method('findAllByProvider')
            ->willReturnCallback(fn ($provider) => $provider === 'ETECSA' ? $etecsaCatalog : $csqCatalog);

        $rows = $this->service->listBindings($promotion, null);

        $this->assertCount(2, $rows);
        $this->assertSame('ETECSA', $rows[0]['provider']);
        $this->assertSame($boundProduct, $rows[0]['boundProduct']);
        $this->assertSame($etecsaCatalog, $rows[0]['candidates']);
        $this->assertSame('CSQ', $rows[1]['provider']);
        $this->assertNull($rows[1]['boundProduct']);
        $this->assertSame($csqCatalog, $rows[1]['candidates']);
    }

    public function testSetBindingCreatesANewBindingWhenNoneExists(): void
    {
        $promotion = $this->promotion();
        $product = $this->createMock(CommunicationProduct::class);

        $this->productRepository->method('find')->with(7)->willReturn($product);
        $this->bindingRepo->method('findForPromotionAndProvider')->willReturn(null);

        $this->em->expects($this->once())->method('persist')->with($this->isInstanceOf(CommunicationPromotionProviderProduct::class));
        $this->em->expects($this->once())->method('flush');

        $binding = $this->service->setBinding($promotion, 'CSQ', 7);

        $this->assertSame($promotion, $binding->getPromotion());
        $this->assertSame('CSQ', $binding->getProvider());
        $this->assertSame($product, $binding->getProduct());
    }

    public function testSetBindingUpdatesTheExistingBindingInsteadOfDuplicating(): void
    {
        $promotion = $this->promotion();
        $newProduct = $this->createMock(CommunicationProduct::class);
        $existing = new CommunicationPromotionProviderProduct();

        $this->productRepository->method('find')->willReturn($newProduct);
        $this->bindingRepo->method('findForPromotionAndProvider')->willReturn($existing);

        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $binding = $this->service->setBinding($promotion, 'CSQ', 7);

        $this->assertSame($existing, $binding);
        $this->assertSame($newProduct, $binding->getProduct());
    }

    public function testSetBindingThrowsWhenProviderIsNotRegistered(): void
    {
        $promotion = $this->promotion();

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('no está registrado');

        // El registro de setUp() solo tiene ETECSA/CSQ — DTONE no está registrado.
        $this->service->setBinding($promotion, 'DTONE', 7);
    }

    public function testSetBindingThrowsWhenProductNotFound(): void
    {
        $promotion = $this->promotion();
        $this->productRepository->method('find')->willReturn(null);

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('Communication product not found');

        $this->service->setBinding($promotion, 'CSQ', 999);
    }

    public function testRemoveBindingDeletesTheExistingBinding(): void
    {
        $promotion = $this->promotion();
        $binding = new CommunicationPromotionProviderProduct();

        $this->bindingRepo->method('findForPromotionAndProvider')->willReturn($binding);

        $this->em->expects($this->once())->method('remove')->with($binding);
        $this->em->expects($this->once())->method('flush');

        $this->service->removeBinding($promotion, 'CSQ');
    }

    public function testRemoveBindingIsANoOpWhenNoBindingExists(): void
    {
        $promotion = $this->promotion();
        $this->bindingRepo->method('findForPromotionAndProvider')->willReturn(null);

        $this->em->expects($this->never())->method('remove');
        $this->em->expects($this->never())->method('flush');

        $this->service->removeBinding($promotion, 'CSQ');
    }
}
