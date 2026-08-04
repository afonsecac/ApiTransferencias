<?php

namespace App\Controller;

use App\Entity\StagingSyncRun;
use App\Entity\User;
use App\Exception\MyCurrentException;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Service\StagingSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Disparo bajo demanda del sync prod->staging (scripts/sync-prod-to-staging.sh)
 * y su estado en vivo. Existe SOLO en la instancia real de producción — cada
 * acción empieza con assertProduction(), que devuelve 404 si esta instancia
 * no tiene DEPLOYMENT_STAGE=production (ver docker-compose.vps.yaml). Esta es
 * la defensa real: el frontend además ni siquiera compila la ruta fuera de un
 * build de producción, pero eso es defensa en profundidad, no la única barrera.
 */
#[Route('/admin/staging-sync')]
#[IsGranted('ROLE_ADMIN')]
class DashboardStagingSyncController extends AbstractController
{
    public function __construct(
        private readonly StagingSyncService $service,
        private readonly EntityManagerInterface $em,
        #[Autowire('%env(default::DEPLOYMENT_STAGE)%')]
        private readonly string $deploymentStage = '',
    ) {
    }

    #[Route('', name: 'dashboard_staging_sync_status', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Última corrida del sync prod->staging e historial reciente', tag: 'Staging Sync')]
    public function status(): JsonResponse
    {
        if ($notFound = $this->assertProduction()) {
            return $notFound;
        }

        return $this->json([
            'latest' => $this->serialize($this->service->latest()),
            'recent' => array_map($this->serialize(...), $this->service->recent()),
        ]);
    }

    #[Route('/trigger', name: 'dashboard_staging_sync_trigger', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    #[DashboardEndpoint(summary: 'Dispara el sync prod->staging bajo demanda', tag: 'Staging Sync')]
    public function trigger(): JsonResponse
    {
        if ($notFound = $this->assertProduction()) {
            return $notFound;
        }

        $user = $this->getUser();

        try {
            $this->service->trigger($user instanceof User ? $user : null);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json(['latest' => $this->serialize($this->service->latest())]);
    }

    #[Route('/stream', name: 'dashboard_staging_sync_stream', methods: ['GET'])]
    public function syncStream(): Response
    {
        if ($notFound = $this->assertProduction()) {
            return $notFound;
        }

        // Mismo patrón que DashboardProviderAvailabilityController::availabilityStream().
        if (session_status() === \PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $response = new StreamedResponse(function () {
            set_time_limit(0);

            $lastPayload = null;
            $deadline = time() + 270;

            while (time() < $deadline && !connection_aborted()) {
                $this->em->clear();

                $payload = json_encode($this->serialize($this->service->latest()));

                if ($payload !== $lastPayload) {
                    $lastPayload = $payload;
                    echo 'data: ' . $payload . "\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }

                echo ": ping\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();

                sleep(3);
            }

            echo "event: close\ndata: {}\n\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');
        $response->headers->set('Connection', 'keep-alive');

        return $response;
    }

    private function assertProduction(): ?JsonResponse
    {
        if ($this->deploymentStage !== 'production') {
            return $this->json(['error' => ['message' => 'No encontrado']], Response::HTTP_NOT_FOUND);
        }

        return null;
    }

    /**
     * @return array{id: int, status: string, triggeredBy: ?string, startedAt: string, finishedAt: ?string, errorMessage: ?string}|null
     */
    private function serialize(?StagingSyncRun $run): ?array
    {
        if ($run === null) {
            return null;
        }

        return [
            'id' => $run->getId(),
            'status' => $run->getStatus()?->value,
            'triggeredBy' => $run->getTriggeredBy(),
            'startedAt' => $run->getStartedAt()?->format(DATE_ATOM),
            'finishedAt' => $run->getFinishedAt()?->format(DATE_ATOM),
            'errorMessage' => $run->getErrorMessage(),
        ];
    }
}
