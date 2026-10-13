<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Test de contrato por evento de dominio (ADR-001).
 *
 * Serializa el evento con el mismo serializador que usa el transporte de Messenger y compara su forma
 * (clase y nombre y tipo de cada campo, ordenados) con el contrato guardado en tests/Contracts. Si
 * alguien añade, quita o cambia el tipo de un campo, el test falla y obliga a revisar a los consumidores.
 *
 * Para crear o actualizar un contrato a propósito: UPDATE_CONTRACTS=1 php bin/phpunit <test>.
 * Hay que revisar el diff del archivo antes de commitearlo.
 *
 * Debe usarse en una clase que extienda KernelTestCase.
 */
trait AssertsEventContract
{
    protected function assertEventContract(object $event, ?string $contractName = null): void
    {
        $contractName ??= (new \ReflectionClass($event))->getShortName();
        $encoded = $this->eventSerializer()->encode(new Envelope($event));

        $actual = [
            'type' => $encoded['headers']['type'] ?? null,
            'body' => self::shapeOf(json_decode((string) $encoded['body'], true, 512, JSON_THROW_ON_ERROR)),
        ];

        $file = dirname(__DIR__) . '/Contracts/' . $contractName . '.contract.json';

        if (getenv('UPDATE_CONTRACTS') === '1') {
            file_put_contents($file, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
        }

        if (!is_file($file)) {
            self::fail(sprintf('No existe el contrato %s. Créalo con UPDATE_CONTRACTS=1 y revísalo antes de commitearlo.', $file));
        }

        $expected = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(
            $expected,
            $actual,
            sprintf('El evento %s cambió de forma respecto a su contrato (%s). Revisa a sus consumidores antes de actualizarlo.', $contractName, basename($file)),
        );
    }

    protected function assertEventRoundTrip(object $event): void
    {
        $serializer = $this->eventSerializer();
        $decoded = $serializer->decode($serializer->encode(new Envelope($event)));

        self::assertEquals($event, $decoded->getMessage(), 'El evento no sobrevive a la ida y vuelta por el transporte.');
    }

    private function eventSerializer(): SerializerInterface
    {
        $serializer = static::getContainer()->get('messenger.transport.symfony_serializer');
        \assert($serializer instanceof SerializerInterface);

        return $serializer;
    }

    private static function shapeOf(mixed $value): mixed
    {
        if (is_array($value)) {
            if ($value === []) {
                return 'list<vacía>';
            }
            if (array_is_list($value)) {
                return ['list' => self::shapeOf($value[0])];
            }
            ksort($value);

            return array_map(self::shapeOf(...), $value);
        }

        return get_debug_type($value);
    }
}
