<?php

namespace App\Tests\Service\Pricing;

use App\Entity\Account;
use App\Entity\Client;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationPricePackage;
use App\Entity\CommunicationProduct;
use App\Exception\MyCurrentException;
use App\Repository\CommunicationPricePackageRepository;
use App\Service\Pricing\PackageSalePriceResolver;
use App\Service\Pricing\PriceSourceEnum;
use App\Service\Provider\ProductPriceResolver;
use App\Service\Provider\ResolvedProductPrice;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \App\Service\Pricing\PackageSalePriceResolver
 *
 * El servicio más crítico de este rediseño: único punto de precio
 * compartido por listado y venta. Orden de resolución probado
 * explícitamente: contrato congelado (priceClientPackage) > contrato por
 * (tenant, paquete referencia) > producto sin contrato > snapshot legacy.
 */
class PackageSalePriceResolverTest extends TestCase
{
    private CommunicationPricePackageRepository&MockObject $contractRepository;
    private ProductPriceResolver&MockObject $productPriceResolver;
    private PackageSalePriceResolver $resolver;

    protected function setUp(): void
    {
        $this->contractRepository = $this->createMock(CommunicationPricePackageRepository::class);
        $this->productPriceResolver = $this->createMock(ProductPriceResolver::class);

        $this->resolver = new PackageSalePriceResolver(
            $this->contractRepository,
            $this->productPriceResolver,
            $this->createMock(LoggerInterface::class),
        );
    }

