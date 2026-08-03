<?php

namespace App\Tests\Service\Provider;

use App\DTO\UpdateProviderCredentialsDto;
use App\Entity\SysConfig;
use App\Enums\CommunicationProviderEnum;
use App\Exception\MyCurrentException;
use App\Provider\DTOne\DTOneCommunicationProvider;
use App\Provider\DTOne\DTOneHttpClient;
use App\Provider\DTOne\DTOneStatusMapper;
use App\Provider\Etecsa\EtecsaCommunicationProvider;
use App\Provider\Etecsa\EtecsaStatusMapper;
use App\Provider\ProviderCredentialsResolver;
use App\Provider\ProviderRegistry;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use App\Service\Etecsa\EtecsaGatewayClient;
use App\Service\Provider\ProviderCredentialsAdminService;
use App\Service\SysConfigCipher;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @covers \App\Service\Provider\ProviderCredentialsAdminService
 */
class ProviderCredentialsAdminServiceTest extends TestCase
{
    private const ENCRYPTION_KEY = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    private EntityManagerInterface&MockObject $em;
    private SysConfigRepository&MockObject $sysConfigRepo;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->sysConfigRepo = $this->createMock(SysConfigRepository::class);
    }

    /**
     * DTOneStatusMapper es `final`: se instancia real. Los proveedores no
     * necesitan funcionar de verdad para estos tests — solo aportan su
     * getConfigSchema() (base_url/api_key/api_secret, los 3 obligatorios).
     */
    private function registry(): ProviderRegistry
    {
        return new ProviderRegistry([
            new DTOneCommunicationProvider(
                $this->createMock(DTOneHttpClient::class),
                new DTOneStatusMapper(),
                new NullLogger(),
            ),
            // EtecsaStatusMapper es `final`: se instancia real, no se dobla.
            new EtecsaCommunicationProvider(
                $this->createMock(EtecsaGatewayClient::class),
                new EtecsaStatusMapper(),
                $this->createMock(EnvironmentRepository::class),
            ),
        ]);
    }

    private function service(string $encryptionKey = self::ENCRYPTION_KEY): ProviderCredentialsAdminService
    {
        $resolver = new ProviderCredentialsResolver($this->sysConfigRepo, $this->registry());

        return new ProviderCredentialsAdminService($this->em, $this->sysConfigRepo, $this->registry(), $resolver, $encryptionKey);
    }

    private function row(string $propertyName, string $value, bool $encrypted = false): SysConfig
    {
        $config = new SysConfig();
        $config->setPropertyName($propertyName);
        $config->setPropertyValue($value);
        $config->setIsEncrypted($encrypted);
        $config->setIsActive(true);

        return $config;
    }

    // ---- getStatus ----

    public function testGetStatusReportsFieldsPerEnvironmentAccordingToSchema(): void
    {
        $this->sysConfigRepo->method('findOneBy')->willReturnCallback(function (array $criteria) {
            return match ($criteria['propertyName']) {
                'provider.dtone.test.base_url' => $this->row('provider.dtone.test.base_url', 'https://preprod.example'),
                'provider.dtone.test.api_key' => $this->row('provider.dtone.test.api_key', 'cipher-text', true),
                default => null,
            };
        });

        $status = $this->service()->getStatus(CommunicationProviderEnum::DTONE);

        $this->assertSame('https://preprod.example', $status['test']['base_url']['value']);
        $this->assertTrue($status['test']['base_url']['configured']);
        $this->assertFalse($status['test']['base_url']['secret']);

        $this->assertNull($status['test']['api_key']['value']); // secreto: nunca se devuelve el valor
        $this->assertTrue($status['test']['api_key']['configured']);
        $this->assertTrue($status['test']['api_key']['secret']);
        $this->assertTrue($status['test']['api_key']['required']);

        $this->assertFalse($status['test']['api_secret']['configured']);

        $this->assertFalse($status['prod']['base_url']['configured']);
        $this->assertFalse($status['isFullyConfiguredTest']); // falta api_secret
        $this->assertFalse($status['isFullyConfiguredProd']);
        // Nunca se ha tocado el interruptor manual: activo por defecto en ambos entornos.
        $this->assertTrue($status['activeTest']);
        $this->assertTrue($status['activeProd']);
    }

    public function testGetStatusReportsManualActiveFlagPerEnvironment(): void
    {
        $this->sysConfigRepo->method('findOneBy')->willReturn(null);
        $this->sysConfigRepo->method('findCachedValue')->willReturnCallback(
            fn (string $key) => $key === 'provider.dtone.prod.active' ? '0' : null,
        );

        $status = $this->service()->getStatus(CommunicationProviderEnum::DTONE);

        $this->assertTrue($status['activeTest']);
        $this->assertFalse($status['activeProd']);
    }

    public function testGetStatusTreatsEmptyStringAsNotConfigured(): void
    {
        $this->sysConfigRepo->method('findOneBy')->willReturnCallback(function (array $criteria) {
            return $criteria['propertyName'] === 'provider.etecsa.prod.api_key'
                ? $this->row('provider.etecsa.prod.api_key', '', true)
                : null;
        });

        $status = $this->service()->getStatus(CommunicationProviderEnum::ETECSA);

        $this->assertFalse($status['prod']['api_key']['configured']);
    }

    // ---- upsert ----

    public function testUpsertCreatesNewEncryptedRowsAccordingToSchema(): void
    {
        $this->sysConfigRepo->method('findOneBy')->willReturn(null);

        $persisted = [];
        $this->em->expects($this->exactly(3))
            ->method('persist')
            ->willReturnCallback(function (SysConfig $config) use (&$persisted) {
                $persisted[$config->getPropertyName()] = $config;
            });
        $this->em->expects($this->once())->method('flush');
        $this->sysConfigRepo->expects($this->once())->method('invalidateCache');

        $dto = new UpdateProviderCredentialsDto([
            'base_url' => 'https://sandbox.example',
            'api_key' => 'my-api-key',
            'api_secret' => 'my-api-secret',
        ]);

        $this->service()->upsert(CommunicationProviderEnum::DTONE, 'TEST', $dto);

        $this->assertCount(3, $persisted);
        $baseUrlRow = $persisted['provider.dtone.test.base_url'];
        $this->assertFalse($baseUrlRow->isEncrypted());
        $this->assertSame('https://sandbox.example', $baseUrlRow->getPropertyValue());

        $apiKeyRow = $persisted['provider.dtone.test.api_key'];
        $this->assertTrue($apiKeyRow->isEncrypted());
        $this->assertSame('my-api-key', SysConfigCipher::decrypt($apiKeyRow->getPropertyValue(), self::ENCRYPTION_KEY));

        $apiSecretRow = $persisted['provider.dtone.test.api_secret'];
        $this->assertTrue($apiSecretRow->isEncrypted());
        $this->assertSame('my-api-secret', SysConfigCipher::decrypt($apiSecretRow->getPropertyValue(), self::ENCRYPTION_KEY));
    }

    public function testUpsertLeavesAbsentFieldsUntouched(): void
    {
        $existingApiKey = $this->row('provider.etecsa.prod.api_key', 'old-cipher', true);
        $this->sysConfigRepo->method('findOneBy')->willReturnCallback(function (array $criteria) use ($existingApiKey) {
            return $criteria['propertyName'] === 'provider.etecsa.prod.api_key' ? $existingApiKey : null;
        });

        $this->em->expects($this->once())->method('persist'); // solo base_url es nuevo
        $this->em->expects($this->once())->method('flush');

        $dto = new UpdateProviderCredentialsDto(['base_url' => 'https://prod.example']);
        $this->service()->upsert(CommunicationProviderEnum::ETECSA, 'PROD', $dto);

        $this->assertSame('old-cipher', $existingApiKey->getPropertyValue());
    }

    public function testUpsertRejectsUnknownFieldForProvider(): void
    {
        $this->sysConfigRepo->method('findOneBy')->willReturn(null);

        $this->expectException(MyCurrentException::class);

        // ETECSA no tiene 'username' en su esquema (eso es de CSQ).
        $this->service()->upsert(
            CommunicationProviderEnum::ETECSA,
            'TEST',
            new UpdateProviderCredentialsDto(['username' => 'abc']),
        );
    }

    public function testUpsertThrowsWhenEncryptionKeyMissingForSecretField(): void
    {
        $this->sysConfigRepo->method('findOneBy')->willReturn(null);

        $this->expectException(MyCurrentException::class);

        $this->service(encryptionKey: '')->upsert(
            CommunicationProviderEnum::DTONE,
            'TEST',
            new UpdateProviderCredentialsDto(['api_key' => 'abc']),
        );
    }

    public function testUpsertDoesNotRequireEncryptionKeyWhenOnlyNonSecretFieldChanges(): void
    {
        $this->sysConfigRepo->method('findOneBy')->willReturn(null);

        $this->service(encryptionKey: '')->upsert(
            CommunicationProviderEnum::DTONE,
            'TEST',
            new UpdateProviderCredentialsDto(['base_url' => 'https://sandbox.example']),
        );

        $this->addToAssertionCount(1); // no exception
    }

    // ---- setActive ----

    public function testSetActiveCreatesAnUnencryptedFlagRow(): void
    {
        $this->sysConfigRepo->method('findOneBy')->willReturn(null);

        $persisted = null;
        $this->em->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function (SysConfig $config) use (&$persisted) {
                $persisted = $config;
            });
        $this->em->expects($this->once())->method('flush');
        $this->sysConfigRepo->expects($this->once())->method('invalidateCache');

        $this->service()->setActive(CommunicationProviderEnum::DTONE, 'PROD', false);

        $this->assertSame('provider.dtone.prod.active', $persisted->getPropertyName());
        $this->assertFalse($persisted->isEncrypted());
        $this->assertSame('0', $persisted->getPropertyValue());
    }

    public function testSetActiveUpdatesTheExistingRowInsteadOfDuplicatingIt(): void
    {
        $existing = $this->row('provider.etecsa.test.active', '0');
        $this->sysConfigRepo->method('findOneBy')->willReturnCallback(
            fn (array $criteria) => $criteria['propertyName'] === 'provider.etecsa.test.active' ? $existing : null,
        );

        $this->em->expects($this->never())->method('persist');

        $this->service()->setActive(CommunicationProviderEnum::ETECSA, 'TEST', true);

        $this->assertSame('1', $existing->getPropertyValue());
    }
}
