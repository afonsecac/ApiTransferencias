<?php

namespace App\Controller;

use App\DTO\CreateNotificationDto;
use App\DTO\Out\NotificationOutDto;
use App\DTO\Out\NotificationStreamTicketOutDto;
use App\DTO\Out\PaginatedListOutDto;
use App\Entity\User;
use App\Exception\MyCurrentException;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Service\NotificationCenterService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notifications')]
class DashboardNotificationsController extends AbstractController
{
    public function __construct(
        private readonly NotificationCenterService $service,
        private readonly EntityManagerInterface $em,
        private readonly int $streamInterval = 5,
    ) {
    }

    #[Route('', name: 'dashboard_notifications_list', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar notificaciones del usuario', tag: 'Notifications', responseDto: PaginatedListOutDto::class, itemDto: NotificationOutDto::class)]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getAuthUser();
        if ($user === null) {
            return $this->unauthorized();
        }

        $page = max(0, (int) $request->query->get('page', 0));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $orderBy = (string) $request->query->get('orderBy', 'createdAt DESC');
        $onlyUnread = $request->query->get('status') === 'unread';
        $type = $request->query->get('type');
        $level = $request->query->get('level');
        $environmentId = $this->resolveEnvironmentId($request);

        $result = $this->service->list($user, $environmentId, $page, $limit, $onlyUnread, $type, $level, $orderBy);

