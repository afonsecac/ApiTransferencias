<?php

namespace App\Tests\Provider;

use App\Entity\Account;
use App\Entity\Client;
use App\Entity\ClientProviderRouting;
use App\Entity\CommunicationProduct;
use App\Entity\CommunicationPromotionProviderProduct;
use App\Entity\CommunicationPromotions;
use App\Entity\Environment;
use App\Enums\CommunicationProviderEnum;
use App\Exception\MyCurrentException;
use App\Provider\ProviderResolver;
use App\Provider\PromotionProviderDispatchResolver;
use App\Repository\ClientProviderRoutingRepository;
use App\Repository\CommunicationPromotionProviderProductRepository;
use App\Repository\SysConfigRepository;
use App\Service\Provider\ProviderAvailabilityService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Provider\PromotionProviderDispatchResolver
 *
 * A diferencia de ProviderDispatchResolver (paquetes V2), aquí NO hay
 * matching automático por tupla — toda asociación proveedor→producto debe
 * ser explícita (CommunicationPromotionProviderProduct), con el único
 * fallback implícito del producto "de origen" de la promoción cuando su
 * proveedor coincide con el candidato evaluado.
 */
class PromotionProviderDispatchResolverTest extends TestCase
{
    private ClientProviderRoutingRepository&MockObject $routingRepo;
    private CommunicationPromotionProviderProductRepository&MockObject $promotionBindingRepo;
    private ProviderAvailabilityService&MockObject $availabilityService;
    private SysConfigRepository&MockObject $sysConfigRepo;
    private PromotionProviderDispatchResolver $resolver;

    protected function setUp(): void
    {
        $this->routingRepo = $this->createMock(ClientProviderRoutingRepository::class);
        $this->promotionBindingRepo = $this->createMock(CommunicationPromotionProviderProductRepository::class);
        $this->availabilityService = $this->createMock(ProviderAvailabilityService::class);
        $this->sysConfigRepo = $this->createMock(SysConfigRepository::class);

        $this->promotionBindingRepo->method('findForPromotionAndProvider')->willReturn(null);

        $this->resolver = new PromotionProviderDispatchResolver(
            $this->routingRepo,
            $this->promotionBindingRepo,
            $this->availabilityService,
            $this->sysConfigRepo,
        );

        $this->sysConfigRepo->method('findCachedValue')
            ->willReturnCallback(fn (string $key) => $key === ProviderResolver::ROUTING_ENABLED_KEY ? '1' : null);
    }

    private function account(int $clientId): Account&MockObject
    {
        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn($clientId);

        $account = $this->createMock(Account::class);
        $account->method('getClient')->willReturn($client);
        $account->method('getEnvironment')->willReturn($this->createMock(Environment::class));

        return $account;
    }

    private function routing(string $provider): ClientProviderRouting&MockObject
    {
        $routing = $this->createMock(ClientProviderRouting::class);
        $routing->method('getProvider')->willReturn($provider);

        return $routing;
    }

    private function promotion(?CommunicationProduct $defaultProduct): CommunicationPromotions&MockObject
    {
        $promotion = $this->createMock(CommunicationPromotions::class);
        $promotion->method('getProduct')->willReturn($defaultProduct);

        return $promotion;
    }

    private function product(string $provider, string $externalRef, bool $enabled = true): CommunicationProduct&MockObject
    {
        $product = $this->createMock(CommunicationProduct::class);
        $product->method('getProvider')->willReturn($provider);
        $product->method('getExternalRef')->willReturn($externalRef);
        $product->method('isEnabled')->willReturn($enabled);

        return $product;
    }

    public function testUsesTheExplicitBindingForTheSelectedProvider(): void
    {
        $account = $this->account(1);
        $bound = $this->product('CSQ', 'ref-bound');
        $binding = $this->createMock(CommunicationPromotionProviderProduct::class);
        $binding->method('getProduct')->willReturn($bound);
        $promotion = $this->promotion(null);

        $this->routingRepo->method('findActiveProvidersOrderedForClient')->willReturn([$this->routing('CSQ')]);
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->promotionBindingRepo = $this->createMock(CommunicationPromotionProviderProductRepository::class);
        $this->promotionBindingRepo->method('findForPromotionAndProvider')
            ->willReturnCallback(fn ($p, $provider) => $provider === 'CSQ' ? $binding : null);
        $this->resolver = new PromotionProviderDispatchResolver(
            $this->routingRepo,
            $this->promotionBindingRepo,
            $this->availabilityService,
            $this->sysConfigRepo,
        );

        $selected = $this->resolver->select($account, $promotion);

        $this->assertSame(CommunicationProviderEnum::CSQ, $selected->provider);
        $this->assertSame('ref-bound', $selected->externalRef);
    }

    public function testFallsBackToTheDefaultProductWhenItsProviderMatchesTheCandidate(): void
    {
        // Ninguna promoción existente hoy tiene vínculos explícitos — este
        // es el caso de regresión crítico: debe comportarse exactamente
        // igual que antes de este cambio.
        $account = $this->account(1);
        $default = $this->product('ETECSA', 'ref-default');
        $promotion = $this->promotion($default);

        $this->routingRepo->method('findActiveProvidersOrderedForClient')->willReturn([$this->routing('ETECSA')]);
        $this->availabilityService->method('canDispatchTo')->willReturn(true);

        $selected = $this->resolver->select($account, $promotion);

        $this->assertSame(CommunicationProviderEnum::ETECSA, $selected->provider);
        $this->assertSame('ref-default', $selected->externalRef);
    }

