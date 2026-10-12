<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Garantiza que las reglas de capas (MarketLayersTest) realmente detectan una
 * violación: analiza fixtures que las rompen a propósito y exige que PHPStan falle.
 */
final class RulesDetectViolationsTest extends TestCase
{
    /** @var list<string> */
    private static array $messages = [];
    private static int $exitCode = 0;

    public static function setUpBeforeClass(): void
    {
        $root = dirname(__DIR__, 2);
        $process = new Process([
            PHP_BINARY,
            $root . '/vendor/bin/phpstan',
            'analyse',
            '--configuration=' . __DIR__ . '/phpstan-violations.neon',
            '--error-format=json',
            '--no-progress',
            '--memory-limit=512M',
        ], $root);
        $process->setTimeout(120);
        $process->run();

        self::$exitCode = (int) $process->getExitCode();
        $json = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        foreach ($json['files'] ?? [] as $file) {
            foreach ($file['messages'] as $message) {
                self::$messages[] = ($message['identifier'] ?? '') . ' | ' . $message['message'];
            }
        }
    }

    public function testAnalysisFailsWhenARuleIsBroken(): void
    {
        self::assertNotSame(0, self::$exitCode, 'PHPStan debería fallar con los fixtures que rompen las reglas.');
    }

    public function testDoctrineMappingAttributesAreAllowedInDomain(): void
    {
        $reported = array_filter(self::$messages, static fn (string $m): bool => str_contains($m, 'EntidadConAtributos'));

        self::assertSame([], array_values($reported), 'Los atributos de Doctrine\\ORM\\Mapping son legales en Domain.');
    }

    /**
     * @dataProvider brokenRules
     */
    public function testEachRuleReportsItsViolation(string $identifier, string $fragment): void
    {
        $found = array_filter(
            self::$messages,
            static fn (string $m): bool => str_contains($m, $identifier) && str_contains($m, $fragment),
        );

        self::assertNotEmpty($found, sprintf('No se detectó la violación %s (%s). Mensajes: %s', $identifier, $fragment, implode("\n", self::$messages)));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function brokenRules(): iterable
    {
        yield 'Domain depende de Doctrine' => ['phpat.domainDoesNotDependOnFrameworkOrPersistence', 'UsaDoctrine'];
        yield 'Application depende de Infrastructure' => ['phpat.applicationDoesNotDependOnInfrastructure', 'UsaInfrastructure'];
        yield 'un contexto importa Infrastructure de otro' => ['phpat.contextsDoNotImportInfrastructureOfOtherContexts', 'LlamaAOtroContexto'];
        yield 'extiende CommonService' => ['phpat.newCodeDoesNotExtendCommonService', 'ExtiendeCommonService'];
    }
}
