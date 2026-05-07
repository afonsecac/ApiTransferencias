<?php

namespace App\Logger;

use Monolog\Attribute\AsMonologProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Symfony\Component\Messenger\Exception\TransportException;

/**
 * Degrada a WARNING los errores de socket AMQP que ocurren durante el reinicio
 * programado de los workers (--time-limit). Son transitorios y esperados; no deben
 * generar alertas por email.
 */
#[AsMonologProcessor]
class AmqpTransientErrorProcessor implements ProcessorInterface
{
    private const TRANSIENT_PATTERNS = [
        'Socket error: could not connect to host',
        'a socket error occurred',
        'Broken pipe',
        'Connection reset by peer',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        if ($record->level !== Level::Error) {
            return $record;
        }

        $exception = $record->context['exception'] ?? null;

        if ($this->isAmqpTransientException($exception)) {
            return $record->with(level: Level::Warning);
        }

        return $record;
    }

    private function isAmqpTransientException(mixed $exception): bool
    {
        if ($exception === null) {
            return false;
        }

        if ($exception instanceof \AMQPConnectionException) {
            return true;
        }

        if ($exception instanceof TransportException) {
            $message = $exception->getMessage();
            foreach (self::TRANSIENT_PATTERNS as $pattern) {
                if (str_contains($message, $pattern)) {
                    return true;
                }
            }

            $previous = $exception->getPrevious();
            if ($previous instanceof \AMQPConnectionException) {
                return true;
            }
        }

        return false;
    }
}
