<?php

namespace App\Tests\Command\StagingSync;

use App\Command\StagingSync\ReencryptProviderSecretsCommand;
use App\Entity\SysConfig;
use App\Repository\SysConfigRepository;
use App\Service\SysConfigCipher;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \App\Command\StagingSync\ReencryptProviderSecretsCommand
 */
class ReencryptProviderSecretsCommandTest extends TestCase
{
    private function prodKey(): string
    {
        return str_repeat('1', 64);
    }

    private function localKey(): string
    {
        return str_repeat('2', 64);
    }

    private function configRow(string $propertyName, string $plainValue, bool $encrypted, string $withKey = ''): SysConfig
    {
        $row = new SysConfig();
        $row->setPropertyName($propertyName);
        $row->setIsEncrypted($encrypted);
        $row->setPropertyValue($encrypted ? SysConfigCipher::encrypt($plainValue, $withKey) : $plainValue);

        return $row;
    }

    private function tester(SysConfigRepository $repo, ?string $localKey = null): CommandTester
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('flush');

        $application = new Application();
        $application->addCommand(new ReencryptProviderSecretsCommand($em, $repo, $localKey ?? $this->localKey()));

        return new CommandTester($application->find('app:staging-sync:reencrypt-provider-secrets'));
    }

    public function testReencryptsOnlyEncryptedProviderRowsWithTheLocalKey(): void
    {
        $providerSecret = $this->configRow('provider.etecsa.prod.api_key', 'secreto-real', encrypted: true, withKey: $this->prodKey());
        $providerPlain = $this->configRow('provider.etecsa.prod.base_url', 'https://etecsa.example', encrypted: false);
        $unrelated = $this->configRow('communications.dispatch.enabled', '1', encrypted: false);

        $repo = $this->createMock(SysConfigRepository::class);
        $repo->method('findBy')->with(['isEncrypted' => true])->willReturn([$providerSecret]);
        $repo->expects($this->once())->method('invalidateCache');

        putenv('PROD_SYS_CONFIG_KEY=' . $this->prodKey());
        try {
            $exitCode = $this->tester($repo)->execute([]);
        } finally {
            putenv('PROD_SYS_CONFIG_KEY');
        }

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame(
            'secreto-real',
            SysConfigCipher::decrypt((string) $providerSecret->getPropertyValue(), $this->localKey()),
        );
        // Los no-cifrados y los que no son de provider.% no se tocan.
        $this->assertSame('https://etecsa.example', $providerPlain->getPropertyValue());
        $this->assertSame('1', $unrelated->getPropertyValue());
    }

    public function testFailsWithoutTheProdKeyInTheEnvironment(): void
    {
        $repo = $this->createMock(SysConfigRepository::class);
        $repo->expects($this->never())->method('findBy');

        putenv('PROD_SYS_CONFIG_KEY');
        $exitCode = $this->tester($repo)->execute([]);

        $this->assertSame(Command::INVALID, $exitCode);
    }

    public function testFailsWithoutALocalEncryptionKeyConfigured(): void
    {
        $repo = $this->createMock(SysConfigRepository::class);
        $repo->expects($this->never())->method('findBy');

        putenv('PROD_SYS_CONFIG_KEY=' . $this->prodKey());
        try {
            $exitCode = $this->tester($repo, localKey: '')->execute([]);
        } finally {
            putenv('PROD_SYS_CONFIG_KEY');
        }

        $this->assertSame(Command::INVALID, $exitCode);
    }
}
