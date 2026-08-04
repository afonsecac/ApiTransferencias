<?php

namespace App\Tests\Service\Provider;

use App\Entity\ProviderAvailability;
use App\Entity\User;
use App\Enums\CommunicationProviderEnum;
use App\Enums\ProviderActionTypeEnum;
use App\Exception\MyCurrentException;
use App\Provider\Contract\CommunicationProviderInterface;
use App\Provider\Contract\ProviderConfigField;
use App\Provider\Contract\ProviderPingResult;
use App\Provider\ProviderCredentialsResolver;
use App\Provider\ProviderRegistry;
use App\Repository\ProviderAvailabilityRepository;
use App\Repository\SysConfigRepository;
use App\Service\Provider\ProviderAvailabilityService;
use App\Service\Provider\ProviderCredentialsAdminService;
use App\Service\NotificationCenterService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @covers \App\Service\Provider\ProviderAvailabilityService
 */
class ProviderAvailabilityServiceTest extends TestCase
{
    private function fakeAdapter(): CommunicationProviderInterface
    {
        return new class implements CommunicationProviderInterface {
            public function getCode(): CommunicationProviderEnum
            {
                return CommunicationProviderEnum::CSQ;
            }

            public function getCapabilities(): array
            {
                return [];
            }

            public function getConfigSchema(): array
            {
                return [new ProviderConfigField('token', 'Token', required: true, secret: false)];
            }
        };
    }

    /**
     * @param array<string, ?string> $sysConfigValues
     */
    private function makeService(
        array $sysConfigValues,
        ?ProviderAvailabilityRepository $repository = null,
        ?EntityManagerInterface $em = null,
        ?ProviderCredentialsAdminService $credentialsAdminService = null,
        ?NotificationCenterService $notificationCenter = null,
        ?Security $security = null,
    ): ProviderAvailabilityService {
        $sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $sysConfigRepo->method('findCachedValue')
            ->willReturnCallback(static fn (string $key, bool $mustBeActive = false) => $sysConfigValues[$key] ?? null);

        $registry = new ProviderRegistry([$this->fakeAdapter()]);
        $credentialsResolver = new ProviderCredentialsResolver($sysConfigRepo, $registry);

        return new ProviderAvailabilityService(
            $em ?? $this->createMock(EntityManagerInterface::class),
            $repository ?? $this->createMock(ProviderAvailabilityRepository::class),
            $credentialsResolver,
            $credentialsAdminService ?? $this->createMock(ProviderCredentialsAdminService::class),
            $registry,
            $sysConfigRepo,
            $notificationCenter ?? $this->createMock(NotificationCenterService::class),
            $security ?? $this->createMock(Security::class),
            new NullLogger(),
        );
    }

    private function repositoryReturning(?ProviderAvailability $row): ProviderAvailabilityRepository&MockObject
    {
        $repository = $this->createMock(ProviderAvailabilityRepository::class);
        $repository->method('findOneByProviderAndType')->willReturn($row);

        return $repository;
    }

    // ---- canDispatchTo: tabla de verdad ----

    public function testCanDispatchToFalseWhenGlobalDisabled(): void
    {
        $service = $this->makeService([
            'communications.dispatch.enabled' => '0',
            'provider.csq.test.token' => 'x',
            'provider.csq.test.active' => '1',
        ]);

        $this->assertFalse($service->canDispatchTo('CSQ', 'TEST'));
    }

    public function testCanDispatchToFalseWhenNotConfigured(): void
    {
        $service = $this->makeService([
            'communications.dispatch.enabled' => '1',
            'provider.csq.test.active' => '1',
            // sin provider.csq.test.token: no configurado
        ]);

        $this->assertFalse($service->canDispatchTo('CSQ', 'TEST'));
    }

