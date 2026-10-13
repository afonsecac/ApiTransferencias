<?php

declare(strict_types=1);

namespace App\Tests\Config;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Topología de RabbitMQ que declara config/packages/messenger.yaml.
 *
 * El transporte AMQP de Symfony crea los exchanges como FANOUT salvo que se indique otro tipo: un fanout copia cada
 * mensaje a TODAS las colas enlazadas e ignora `binding_keys`. Si dos transportes comparten exchange, cada mensaje de
 * uno llega también a la cola del otro y, como el worker consume todas las colas y el manejador se elige por la clase
 * del mensaje, se procesa dos veces (recargas y consultas de estado repetidas, correos duplicados). Estas guardas
 * impiden que vuelva a pasar al añadir un transporte nuevo.
 */
final class MessengerTopologyTest extends TestCase
{
    /**
     * Transportes AMQP: nombre => configuración del exchange y de sus colas.
     *
     * @return array<string, array{exchange: string, type: string, queues: list<string>}>
     */
    private static function amqpTransports(): array
    {
        $config = Yaml::parseFile(dirname(__DIR__, 2) . '/config/packages/messenger.yaml');
        $transports = $config['framework']['messenger']['transports'] ?? [];
        self::assertIsArray($transports);

        $amqp = [];
        foreach ($transports as $name => $transport) {
            $exchange = is_array($transport) ? ($transport['options']['exchange']['name'] ?? null) : null;
            if ($exchange === null) {
                continue; // doctrine://, in-memory://, failed…
            }
            $amqp[(string) $name] = [
                'exchange' => (string) $exchange,
                'type' => (string) ($transport['options']['exchange']['type'] ?? 'fanout'),
                'queues' => array_map('strval', array_keys($transport['options']['queues'] ?? [])),
            ];
        }

        return $amqp;
    }

    public function testThereAreAmqpTransportsToCheck(): void
    {
        self::assertNotEmpty(self::amqpTransports(), 'Si no se detecta ningún transporte AMQP, las demás guardas no comprueban nada.');
    }

    public function testNoTwoTransportsShareAnExchange(): void
    {
        $byExchange = [];
        foreach (self::amqpTransports() as $name => $transport) {
            $byExchange[$transport['exchange']][] = $name;
        }
        $shared = array_filter($byExchange, static fn (array $transports): bool => count($transports) > 1);

        self::assertSame([], $shared, 'Exchanges compartidos entre transportes (exchange => transportes): cada mensaje se entregaría a las colas de todos.');
    }

    public function testAFanoutExchangeHasExactlyOneQueue(): void
    {
        $offenders = [];
        foreach (self::amqpTransports() as $name => $transport) {
            if ($transport['type'] === 'fanout' && count($transport['queues']) !== 1) {
                $offenders[$name] = $transport['queues'];
            }
        }

        self::assertSame([], $offenders, 'Un exchange fanout copia cada mensaje a todas sus colas: debe tener una sola.');
    }

    public function testNoTwoTransportsShareAQueue(): void
    {
        $byQueue = [];
        foreach (self::amqpTransports() as $name => $transport) {
            foreach ($transport['queues'] as $queue) {
                $byQueue[$queue][] = $name;
            }
        }
        $shared = array_filter($byQueue, static fn (array $transports): bool => count($transports) > 1);

        self::assertSame([], $shared, 'Una cola compartida entre transportes la consumirían dos veces.');
    }
}