    private function assignId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }

    private function account(int $id, string $currency = 'USD'): Account&MockObject
    {
        $client = $this->createMock(Client::class);
        $client->method('getCurrency')->willReturn($currency);

        $account = $this->createMock(Account::class);
        $account->method('getId')->willReturn($id);
        $account->method('getClient')->willReturn($client);

        return $account;
    }

    private function referencePackage(int $id, ?CommunicationProduct $product = null): CommunicationClientPackage
    {
        $reference = (new CommunicationClientPackage())
            ->setName('Referencia')
            ->setDescription('Referencia')
            ->setProduct($product);
        $this->assignId($reference, $id);

        return $reference;
    }

    private function materializedPackage(
        CommunicationClientPackage $reference,
        ?CommunicationPricePackage $frozenContract = null,
    ): CommunicationClientPackage {
        // product se copia del paquete referencia, igual que hace
        // PackageMaterializationService::materializeForTenant() en
        // producción — resolveProduct() prioriza priceClientPackage->product
        // y cae aquí cuando ese contrato (si existe) no tiene producto.
        return (new CommunicationClientPackage())
            ->setTenant($this->createMock(Account::class))
            ->setReferencePackage($reference)
            ->setProduct($reference->getProduct())
            ->setName('Materializado')
            ->setDescription('Materializado')
            ->setPriceClientPackage($frozenContract)
            ->setAmount(999.0)
            ->setCurrency('EUR'); // snapshot crudo deliberadamente distinto, para detectar si algo lo lee por error
    }

    private function contract(
        float $amount,
        string $currency,
        bool $isContract = true,
        bool $isActive = true,
        ?\DateTimeImmutable $activeStartAt = null,
        ?\DateTimeImmutable $activeEndAt = null,
    ): CommunicationPricePackage {
        $contract = (new CommunicationPricePackage())
            ->setIsContract($isContract)
            ->setIsActive($isActive)
            ->setAmount($amount)
            ->setCurrency($currency)
            ->setActiveStartAt($activeStartAt ?? new \DateTimeImmutable('-1 day'));
        if ($activeEndAt !== null) {
            $contract->setActiveEndAt($activeEndAt);
        }
        $this->assignId($contract, random_int(1000, 999999));

        return $contract;
    }

    public function testFrozenContractOnPackageWinsOverEverything(): void
    {
        $product = $this->createMock(CommunicationProduct::class);
        $reference = $this->referencePackage(1, $product);
        $frozen = $this->contract(15.0, 'USD');
        $package = $this->materializedPackage($reference, $frozen);
        $account = $this->account(99);

        $this->contractRepository->expects($this->never())->method('findActiveContract');
        $this->productPriceResolver->expects($this->never())->method('resolve');

        $resolved = $this->resolver->resolve($package, $account);

        $this->assertSame(15.0, $resolved->amount);
        $this->assertSame('USD', $resolved->currency);
        $this->assertSame(PriceSourceEnum::CONTRACT, $resolved->source);
    }

    public function testExpiredFrozenContractIsIgnoredAndFallsBackToProduct(): void
    {
        $product = $this->createMock(CommunicationProduct::class);
        $reference = $this->referencePackage(1, $product);
        $expired = $this->contract(15.0, 'USD', activeEndAt: new \DateTimeImmutable('-1 day'));
        $package = $this->materializedPackage($reference, $expired);
        $account = $this->account(99);

        $this->contractRepository->method('findActiveContract')->willReturn(null);
        $this->productPriceResolver->method('resolve')->willReturn(new ResolvedProductPrice(4.5, 'USD', null, null, null));

        $resolved = $this->resolver->resolve($package, $account);

        $this->assertSame(PriceSourceEnum::PRODUCT, $resolved->source);
    }

    public function testInactiveFrozenContractIsIgnored(): void
    {
        $product = $this->createMock(CommunicationProduct::class);
        $reference = $this->referencePackage(1, $product);
        $inactive = $this->contract(15.0, 'USD', isActive: false);
        $package = $this->materializedPackage($reference, $inactive);
        $account = $this->account(99);

        $this->contractRepository->method('findActiveContract')->willReturn(null);
        $this->productPriceResolver->method('resolve')->willReturn(new ResolvedProductPrice(4.5, 'USD', null, null, null));

        $resolved = $this->resolver->resolve($package, $account);

        $this->assertSame(PriceSourceEnum::PRODUCT, $resolved->source);
    }

    public function testNonContractFrozenRowIsIgnored(): void
    {
        // isContract=false: es un espejo automático del costo mayorista
        // (autoManaged), no un contrato real — nunca debe usarse como
        // fuente de precio, aunque esté "activo" y vigente.
        $product = $this->createMock(CommunicationProduct::class);
        $reference = $this->referencePackage(1, $product);
        $mirror = $this->contract(15.0, 'USD', isContract: false);
        $package = $this->materializedPackage($reference, $mirror);
        $account = $this->account(99);

        $this->contractRepository->method('findActiveContract')->willReturn(null);
        $this->productPriceResolver->method('resolve')->willReturn(new ResolvedProductPrice(4.5, 'USD', null, null, null));

        $resolved = $this->resolver->resolve($package, $account);

        $this->assertSame(PriceSourceEnum::PRODUCT, $resolved->source);
    }

    public function testFindsContractByTenantAndReferencePackageWhenNoFrozenContract(): void
    {
        $product = $this->createMock(CommunicationProduct::class);
        $reference = $this->referencePackage(42, $product);
        $package = $this->materializedPackage($reference);
        $account = $this->account(99);

        $contract = $this->contract(20.0, 'USD');
        $this->contractRepository->expects($this->once())
            ->method('findActiveContract')
            ->with($account, 42, $this->isInstanceOf(\DateTimeImmutable::class))
            ->willReturn($contract);
        $this->productPriceResolver->expects($this->never())->method('resolve');

        $resolved = $this->resolver->resolve($package, $account);

        $this->assertSame(20.0, $resolved->amount);
        $this->assertSame(PriceSourceEnum::CONTRACT, $resolved->source);
    }

    public function testFallsBackToProductPriceResolverWhenNoContractExists(): void
    {
        $product = $this->createMock(CommunicationProduct::class);
        $reference = $this->referencePackage(1, $product);
        $package = $this->materializedPackage($reference);
        $account = $this->account(99, 'EUR');

        $this->contractRepository->method('findActiveContract')->willReturn(null);
        $rateDate = new \DateTimeImmutable('2026-08-01');
        $this->productPriceResolver->expects($this->once())
            ->method('resolve')
            ->with($product, 'EUR', 99)
            ->willReturn(new ResolvedProductPrice(4.0, 'EUR', 0.89, $rateDate, 'nota'));

        $resolved = $this->resolver->resolve($package, $account);

        $this->assertSame(4.0, $resolved->amount);
        $this->assertSame('EUR', $resolved->currency);
        $this->assertSame(PriceSourceEnum::PRODUCT, $resolved->source);
        $this->assertSame(0.89, $resolved->conversionRate);
        $this->assertSame($rateDate, $resolved->conversionRateDate);
        $this->assertSame('nota', $resolved->note);
    }

    public function testFallsBackToLegacySnapshotWhenNoContractAndNoProduct(): void
    {
        $reference = $this->referencePackage(1, null);
        $package = $this->materializedPackage($reference);
        $account = $this->account(99);

        $this->contractRepository->method('findActiveContract')->willReturn(null);
        $this->productPriceResolver->expects($this->never())->method('resolve');

        $resolved = $this->resolver->resolve($package, $account);

        $this->assertSame(999.0, $resolved->amount);
        $this->assertSame('EUR', $resolved->currency);
        $this->assertSame(PriceSourceEnum::LEGACY_SNAPSHOT, $resolved->source);
    }

    public function testResolveForSaleThrowsOnUnresolvableLegacySnapshot(): void
    {
        $reference = $this->referencePackage(1, null);
        $package = $this->materializedPackage($reference);
        $package->setAmount(0.0);
        $account = $this->account(99);

        $this->contractRepository->method('findActiveContract')->willReturn(null);

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('No hay precio vigente para este paquete');

        $this->resolver->resolveForSale($package, $account);
    }

    public function testResolveForSaleAdmitsAZeroPriceRealContract(): void
    {
        // Un contrato explícito a $0 es un precio real y deliberado
        // (paquete gratuito/de compensación) — no debe rechazarse, solo el
        // snapshot legacy no resoluble.
        $product = $this->createMock(CommunicationProduct::class);
        $reference = $this->referencePackage(1, $product);
        $package = $this->materializedPackage($reference);
        $account = $this->account(99);

        $freeContract = $this->contract(0.0, 'USD');
        $this->contractRepository->method('findActiveContract')->willReturn($freeContract);

        $resolved = $this->resolver->resolveForSale($package, $account);

        $this->assertSame(0.0, $resolved->amount);
        $this->assertSame(PriceSourceEnum::CONTRACT, $resolved->source);
    }

    public function testResolveManyIssuesOnlyOneContractQueryForTheWholeBatch(): void
    {
        $product = $this->createMock(CommunicationProduct::class);
        $reference1 = $this->referencePackage(1, $product);
        $reference2 = $this->referencePackage(2, $product);
        $package1 = $this->materializedPackage($reference1);
        $package2 = $this->materializedPackage($reference2);
        $account = $this->account(99);

        $contract1 = $this->contract(10.0, 'USD');
        $this->contractRepository->expects($this->once())
            ->method('findActiveContractsFor')
            ->with($account, $this->callback(fn ($ids) => in_array(1, $ids, true) && in_array(2, $ids, true)))
            ->willReturn([1 => $contract1]);
        $this->contractRepository->expects($this->never())->method('findActiveContract');
        $this->productPriceResolver->method('resolve')->willReturn(new ResolvedProductPrice(4.5, 'USD', null, null, null));

        $this->resolver->resolveMany([$package1, $package2], $account);

        $this->assertSame(PriceSourceEnum::CONTRACT, $package1->getResolvedSalePrice()->source);
        $this->assertSame(10.0, $package1->getAmount());
        $this->assertSame(PriceSourceEnum::PRODUCT, $package2->getResolvedSalePrice()->source);
    }

    public function testContractOfAnotherTenantIsNeverAppliedBecauseTheQueryItselfScopesByTenant(): void
    {
        // El aislamiento por tenant lo garantiza el repositorio
        // (findActiveContract recibe $account como parámetro) — este test
        // documenta el contrato: el resolver siempre pasa la cuenta
        // correcta, nunca busca "cualquier contrato de esa referencia".
        $product = $this->createMock(CommunicationProduct::class);
        $reference = $this->referencePackage(7, $product);
        $package = $this->materializedPackage($reference);
        $accountA = $this->account(1);

        $this->contractRepository->expects($this->once())
            ->method('findActiveContract')
            ->with($accountA, 7, $this->isInstanceOf(\DateTimeImmutable::class))
            ->willReturn(null);
        $this->productPriceResolver->method('resolve')->willReturn(new ResolvedProductPrice(4.5, 'USD', null, null, null));

        $this->resolver->resolve($package, $accountA);
    }
}