    public function testDoesNotUseTheDefaultProductWhenItsProviderDoesNotMatchTheCandidate(): void
    {
        $account = $this->account(1);
        $default = $this->product('ETECSA', 'ref-default');
        $promotion = $this->promotion($default);

        // El único candidato es CSQ, pero el producto "de origen" es de
        // ETECSA — sin vínculo explícito, CSQ no tiene nada que ofrecer.
        $this->routingRepo->method('findActiveProvidersOrderedForClient')->willReturn([$this->routing('CSQ')]);
        $this->availabilityService->method('canDispatchTo')->willReturn(true);

        $this->expectException(MyCurrentException::class);

        $this->resolver->select($account, $promotion);
    }

    public function testSkipsAProviderThatIsNotAvailableAndTriesTheNextOne(): void
    {
        $account = $this->account(1);
        $bound = $this->product('DTONE', 'ref-dtone');
        $binding = $this->createMock(CommunicationPromotionProviderProduct::class);
        $binding->method('getProduct')->willReturn($bound);
        $promotion = $this->promotion(null);

        $this->routingRepo->method('findActiveProvidersOrderedForClient')
            ->willReturn([$this->routing('CSQ'), $this->routing('DTONE')]);
        $this->availabilityService->method('canDispatchTo')
            ->willReturnCallback(fn ($provider) => $provider !== 'CSQ');
        $this->promotionBindingRepo = $this->createMock(CommunicationPromotionProviderProductRepository::class);
        $this->promotionBindingRepo->method('findForPromotionAndProvider')
            ->willReturnCallback(fn ($p, $provider) => $provider === 'DTONE' ? $binding : null);
        $this->resolver = new PromotionProviderDispatchResolver(
            $this->routingRepo,
            $this->promotionBindingRepo,
            $this->availabilityService,
            $this->sysConfigRepo,
        );

        $selected = $this->resolver->select($account, $promotion);

        $this->assertSame(CommunicationProviderEnum::DTONE, $selected->provider);
    }

    public function testSkipsAProviderWithNoProductAndTriesTheNextOne(): void
    {
        $account = $this->account(1);
        $bound = $this->product('DTONE', 'ref-dtone');
        $binding = $this->createMock(CommunicationPromotionProviderProduct::class);
        $binding->method('getProduct')->willReturn($bound);
        $promotion = $this->promotion(null);

        $this->routingRepo->method('findActiveProvidersOrderedForClient')
            ->willReturn([$this->routing('CSQ'), $this->routing('DTONE')]);
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->promotionBindingRepo = $this->createMock(CommunicationPromotionProviderProductRepository::class);
        $this->promotionBindingRepo->method('findForPromotionAndProvider')
            ->willReturnCallback(fn ($p, $provider) => $provider === 'DTONE' ? $binding : null);
        $this->resolver = new PromotionProviderDispatchResolver(
            $this->routingRepo,
            $this->promotionBindingRepo,
            $this->availabilityService,
            $this->sysConfigRepo,
        );

        $selected = $this->resolver->select($account, $promotion);

        $this->assertSame(CommunicationProviderEnum::DTONE, $selected->provider);
    }

    public function testIgnoresADisabledExplicitBindingAndFallsThroughToNoMatch(): void
    {
        $account = $this->account(1);
        $bound = $this->product('CSQ', 'ref-bound', enabled: false);
        $binding = $this->createMock(CommunicationPromotionProviderProduct::class);
        $binding->method('getProduct')->willReturn($bound);
        $promotion = $this->promotion(null);

        $this->routingRepo->method('findActiveProvidersOrderedForClient')->willReturn([$this->routing('CSQ')]);
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->promotionBindingRepo = $this->createMock(CommunicationPromotionProviderProductRepository::class);
        $this->promotionBindingRepo->method('findForPromotionAndProvider')->willReturn($binding);
        $this->resolver = new PromotionProviderDispatchResolver(
            $this->routingRepo,
            $this->promotionBindingRepo,
            $this->availabilityService,
            $this->sysConfigRepo,
        );

        $this->expectException(MyCurrentException::class);

        $this->resolver->select($account, $promotion);
    }

    public function testThrowsPromotionNotDispatchableWhenNoProviderIsAvailable(): void
    {
        $account = $this->account(1);
        $promotion = $this->promotion(null);

        $this->routingRepo->method('findActiveProvidersOrderedForClient')->willReturn([$this->routing('CSQ')]);
        $this->availabilityService->method('canDispatchTo')->willReturn(false);

        try {
            $this->resolver->select($account, $promotion);
            $this->fail('Se esperaba MyCurrentException');
        } catch (MyCurrentException $e) {
            $this->assertSame('PROMOTION_NOT_DISPATCHABLE', $e->getCodeWork());
            $this->assertSame(409, $e->getCode());
        }
    }

    public function testKillSwitchIgnoresRoutingTableAndTriesOnlyTheDefaultProvider(): void
    {
        $account = $this->account(1);
        $default = $this->product('ETECSA', 'ref-etecsa');
        $promotion = $this->promotion($default);

        $this->sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $this->sysConfigRepo->method('findCachedValue')
            ->willReturnCallback(fn (string $key) => $key === ProviderResolver::ROUTING_ENABLED_KEY ? '0' : null);
        $this->resolver = new PromotionProviderDispatchResolver(
            $this->routingRepo,
            $this->promotionBindingRepo,
            $this->availabilityService,
            $this->sysConfigRepo,
        );

        $this->routingRepo->expects($this->never())->method('findActiveProvidersOrderedForClient');
        $this->availabilityService->method('canDispatchTo')->willReturn(true);

        $selected = $this->resolver->select($account, $promotion);

        $this->assertSame(CommunicationProviderEnum::ETECSA, $selected->provider);
    }
}
