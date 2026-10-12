<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Lo llama monitor.sh (cron del VPS) para saber si la cola `failed` de Messenger se está llenando o
 * guarda mensajes viejos. Códigos de salida: 0 = cola sana (sin salida), 1 = problema (el informe va a
 * stdout y pasa al cuerpo del aviso), 2 = no se pudo comprobar (p. ej. base de datos caída). El informe
 * solo incluye recuentos y tipos de mensaje, nunca el cuerpo de los mensajes.
 */
#[AsCommand(
    name: 'app:monitor:failed-queue',
    description: 'Comprueba la cola failed de Messenger y avisa si supera el umbral o tiene mensajes viejos (lo usa monitor.sh)',
)]
class FailedQueueMonitorCommand extends Command
{
    private const EXIT_UNABLE_TO_CHECK = 2;

    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('max-messages', null, InputOption::VALUE_REQUIRED, 'Mensajes permitidos antes de avisar', '10')
            ->addOption('max-age-hours', null, InputOption::VALUE_REQUIRED, 'Antigüedad máxima (horas) del mensaje más viejo', '24');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $maxMessages = max(0, (int) $input->getOption('max-messages'));
        $maxAgeHours = max(1, (int) $input->getOption('max-age-hours'));

        try {
            $summary = $this->connection->fetchAssociative(
                "SELECT COUNT(*) AS total, EXTRACT(EPOCH FROM (NOW() - MIN(created_at))) / 3600 AS oldest_hours FROM messenger_messages WHERE queue_name = 'failed'",
            );
            $byType = $this->connection->fetchAllKeyValue(
                "SELECT COALESCE((headers::json)->>'type', '(desconocido)') AS type, COUNT(*) FROM messenger_messages WHERE queue_name = 'failed' GROUP BY 1 ORDER BY 2 DESC",
            );
        } catch (\Throwable $e) {
            $output->writeln('No se pudo consultar la cola failed: ' . $e->getMessage());

            return self::EXIT_UNABLE_TO_CHECK;
        }

        $total = (int) ($summary['total'] ?? 0);
        $oldestHours = (float) ($summary['oldest_hours'] ?? 0);

        $problems = [];
        if ($total > $maxMessages) {
            $problems[] = sprintf('La cola failed de Messenger tiene %d mensajes (límite %d).', $total, $maxMessages);
        }
        if ($total > 0 && $oldestHours > $maxAgeHours) {
            $problems[] = sprintf(
                'Hay %s con más de %d h sin atender (el más antiguo lleva %s).',
                1 === $total ? '1 mensaje' : 'mensajes',
                $maxAgeHours,
                $this->humanAge($oldestHours),
            );
        }

        if ([] === $problems) {
            return Command::SUCCESS;
        }

        $lines = $problems;
        if ($total > 0) {
            $lines[] = '';
            $lines[] = 'Por tipo de mensaje:';
            foreach ($byType as $type => $count) {
                $lines[] = sprintf('- %s: %d', $this->shortName((string) $type), (int) $count);
            }
            $lines[] = '';
            $lines[] = 'Revisar con: php bin/console messenger:failed:show';
        }
        $output->writeln($lines);

        return Command::FAILURE;
    }

    private function shortName(string $class): string
    {
        $position = strrpos($class, '\\');

        return false === $position ? $class : substr($class, $position + 1);
    }

    private function humanAge(float $hours): string
    {
        return $hours >= 48 ? sprintf('%d días', (int) floor($hours / 24)) : sprintf('%d h', (int) floor($hours));
    }
}