    public function testCanDispatchToFalseWhenManualInactive(): void
    {
        $service = $this->makeService([
            'communications.dispatch.enabled' => '1',
            'provider.csq.test.token' => 'x',
            'provider.csq.test.active' => '0',
        ]);

        $this->assertFalse($service->canDispatchTo('CSQ', 'TEST'));
    }

    public function testCanDispatchToDefaultsAutoEnabledWhenNoRowExistsYet(): void
    {
        $service = $this->makeService([
            'communications.dispatch.enabled' => '1',
            'provider.csq.test.token' => 'x',
            'provider.csq.test.active' => '1',
        ], repository: $this->repositoryReturning(null));

        $this->assertTrue($service->canDispatchTo('CSQ', 'TEST'));
    }

    public function testCanDispatchToFalseWhenAutoDisabledByPing(): void
    {
        $row = (new ProviderAvailability())->setProvider('CSQ')->setEnvironmentType('TEST')->setAutoEnabled(false);

        $service = $this->makeService([
            'communications.dispatch.enabled' => '1',
            'provider.csq.test.token' => 'x',
            'provider.csq.test.active' => '1',
        ], repository: $this->repositoryReturning($row));

        $this->assertFalse($service->canDispatchTo('CSQ', 'TEST'));
    }

    // ---- recordPing ----

    public function testRecordPingDisablesAutoOnFirstFailureAndNotifies(): void
    {
        $row = (new ProviderAvailability())->setProvider('CSQ')->setEnvironmentType('TEST')->setAutoEnabled(true);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->once())->method('flush');

        $notificationCenter = $this->createMock(NotificationCenterService::class);
        $notificationCenter->expects($this->once())->method('bumpGroup');

        $service = $this->makeService(
            [],
            repository: $this->repositoryReturning($row),
            em: $em,
            notificationCenter: $notificationCenter,
        );

        $result = $service->recordPing(CommunicationProviderEnum::CSQ, 'TEST', ProviderPingResult::unavailable('timeout'));

