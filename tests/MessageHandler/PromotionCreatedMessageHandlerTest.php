<?php

namespace App\Tests\MessageHandler;

use App\Entity\Account;
use App\Entity\Client;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationContract;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationPricePackage;
use App\Entity\CommunicationPromotions;
use App\Entity\Environment;
use App\Message\PromotionCreatedMessage;
use App\MessageHandler\PromotionCreatedMessageHandler;
use App\Repository\CommunicationPackageRepository;
use App\Service\NotificationCenterService;
use App\Service\Pricing\PackageCatalogResolver;
use App\Service\Pricing\PackageOfferSourceEnum;
use App\Service\Pricing\ResolvedPackageOffer;
use App\Service\Pricing\TargetAccountResolver;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;

/**
 * @covers \App\MessageHandler\PromotionCreatedMessageHandler
 *
 * Fase 2 de la deprecación de V1: antes de este fix, el email de promoción
 * solo sabía leer $promotion->getProducts() (V1) — para una promoción V2
 * (catálogo compartido, sin copia por tenant) el array de paquetes salía
 * siempre vacío, en silencio. Los tests de "camino V2" son la regresión que
 * este fix compra.
 */
class PromotionCreatedMessageHandlerTest extends TestCase
{
    private MailerInterface&MockObject $mailer;
    private ParameterBagInterface&MockObject $parameterBag;
    private EntityManagerInterface&MockObject $em;
    private NotificationCenterService&MockObject $notificationCenter;
    private CommunicationPackageRepository&MockObject $packageRepository;
    private PackageCatalogResolver&MockObject $catalogResolver;
    private TargetAccountResolver&MockObject $targetAccountResolver;
    private PromotionCreatedMessageHandler $handler;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);
        $this->parameterBag->method('get')->with('app.email.from')->willReturn('noreply@test.com');
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->notificationCenter = $this->createMock(NotificationCenterService::class);
        $this->packageRepository = $this->createMock(CommunicationPackageRepository::class);
        $this->catalogResolver = $this->createMock(PackageCatalogResolver::class);
        $this->targetAccountResolver = $this->createMock(TargetAccountResolver::class);

        $this->handler = new PromotionCreatedMessageHandler(
            $this->mailer,
            $this->parameterBag,
            $this->em,
            $this->notificationCenter,
            $this->packageRepository,
            $this->catalogResolver,
            $this->targetAccountResolver,
        );
    }

    private function message(int $promotionId = 1, int $clientId = 10): PromotionCreatedMessage
    {
        return new PromotionCreatedMessage($promotionId, 'user@example.com', 'Test', $clientId, 'comremit');
    }

    private function repoReturning(string $class, mixed $value): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->willReturn($value);
        $this->em->method('getRepository')->willReturnCallback(
            fn (string $c) => $c === $class ? $repo : $this->createMock(EntityRepository::class)
        );
    }

    public function testDoesNothingWhenPromotionNotFound(): void
    {
        $this->repoReturning(CommunicationPromotions::class, null);

        $this->mailer->expects($this->never())->method('send');

        ($this->handler)($this->message());
    }

    public function testV1PathOnlyIncludesPackagesForTheTargetClient(): void
    {
        $promotion = $this->createMock(CommunicationPromotions::class);
        $promotion->method('getName')->willReturn('Promo V1');

        $targetClient = $this->createMock(Client::class);
        $targetClient->method('getId')->willReturn(10);
        $targetAccount = $this->createMock(Account::class);
        $targetAccount->method('getClient')->willReturn($targetClient);

        $otherClient = $this->createMock(Client::class);
        $otherClient->method('getId')->willReturn(99);
        $otherAccount = $this->createMock(Account::class);
        $otherAccount->method('getClient')->willReturn($otherClient);

        $priceContract = $this->createMock(CommunicationPricePackage::class);
        $priceContract->method('getPrice')->willReturn(15.0);
        $priceContract->method('getPriceCurrency')->willReturn('USD');

        $ownPackage = $this->createMock(CommunicationClientPackage::class);
        $ownPackage->method('getTenant')->willReturn($targetAccount);
        $ownPackage->method('getName')->willReturn('Paquete propio');
        $ownPackage->method('getPriceClientPackage')->willReturn($priceContract);
        $ownPackage->method('getAmount')->willReturn(500.0);
        $ownPackage->method('getCurrency')->willReturn('CUP');

        $otherPackage = $this->createMock(CommunicationClientPackage::class);
        $otherPackage->method('getTenant')->willReturn($otherAccount);

        $promotion->method('getProducts')->willReturn(new ArrayCollection([$ownPackage, $otherPackage]));

        $this->repoReturning(CommunicationPromotions::class, $promotion);

        $this->catalogResolver->expects($this->never())->method('offerFor');

        $this->mailer->expects($this->once())->method('send')
            ->with($this->callback(function (TemplatedEmail $email) {
                $packages = $email->getContext()['packages'];
                $this->assertCount(1, $packages);
                $this->assertSame('Paquete propio', $packages[0]['name']);
                $this->assertSame(15.0, $packages[0]['price']);
                $this->assertSame('USD', $packages[0]['priceCurrency']);
                $this->assertSame(500.0, $packages[0]['amount']);
                $this->assertSame('CUP', $packages[0]['currency']);
                return true;
            }));

        ($this->handler)($this->message());
    }

    public function testV2PathResolvesPriceForTheTargetClientsAccount(): void
    {
        $promotion = $this->createMock(CommunicationPromotions::class);
        $promotion->method('getName')->willReturn('Promo V2');
        $promotion->method('getProducts')->willReturn(new ArrayCollection([]));
        $environment = $this->createMock(Environment::class);
        $promotion->method('getEnvironment')->willReturn($environment);

        $this->repoReturning(CommunicationPromotions::class, $promotion);

        $account = $this->createMock(Account::class);
        $this->targetAccountResolver->method('resolveOne')->with($environment, 10)->willReturn($account);

        $package = $this->createMock(CommunicationPackage::class);
        $package->method('getName')->willReturn('Paquete V2');
        $package->method('getDestinationAmount')->willReturn(500.0);
        $package->method('getDestinationCurrency')->willReturn('CUP');
        $this->packageRepository->method('findByPromotion')->with($promotion)->willReturn([$package]);

        $offer = new ResolvedPackageOffer(
            package: $package,
            price: 12.5,
            currency: 'USD',
            source: PackageOfferSourceEnum::TENANT_CONTRACT,
        );
        $this->catalogResolver->method('offerFor')->with($package, $account)->willReturn($offer);

        $this->mailer->expects($this->once())->method('send')
            ->with($this->callback(function (TemplatedEmail $email) {
                $packages = $email->getContext()['packages'];
                $this->assertCount(1, $packages);
                $this->assertSame('Paquete V2', $packages[0]['name']);
                $this->assertSame(12.5, $packages[0]['price']);
                $this->assertSame('USD', $packages[0]['priceCurrency']);
                $this->assertSame(500.0, $packages[0]['amount']);
                $this->assertSame('CUP', $packages[0]['currency']);
                return true;
            }));

        ($this->handler)($this->message());
    }

    public function testV2PathSendsEmailWithEmptyPackagesWhenClientHasNoActiveAccount(): void
    {
        $promotion = $this->createMock(CommunicationPromotions::class);
        $promotion->method('getName')->willReturn('Promo V2');
        $promotion->method('getProducts')->willReturn(new ArrayCollection([]));
        $environment = $this->createMock(Environment::class);
        $promotion->method('getEnvironment')->willReturn($environment);

        $this->repoReturning(CommunicationPromotions::class, $promotion);

        $this->targetAccountResolver->method('resolveOne')->willReturn(null);
        $this->packageRepository->expects($this->never())->method('findByPromotion');

        $this->mailer->expects($this->once())->method('send')
            ->with($this->callback(function (TemplatedEmail $email) {
                $this->assertSame([], $email->getContext()['packages']);
                return true;
            }));

        ($this->handler)($this->message());
    }

    public function testV2PathNullsPriceWhenOfferIsUnavailable(): void
    {
        // El paquete se sigue mostrando (mismo criterio que V1: un paquete
        // sin contrato/precio congelado igual aparece, con price null) —
        // solo que acá la causa es que PackageCatalogResolver no encontró
        // ningún proveedor que cubra la tupla.
        $promotion = $this->createMock(CommunicationPromotions::class);
        $promotion->method('getName')->willReturn('Promo V2');
        $promotion->method('getProducts')->willReturn(new ArrayCollection([]));
        $environment = $this->createMock(Environment::class);
        $promotion->method('getEnvironment')->willReturn($environment);

        $this->repoReturning(CommunicationPromotions::class, $promotion);

        $account = $this->createMock(Account::class);
        $this->targetAccountResolver->method('resolveOne')->willReturn($account);

        $package = $this->createMock(CommunicationPackage::class);
        $package->method('getName')->willReturn('Paquete sin cobertura');
        $package->method('getDestinationAmount')->willReturn(300.0);
        $package->method('getDestinationCurrency')->willReturn('CUP');
        $this->packageRepository->method('findByPromotion')->willReturn([$package]);

        $unavailable = new ResolvedPackageOffer(
            package: $package,
            price: 0.0,
            currency: 'USD',
            source: PackageOfferSourceEnum::UNAVAILABLE,
        );
        $this->catalogResolver->method('offerFor')->willReturn($unavailable);

        $this->mailer->expects($this->once())->method('send')
            ->with($this->callback(function (TemplatedEmail $email) {
                $packages = $email->getContext()['packages'];
                $this->assertCount(1, $packages);
                $this->assertNull($packages[0]['price']);
                $this->assertNull($packages[0]['priceCurrency']);
                return true;
            }));

        ($this->handler)($this->message());
    }
}
