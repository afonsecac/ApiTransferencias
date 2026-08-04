<?php

namespace App\Service;

use App\Entity\StagingSyncRun;
use App\Entity\User;
use App\Enums\StagingSyncStatusEnum;
use App\Exception\MyCurrentException;
use App\Repository\StagingSyncRunRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puente entre el dashboard y scripts/sync-prod-to-staging.sh — el
 * contenedor php-fpm no tiene socket de Docker, CLI de Docker ni la llave
 * SSH que usa ese script (corre en el host como deploy@), así que
 * trigger() NUNCA ejecuta nada: solo escribe un archivo de bandera en un
 * directorio montado (`var/staging-sync-trigger/`). Un cron nuevo en el
 * host (scripts/staging-sync-watcher.sh, cada 2 min) lo recoge, lo borra y
 * ejecuta el script real — que a su vez reporta de vuelta con
 * report() (vía `bin/console app:staging-sync:report`, ver
 * ReportStagingSyncCommand) en cada checkpoint. Mismo mecanismo tanto si lo
 * disparó el cron mensual como un admin desde el dashboard.
 */
class StagingSyncService
{
    private const TRIGGER_FILE = 'request.json';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly StagingSyncRunRepository $repository,
        #[Autowire('%kernel.project_dir%/var/staging-sync-trigger')]
        private readonly string $triggerDir,
    ) {
    }

    /**
     * Escribe la bandera que scripts/staging-sync-watcher.sh recoge. No
     * dispara nada directamente — ver el docblock de la clase.
     */
    public function trigger(?User $user): void
    {
        if ($this->hasPendingTrigger()) {
            throw new MyCurrentException(
                'STAGING_SYNC_ALREADY_RUNNING',
                'Ya hay una sincronización pendiente de recoger por el watcher.',
                Response::HTTP_CONFLICT,
            );
        }

        $latest = $this->repository->findLatest();
        if ($latest?->getStatus() === StagingSyncStatusEnum::RUNNING) {
            throw new MyCurrentException(
                'STAGING_SYNC_ALREADY_RUNNING',
                'Ya hay una sincronización en curso.',
                Response::HTTP_CONFLICT,
            );
        }

        if (!is_dir($this->triggerDir)) {
            mkdir($this->triggerDir, 0770, true);
        }

        file_put_contents(
            $this->triggerDir . '/' . self::TRIGGER_FILE,
            json_encode([
                'triggeredBy' => $user?->getEmail(),
                'requestedAt' => (new \DateTimeImmutable('now'))->format(DATE_ATOM),
            ]),
        );
    }

    /**
     * Llamado por scripts/sync-prod-to-staging.sh en cada checkpoint (inicio,
     * éxito, fallo) vía ReportStagingSyncCommand. RUNNING siempre crea una
     * corrida nueva; SUCCESS/FAILED cierran la corrida RUNNING más reciente
     * (o crean una si por lo que sea no la encuentran, para no perder el dato).
     */
    public function report(StagingSyncStatusEnum $status, ?string $triggeredBy, ?string $error): void
    {
        $now = new \DateTimeImmutable('now');

        if ($status === StagingSyncStatusEnum::RUNNING) {
            $run = (new StagingSyncRun())
                ->setStatus(StagingSyncStatusEnum::RUNNING)
                ->setTriggeredBy($triggeredBy)
                ->setStartedAt($now);
            $this->em->persist($run);
            $this->em->flush();

            return;
        }

        $run = $this->repository->findOneBy(['status' => StagingSyncStatusEnum::RUNNING], ['id' => 'DESC']);
        if ($run === null) {
            $run = (new StagingSyncRun())
                ->setTriggeredBy($triggeredBy)
                ->setStartedAt($now);
            $this->em->persist($run);
        }

        $run->setStatus($status)
            ->setFinishedAt($now)
            ->setErrorMessage($status === StagingSyncStatusEnum::FAILED ? $error : null);

        $this->em->flush();
    }

    public function latest(): ?StagingSyncRun
    {
        return $this->repository->findLatest();
    }

    /**
     * @return StagingSyncRun[]
     */
    public function recent(): array
    {
        return $this->repository->findRecent();
    }

    private function hasPendingTrigger(): bool
    {
        return is_file($this->triggerDir . '/' . self::TRIGGER_FILE);
    }
}