        $this->assertFalse($result);
        $this->assertFalse($row->isAutoEnabled());
        $this->assertSame(ProviderActionTypeEnum::AUTO, $row->getLastActionType());
        $this->assertSame('timeout', $row->getLastPingError());
    }

    public function testRecordPingReturnsTrueWhenRecoveryMakesItDispatchableAgain(): void
    {
        $row = (new ProviderAvailability())->setProvider('CSQ')->setEnvironmentType('TEST')->setAutoEnabled(false);

        $em = $this->createMock(EntityManagerInterface::class);
        $notificationCenter = $this->createMock(NotificationCenterService::class);
        $notificationCenter->expects($this->once())->method('bumpGroup');

        $service = $this->makeService([
            'communications.dispatch.enabled' => '1',
            'provider.csq.test.token' => 'x',
            'provider.csq.test.active' => '1',
        ], repository: $this->repositoryReturning($row), em: $em, notificationCenter: $notificationCenter);

        $result = $service->recordPing(CommunicationProviderEnum::CSQ, 'TEST', ProviderPingResult::available(120));

        $this->assertTrue($result);
        $this->assertTrue($row->isAutoEnabled());
    }

    public function testRecordPingRecoversAutoButStaysNonDispatchableWhenManualIsOff(): void
    {
        // Requisito 4, caso simétrico: el ping SÍ puede volver a poner AUTO en
        // true (no queda bloqueado por un apagado manual previo), pero no
        // reencola nada porque el interruptor MANUAL sigue mandando.
        $row = (new ProviderAvailability())->setProvider('CSQ')->setEnvironmentType('TEST')->setAutoEnabled(false);

        $service = $this->makeService([
            'communications.dispatch.enabled' => '1',
            'provider.csq.test.token' => 'x',
            'provider.csq.test.active' => '0',
        ], repository: $this->repositoryReturning($row));

        $result = $service->recordPing(CommunicationProviderEnum::CSQ, 'TEST', ProviderPingResult::available(50));

        $this->assertFalse($result);
        $this->assertTrue($row->isAutoEnabled());
    }

    public function testRecordPingInconclusiveDoesNotChangeAuto(): void
    {
        $row = (new ProviderAvailability())->setProvider('CSQ')->setEnvironmentType('TEST')->setAutoEnabled(true);

        $notificationCenter = $this->createMock(NotificationCenterService::class);
        $notificationCenter->expects($this->once())->method('bumpGroup');

        $service = $this->makeService(
            [],
            repository: $this->repositoryReturning($row),
            notificationCenter: $notificationCenter,
        );

        $result = $service->recordPing(CommunicationProviderEnum::CSQ, 'TEST', ProviderPingResult::inconclusive('401'));

        $this->assertFalse($result);
        $this->assertTrue($row->isAutoEnabled());
        $this->assertNull($row->getLastActionType());
    }

    // ---- setManual ----

    public function testSetManualThrowsWhenActivatingUnconfiguredProvider(): void
    {
        $credentialsAdminService = $this->createMock(ProviderCredentialsAdminService::class);
        $credentialsAdminService->expects($this->never())->method('setActive');

        $service = $this->makeService([], credentialsAdminService: $credentialsAdminService);

        $this->expectException(MyCurrentException::class);

        $service->setManual(CommunicationProviderEnum::CSQ, 'TEST', true);
    }

    public function testSetManualEnableDelegatesResetsAutoAndAudits(): void
    {
        $row = (new ProviderAvailability())->setProvider('CSQ')->setEnvironmentType('TEST')->setAutoEnabled(false);

        $credentialsAdminService = $this->createMock(ProviderCredentialsAdminService::class);
        $credentialsAdminService->expects($this->once())
            ->method('setActive')
            ->with(CommunicationProviderEnum::CSQ, 'TEST', true);

        $user = $this->createMock(User::class);
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $service = $this->makeService(
            ['provider.csq.test.token' => 'x'],
            repository: $this->repositoryReturning($row),
            em: $em,
            credentialsAdminService: $credentialsAdminService,
            security: $security,
        );

        $service->setManual(CommunicationProviderEnum::CSQ, 'TEST', true, 'reactivación manual');

        $this->assertTrue($row->isAutoEnabled());
        $this->assertSame(ProviderActionTypeEnum::MANUAL, $row->getLastActionType());
        $this->assertSame($user, $row->getLastActionBy());
        $this->assertSame('reactivación manual', $row->getLastActionReason());
    }

    public function testSetManualDisableDoesNotTouchAuto(): void
    {
        $row = (new ProviderAvailability())->setProvider('CSQ')->setEnvironmentType('TEST')->setAutoEnabled(false);

        $service = $this->makeService(
            [],
            repository: $this->repositoryReturning($row),
            credentialsAdminService: $this->createMock(ProviderCredentialsAdminService::class),
        );

        $service->setManual(CommunicationProviderEnum::CSQ, 'TEST', false);

        $this->assertFalse($row->isAutoEnabled());
        $this->assertSame(ProviderActionTypeEnum::MANUAL, $row->getLastActionType());
    }

    // ---- statusMatrix ----

    public function testStatusMatrixReflectsEffectiveFlagPerEnvironment(): void
    {
        $service = $this->makeService([
            'communications.dispatch.enabled' => '1',
            'provider.csq.test.token' => 'x',
            'provider.csq.test.active' => '1',
            // provider.csq.prod.token ausente: PROD no configurado
        ], repository: $this->repositoryReturning(null));

        $matrix = $service->statusMatrix();
        $byEnv = [];
        foreach ($matrix as $row) {
            $byEnv[$row['environmentType']] = $row;
        }

        $this->assertTrue($byEnv['TEST']['configured']);
        $this->assertTrue($byEnv['TEST']['effective']);
        $this->assertFalse($byEnv['PROD']['configured']);
        $this->assertFalse($byEnv['PROD']['effective']);
    }
}
