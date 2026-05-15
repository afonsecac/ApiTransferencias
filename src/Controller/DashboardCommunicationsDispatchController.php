<?php

namespace App\Controller;

use App\DTO\DispatchConfigDto;
use App\DTO\Out\DispatchConfigOutDto;
use App\DTO\Out\DispatchPendingOutDto;
use App\Exception\MyCurrentException;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Service\CommunicationsDispatchService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class DashboardCommunicationsDispatchController extends AbstractController
{
    public function __construct(
        private readonly CommunicationsDispatchService $dispatchService,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/communications/dispatch-config', name: 'dashboard_communications_dispatch_config_get', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Estado del dispatch de comunicaciones', tag: 'Communications Dispatch', responseDto: DispatchConfigOutDto::class)]
    public function getConfig(): JsonResponse
    {
        return $this->json($this->buildResponse());
    }

    #[Route('/communications/dispatch-config', name: 'dashboard_communications_dispatch_config_patch', methods: ['PATCH'])]
    #[DashboardEndpoint(summary: 'Habilitar o pausar el dispatch de comunicaciones', tag: 'Communications Dispatch', responseDto: DispatchConfigOutDto::class)]
    public function updateConfig(DispatchConfigDto $dto): JsonResponse
    {
        try {
            $this->dispatchService->setEnabled($dto->getDispatchEnabled());
        } catch (\Exception $e) {
            throw new MyCurrentException('DISPATCH_CONFIG_ERROR', $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json($this->buildResponse());
    }

    #[Route('/communications/dispatch-config/dispatch-pending', name: 'dashboard_communications_dispatch_pending', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Encolar ventas pendientes de despacho', tag: 'Communications Dispatch', responseDto: DispatchPendingOutDto::class, responseStatusCode: 200)]
    public function dispatchPending(): JsonResponse
    {
        $user        = $this->getUser();
        $triggeredBy = $user instanceof UserInterface ? $user->getUserIdentifier() : null;

        try {
            $result = $this->dispatchService->dispatchPending($triggeredBy);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json($result);
    }

    #[Route('/communications/dispatch-config/pending-stream', name: 'dashboard_communications_dispatch_pending_stream', methods: ['GET'])]
    public function pendingStream(): StreamedResponse
    {
        $response = new StreamedResponse(function () {
            set_time_limit(0);

            $lastCount   = -1;
            $lastEnabled = null;
            $deadline    = time() + 270; // cierra antes de que el proxy corte (< 5 min)

            while (time() < $deadline && !connection_aborted()) {
                // Limpia la identity map para forzar lectura fresca de BD
                $this->em->clear();
                $this->dispatchService->invalidateCache();

                $count   = $this->dispatchService->countPendingUndispatched();
                $enabled = $this->dispatchService->isEnabled();

                if ($count !== $lastCount || $enabled !== $lastEnabled) {
                    $lastCount   = $count;
                    $lastEnabled = $enabled;

                    echo 'data: ' . json_encode([
                        'pendingCount'    => $count,
                        'dispatchEnabled' => $enabled,
                    ]) . "\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }

                // Heartbeat cada ciclo para mantener la conexión viva
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

    private function buildResponse(): array
    {
        return [
            'dispatchEnabled'  => $this->dispatchService->isEnabled(),
            'pendingCount'     => $this->dispatchService->countPendingUndispatched(),
        ];
    }
}