        return $this->json([
            'limit' => $limit,
            'currentPage' => $page,
            'hasNext' => $result['hasNext'],
            'hasPrevious' => $page > 0,
            'unreadCount' => $this->service->unreadCount($user, $environmentId),
            'results' => $result['items'],
        ]);
    }

    #[Route('/unread-count', name: 'dashboard_notifications_unread_count', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Contador de notificaciones no leídas', tag: 'Notifications')]
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $this->getAuthUser();
        if ($user === null) {
            return $this->unauthorized();
        }

        return $this->json(['unreadCount' => $this->service->unreadCount($user, $this->resolveEnvironmentId($request))]);
    }

    #[Route('/{id}/read', name: 'dashboard_notifications_read', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Marcar una notificación como leída', tag: 'Notifications')]
    public function markRead(int $id): JsonResponse
    {
        $user = $this->getAuthUser();
        if ($user === null) {
            return $this->unauthorized();
        }

        try {
            return $this->json($this->service->markRead($user, $id));
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }
    }

    #[Route('/{id}/unread', name: 'dashboard_notifications_unread', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Marcar una notificación como no leída', tag: 'Notifications')]
    public function markUnread(int $id): JsonResponse
    {
        $user = $this->getAuthUser();
        if ($user === null) {
            return $this->unauthorized();
        }

        try {
            return $this->json($this->service->markUnread($user, $id));
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }
    }

    #[Route('/read-all', name: 'dashboard_notifications_mark_all', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Marcar todas las notificaciones como leídas', tag: 'Notifications')]
    public function markAllRead(Request $request): JsonResponse
    {
        $user = $this->getAuthUser();
        if ($user === null) {
            return $this->unauthorized();
        }

        return $this->json($this->service->markAllRead($user, $this->resolveEnvironmentId($request)));
    }

    #[Route('/{id}', name: 'dashboard_notifications_dismiss', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Descartar una notificación', tag: 'Notifications')]
    public function dismiss(int $id): JsonResponse
    {
        $user = $this->getAuthUser();
        if ($user === null) {
            return $this->unauthorized();
        }

        try {
            return $this->json($this->service->dismiss($user, $id));
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }
    }

    #[Route('/stream-ticket', name: 'dashboard_notifications_stream_ticket', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Emitir un ticket de un solo uso para abrir el stream SSE', tag: 'Notifications', responseDto: NotificationStreamTicketOutDto::class, responseStatusCode: 201)]
    public function streamTicket(Request $request): JsonResponse
    {
        $user = $this->getAuthUser();
        if ($user === null) {
            return $this->unauthorized();
        }

        $ticket = $this->service->issueStreamTicket($user, $this->resolveEnvironmentId($request));

        return $this->json($ticket, Response::HTTP_CREATED);
    }

    /**
     * Ruta PUBLIC_ACCESS (config/packages/security.yaml): EventSource no
     * puede enviar el header Authorization, así que este endpoint se
     * autentica con un ticket de un solo uso, no con el Bearer del resto del
     * dashboard. Al no procesar Authorization no se restaura sesión desde la
     * cookie (DashboardAccessToken::supports() es false) — no hay lock de
     * sesión PHP de por medio. El session_write_close() es cinturón y
     * tirantes por si el firewall llegara a abrir una de todos modos.
     */
    #[Route('/stream', name: 'dashboard_notifications_stream', methods: ['GET'])]
    public function streamEvents(Request $request): Response
    {
        if (session_status() === \PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $rawTicket = (string) $request->query->get('ticket', '');
        if ($rawTicket === '') {
            return $this->json(['error' => ['message' => 'Missing stream ticket']], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $ticket = $this->service->consumeStreamTicket($rawTicket);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        if (!$this->service->hasCapacityForNewStream()) {
            $response = $this->json(['error' => ['message' => 'Too many concurrent streams, retry later']], Response::HTTP_SERVICE_UNAVAILABLE);
            $response->headers->set('Retry-After', '60');

            return $response;
        }

        $user = $ticket->getUser();
        if ($user === null) {
            return $this->json(['error' => ['message' => 'Invalid stream ticket']], Response::HTTP_UNAUTHORIZED);
        }

        $userId = $user->getId();
        $clientId = $user->getCompany()?->getId();
        $reachableRoles = $this->service->resolveReachableRoles($user);
        $isAdmin = \in_array('ROLE_ADMIN', $reachableRoles, true);
        $environmentId = $ticket->getEnvironment()?->getId();

        $lastEventId = (int) ($request->headers->get('Last-Event-ID') ?? $request->query->get('lastId', 0));
        $streamTtl = $this->service->getStreamTtl();
        $streamInterval = max(1, $this->streamInterval);
        $service = $this->service;
        $em = $this->em;

        $response = new StreamedResponse(function () use (
            $service,
            $em,
            $userId,
            $clientId,
            $reachableRoles,
            $environmentId,
            $isAdmin,
            $lastEventId,
            $streamTtl,
            $streamInterval,
        ) {
            set_time_limit(0);

            $lastId = $lastEventId;
            $lastUnread = -1;
            $streamStart = new \DateTimeImmutable();
            $deadline = time() + $streamTtl;

            while (time() < $deadline && !connection_aborted()) {
                // Limpia la identity map para forzar lectura fresca de BD, igual
                // que DashboardCommunicationsDispatchController::pendingStream().
                // Por eso todo lo que sigue trabaja con IDs primitivos, nunca
                // con la entidad User capturada antes del bucle.
                $em->clear();

                $result = $service->pollForRaw($userId, $clientId, $reachableRoles, $environmentId, $lastId);
                $lastId = $result['lastId'];

                foreach ($result['items'] as $item) {
                    echo 'id: ' . $item['id'] . "\n";
                    echo "event: notification\n";
                    echo 'data: ' . json_encode($item) . "\n\n";
                }

                if ($result['unreadCount'] !== $lastUnread) {
                    $lastUnread = $result['unreadCount'];
                    echo "event: unread\n";
                    echo 'data: ' . json_encode(['unreadCount' => $result['unreadCount']]) . "\n\n";
                }

                if ($isAdmin) {
                    $live = $service->countRecentRecharges($environmentId, $streamStart);
                    echo "event: live\n";
                    echo 'data: ' . json_encode(['recharges' => $live]) . "\n\n";
                }

                echo ": ping\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();

                sleep($streamInterval);
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

    #[Route('', name: 'dashboard_notifications_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    #[DashboardEndpoint(summary: 'Difundir una notificación manual a un usuario, rol o cliente', tag: 'Notifications', requestDto: CreateNotificationDto::class, responseDto: NotificationOutDto::class, responseStatusCode: 201)]
    public function create(CreateNotificationDto $dto): JsonResponse
    {
        $actor = $this->getAuthUser();
        if ($actor === null) {
            return $this->unauthorized();
        }

        try {
            $notification = $this->service->createManual($dto, $actor);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json($this->service->serialize($notification, false), Response::HTTP_CREATED);
    }

    private function getAuthUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function unauthorized(): JsonResponse
    {
        return $this->json(['error' => ['message' => 'Unauthorized']], Response::HTTP_UNAUTHORIZED);
    }

    private function resolveEnvironmentId(Request $request): ?int
    {
        if ($request->headers->has('X-Environment-Id')) {
            return (int) $request->headers->get('X-Environment-Id');
        }

        return null;
    }
}
