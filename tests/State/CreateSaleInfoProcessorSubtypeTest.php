<?php

namespace App\Tests\State;

use ApiPlatform\Metadata\Post;
use App\Entity\CommunicationSaleInfo;
use App\Entity\CommunicationSalePackage;
use App\Entity\CommunicationSaleRecharge;
use App\Exception\MyCurrentException;
use App\Service\CommunicationSaleService;
use App\State\CreateSaleInfoProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * El processor de creación bifurca por subtipo (recarga, paquete, reserva). COMR-62 (C3): un subtipo que no
 * encaje lanza, en lugar de devolver null sin crear nada ni avisar (comportamiento que fijó COMR-263).
 *
 * @covers \App\State\CreateSaleInfoProcessor
 */
class CreateSaleInfoProcessorSubtypeTest extends TestCase
{
    private CommunicationSaleService&MockObject $saleService;
    private CreateSaleInfoProcessor $processor;

    protected function setUp(): void
    {
        $this->saleService = $this->createMock(CommunicationSaleService::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($this->createMock(\Doctrine\ORM\EntityRepository::class));
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);
        $limiter = new RateLimiterFactory(['id' => 'test', 'policy' => 'fixed_window', 'limit' => 100, 'interval' => '1 minute'], new InMemoryStorage());

        $this->processor = new CreateSaleInfoProcessor($this->saleService, $em, $security, $limiter);
    }

    public function testARechargeGoesToProcessRecharge(): void
    {
        $recharge = new CommunicationSaleRecharge();
        $this->saleService->expects($this->once())->method('processRecharge')->with($recharge)->willReturn($recharge);

        $this->assertSame($recharge, $this->processor->process($recharge, new Post()));
    }

    public function testAPackageGoesToExecuteSale(): void
    {
        $package = new CommunicationSalePackage();
        $this->saleService->expects($this->once())->method('executeSale')->with($package)->willReturn($package);

        $this->assertSame($package, $this->processor->process($package, new Post()));
    }

    public function testAnyOtherSubtypeThrowsWithoutCreatingAnything(): void
    {
        $this->saleService->expects($this->never())->method('processRecharge');
        $this->saleService->expects($this->never())->method('executeSale');
        $this->saleService->expects($this->never())->method('processReserve');

        $this->expectException(MyCurrentException::class);

        $this->processor->process(new CommunicationSaleInfo(), new Post());
    }
}
