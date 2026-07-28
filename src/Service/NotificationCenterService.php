<?php

namespace App\Service;

use App\DTO\CreateNotificationDto;
use App\DTO\NotificationDraft;
use App\Entity\Client;
use App\Entity\CommunicationSaleRecharge;
use App\Entity\Environment;
use App\Entity\Notification;
use App\Entity\NotificationReceipt;
use App\Entity\NotificationStreamTicket;
use App\Entity\User;
use App\Enums\NotificationAudienceEnum;
use App\Enums\NotificationLevelEnum;
use App\Enums\NotificationTypeEnum;
use App\Exception\MyCurrentException;
use App\Repository\NotificationReceiptRepository;
use App\Repository\NotificationRepository;
use App\Repository\NotificationStreamTicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * Centro de notificaciones in-app. Servicio nuevo con pocas dependencias:
 * inyección directa, sin extender CommonService (CLAUDE.md §5).
 *
 * Toda emisión (notifyUser/notifyRole/notifyClient/notifyGlobal/bumpGroup)
 * atrapa cualquier excepción y solo la registra: una notificación nunca debe
 * hacer fallar el flujo de negocio que la origina.
 */
class NotificationCenterService
{
    private const ORDERABLE_FIELDS = [
        'createdAt' => 'n.createdAt',
        'level' => 'n.level',
        'type' => 'n.type',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NotificationRepository $notifications,
        private readonly NotificationReceiptRepository $receipts,
        private readonly NotificationStreamTicketRepository $streamTickets,
        private readonly RoleHierarchyInterface $roleHierarchy,
        private readonly LoggerInterface $logger,
        private readonly int $streamTicketTtl = 30,
        private readonly int $streamTtl = 270,
        private readonly int $maxStreams = 20,
    ) {
    }

    // -------------------------------------------------------------------
    // Lectura
    // -------------------------------------------------------------------

    /**
     * @return string[]
     */
    public function resolveReachableRoles(User $user): array
    {
        return $this->roleHierarchy->getReachableRoleNames($user->getRoles());
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, hasNext: bool}
     */
    public function list(
        User $user,
        ?int $environmentId,
        int $page,
        int $limit,
        bool $onlyUnread,
        ?string $type,
        ?string $level,
        string $orderBy,
    ): array {
        $roles = $this->resolveReachableRoles($user);
        $clientId = $user->getCompany()?->getId();
        [$field, $direction] = $this->parseOrderBy($orderBy);

        $rows = $this->notifications->findVisiblePage(
            $user->getId(),
            $clientId,
            $roles,
            $environmentId,
            $onlyUnread,
            $type,
            $level,
            $page,
            $limit,
            $field,
            $direction,
        );

        $hasNext = count($rows) > $limit;
        if ($hasNext) {
            array_pop($rows);
        }

        $readMap = $this->buildReadMap($rows, $user->getId());
        $items = array_map(fn (Notification $n) => $this->serialize($n, $readMap[$n->getId()] ?? false), $rows);

        return ['items' => $items, 'hasNext' => $hasNext];
    }

    public function unreadCount(User $user, ?int $environmentId): int
    {
        return $this->notifications->countUnread(
            $user->getId(),
            $user->getCompany()?->getId(),
            $this->resolveReachableRoles($user),
            $environmentId,
        );
    }

    /**
     * Variante del polling que solo trabaja con IDs primitivos y jamás
     * conserva una referencia a la entidad User: el stream SSE llama a esto
     * en un bucle que hace em->clear() en cada vuelta (ver
     * DashboardNotificationsController::stream), y una entidad capturada
     * antes del bucle quedaría desasociada del EntityManager.
     *
     * @param string[] $reachableRoles
     *
     * @return array{items: array<int, array<string, mixed>>, unreadCount: int, lastId: int}
     */
    public function pollForRaw(int $userId, ?int $clientId, array $reachableRoles, ?int $environmentId, int $lastId): array
    {
        $rows = $this->notifications->findVisibleSince($userId, $clientId, $reachableRoles, $environmentId, $lastId);
        $readMap = $this->buildReadMap($rows, $userId);
        $items = array_map(fn (Notification $n) => $this->serialize($n, $readMap[$n->getId()] ?? false), $rows);

        $newLastId = $lastId;
        foreach ($rows as $n) {
            $newLastId = max($newLastId, (int) $n->getId());
        }

        return [
            'items' => $items,
            'unreadCount' => $this->notifications->countUnread($userId, $clientId, $reachableRoles, $environmentId),
            'lastId' => $newLastId,
        ];
    }

    // -------------------------------------------------------------------
    // Escritura del usuario
    // -------------------------------------------------------------------

    /**
     * @return array{id: string, read: bool, unreadCount: int}
     */
    public function markRead(User $user, int $id): array
    {
        $notification = $this->getVisibleOrFail($id, $user);
        $receipt = $this->receipts->findOneByNotificationAndUser($id, $user->getId())
            ?? (new NotificationReceipt())->setNotification($notification)->setUser($user);
        $receipt->setReadAt(new \DateTimeImmutable());
        $this->em->persist($receipt);
        $this->em->flush();

        return ['id' => (string) $id, 'read' => true, 'unreadCount' => $this->unreadCount($user, null)];
    }

    /**
     * @return array{id: string, read: bool, unreadCount: int}
     */
    public function markUnread(User $user, int $id): array
    {
        $this->getVisibleOrFail($id, $user);
        $receipt = $this->receipts->findOneByNotificationAndUser($id, $user->getId());
        if ($receipt !== null) {
            $receipt->setReadAt(null);
            $this->em->flush();
        }

        return ['id' => (string) $id, 'read' => false, 'unreadCount' => $this->unreadCount($user, null)];
    }

    /**
     * @return array{marked: int, unreadCount: int}
     */
    public function markAllRead(User $user, ?int $environmentId): array
    {
        $roles = $this->resolveReachableRoles($user);
        $clientId = $user->getCompany()?->getId();
        $ids = $this->notifications->findVisibleUnreadIds($user->getId(), $clientId, $roles, $environmentId);
        $marked = $this->receipts->markManyRead($ids, $user->getId());

        return [
            'marked' => $marked,
            'unreadCount' => $this->notifications->countUnread($user->getId(), $clientId, $roles, $environmentId),
        ];
    }

    /**
     * @return array{deleted: bool, unreadCount: int}
     */
    public function dismiss(User $user, int $id): array
    {
        $notification = $this->getVisibleOrFail($id, $user);
        $receipt = $this->receipts->findOneByNotificationAndUser($id, $user->getId())
            ?? (new NotificationReceipt())->setNotification($notification)->setUser($user);
        $receipt->setDismissedAt(new \DateTimeImmutable());
        $this->em->persist($receipt);
        $this->em->flush();

        return ['deleted' => true, 'unreadCount' => $this->unreadCount($user, null)];
    }

    private function getVisibleOrFail(int $id, User $user): Notification
    {
        $notification = $this->notifications->findVisibleById(
            $id,
            $user->getId(),
            $user->getCompany()?->getId(),
            $this->resolveReachableRoles($user),
        );
        if ($notification === null) {
            // Mismo código para "no existe" y "existe pero no es de este usuario":
            // no hay que filtrar existencia entre tenants.
            throw new MyCurrentException('NOTIFICATION_NOT_FOUND', 'Notification not found', 404);
        }

        return $notification;
    }

    // -------------------------------------------------------------------
    // Stream (SSE)
    // -------------------------------------------------------------------

    /**
     * @return array{ticket: string, expiresIn: int}
     */
    public function issueStreamTicket(User $user, ?int $environmentId): array
    {
        $raw = bin2hex(random_bytes(32));

        $ticket = new NotificationStreamTicket();
        $ticket->setTokenHash(hash('sha256', $raw))
            ->setUser($user)
            ->setExpiresAt(new \DateTimeImmutable('+' . $this->streamTicketTtl . ' seconds'));

        if ($environmentId !== null) {
            $ticket->setEnvironment($this->em->getReference(Environment::class, $environmentId));
        }

        $this->em->persist($ticket);
        $this->em->flush();

        return ['ticket' => $raw, 'expiresIn' => $this->streamTicketTtl];
    }

    /**
     * Valida, marca como usado y devuelve el ticket. Un ticket inválido,
     * caducado o ya usado es siempre el mismo error genérico: no hay que dar
     * pistas sobre cuál de los tres casos ocurrió.
     */
    public function consumeStreamTicket(string $rawTicket): NotificationStreamTicket
    {
        $ticket = $this->streamTickets->findByTokenHash(hash('sha256', $rawTicket));
        if ($ticket === null || $ticket->isExpired() || $ticket->isUsed()) {
            throw new MyCurrentException('INVALID_STREAM_TICKET', 'Invalid or expired stream ticket', 401);
        }

        $ticket->setUsedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $ticket;
    }

    /**
     * Guardia de concurrencia: por debajo de este límite de streams
     * "probablemente abiertos" (tickets consumidos hace menos de streamTtl
     * segundos), se admite una conexión más.
     */
    public function hasCapacityForNewStream(): bool
    {
        $since = new \DateTimeImmutable('-' . $this->streamTtl . ' seconds');

        return $this->streamTickets->countRecentlyUsed($since) < $this->maxStreams;
    }

    public function getStreamTtl(): int
    {
        return $this->streamTtl;
    }

    /**
     * @return array{notifications: int, tickets: int}
     */
    public function purge(int $retentionDays = 90, bool $dryRun = false): array
    {
        if ($dryRun) {
            return [
                'notifications' => $this->notifications->countExpired($retentionDays),
                'tickets' => $this->streamTickets->countExpired(),
            ];
        }

        return [
            'notifications' => $this->notifications->purgeExpired($retentionDays, 5000),
            'tickets' => $this->streamTickets->purgeExpired(),
        ];
    }

    /**
     * Contador en vivo de recargas entrantes, para el evento "live" del
     * stream. No persiste nada: cero filas nuevas, cero impacto en el badge.
     * Solo tiene sentido para ROLE_ADMIN (lo decide el llamador).
     *
     * @return array{count: int, since: string, last: array<string, mixed>|null}
     */
    public function countRecentRecharges(?int $environmentId, \DateTimeImmutable $since): array
    {
        $qb = $this->em->getRepository(CommunicationSaleRecharge::class)->createQueryBuilder('s')
            ->leftJoin('s.tenant', 'a')
            ->andWhere('s.createdAt > :since')
            ->setParameter('since', $since)
            ->orderBy('s.createdAt', 'DESC');

        if ($environmentId !== null) {
            $qb->andWhere('a.environment = :envId')->setParameter('envId', $environmentId);
        }

        /** @var CommunicationSaleRecharge[] $rows */
        $rows = $qb->getQuery()->getResult();

        if ($rows === []) {
            return ['count' => 0, 'since' => $since->format('c'), 'last' => null];
        }

        $last = $rows[0];

        return [
            'count' => count($rows),
            'since' => $since->format('c'),
            'last' => [
                'saleId' => $last->getId(),
                'amount' => $last->getAmount(),
                'currency' => $last->getCurrency(),
            ],
        ];
    }

    // -------------------------------------------------------------------
    // Difusión manual (ROLE_ADMIN)
    // -------------------------------------------------------------------

    public function createManual(CreateNotificationDto $dto, User $actor): Notification
    {
        $audience = NotificationAudienceEnum::from((string) $dto->getAudience());
        $level = NotificationLevelEnum::from($dto->getLevel() ?? 'INFO');
        $draft = new NotificationDraft(
            type: NotificationTypeEnum::CUSTOM,
            title: (string) $dto->getTitle(),
            level: $level,
            body: $dto->getBody(),
            link: $dto->getLink(),
            data: $dto->getData(),
            environmentId: $dto->getEnvironmentId(),
        );

        $notification = match ($audience) {
            NotificationAudienceEnum::USER => $this->notifyUser($this->resolveUser($dto->getTargetUserId()), $draft),
            NotificationAudienceEnum::ROLE => $this->notifyRole($this->resolveRole($dto->getTargetRole()), $draft),
            NotificationAudienceEnum::CLIENT => $this->notifyClient($this->resolveClient($dto->getClientId()), $draft),
            NotificationAudienceEnum::GLOBAL => $this->notifyGlobal($draft),
        };

        if ($notification === null) {
            // safeEmit solo atrapa fallos inesperados de persistencia; aquí sí
            // queremos que el admin sepa que su difusión no se guardó.
            throw new MyCurrentException('NOTIFICATION_CREATE_FAILED', 'Could not create notification', 500);
        }

        $this->logger->info('Manual notification broadcast', [
            'actor' => $actor->getEmail(),
            'audience' => $audience->value,
            'notificationId' => $notification->getId(),
        ]);

        return $notification;
    }

    private function resolveUser(?int $id): User
    {
        $user = $id !== null ? $this->em->getRepository(User::class)->find($id) : null;
        if ($user === null) {
            throw new MyCurrentException('NOTIFICATION_TARGET_NOT_FOUND', 'Target user not found', 404);
        }

        return $user;
    }

    private function resolveClient(?int $id): Client
    {
        $client = $id !== null ? $this->em->getRepository(Client::class)->find($id) : null;
        if ($client === null) {
            throw new MyCurrentException('NOTIFICATION_TARGET_NOT_FOUND', 'Target client not found', 404);
        }

        return $client;
    }

    private function resolveRole(?string $role): string
    {
        if ($role === null || trim($role) === '') {
            throw new MyCurrentException('NOTIFICATION_TARGET_NOT_FOUND', 'Target role is required', 400);
        }

        return $role;
    }

    // -------------------------------------------------------------------
    // Emisión desde eventos de dominio
    // -------------------------------------------------------------------

    public function notifyUser(User $user, NotificationDraft $draft): ?Notification
    {
        return $this->safeEmit(fn () => $this->persist(NotificationAudienceEnum::USER, $draft, targetUser: $user));
    }

    /**
     * Recibe un iterable sin tipar en el PHPDoc a propósito: los repositorios
     * que resuelven destinatarios (p. ej. getFinanceUsers() en
     * BalanceMessageHandler) devuelven `array` sin generics, y el guard
     * instanceof es la única garantía real de que solo se notifique a User.
     *
     * @param iterable<mixed> $users
     */
    public function notifyUsers(iterable $users, NotificationDraft $draft): void
    {
        foreach ($users as $user) {
            if ($user instanceof User) {
                $this->notifyUser($user, $draft);
            }
        }
    }

    public function notifyRole(string $role, NotificationDraft $draft): ?Notification
    {
        return $this->safeEmit(fn () => $this->persist(NotificationAudienceEnum::ROLE, $draft, targetRole: $role));
    }

    public function notifyClient(Client $client, NotificationDraft $draft): ?Notification
    {
        return $this->safeEmit(fn () => $this->persist(NotificationAudienceEnum::CLIENT, $draft, client: $client));
    }

    public function notifyGlobal(NotificationDraft $draft): ?Notification
    {
        return $this->safeEmit(fn () => $this->persist(NotificationAudienceEnum::GLOBAL, $draft));
    }

    /**
     * Upsert nativo: una sola fila por group_key que se incrementa en lugar
     * de crear una nueva notificación cada vez (dispatch pendiente diario,
     * reintentos de venta agotados). updated_at avanza en cada bump, así que
     * una notificación ya leída vuelve a contar como no leída al recibir un
     * nuevo incremento (ver NotificationRepository::applyUnreadPredicate).
     */
    public function bumpGroup(
        string $groupKey,
        NotificationAudienceEnum $audience,
        NotificationDraft $draft,
        ?User $targetUser = null,
        ?string $targetRole = null,
        ?Client $client = null,
    ): void {
        $this->safeEmit(function () use ($groupKey, $audience, $draft, $targetUser, $targetRole, $client) {
            $now = new \DateTimeImmutable();
            $this->em->getConnection()->executeStatement(
                <<<'SQL'
                    INSERT INTO notification (
                        type, level, audience, target_user_id, target_role, client_id, environment_id,
                        title, body, link, data, group_key, group_count, created_at, updated_at, expires_at
                    )
                    VALUES (:type, :level, :audience, :targetUserId, :targetRole, :clientId, :environmentId,
                            :title, :body, :link, :data, :groupKey, 1, :now, :now, :expiresAt)
                    ON CONFLICT (group_key) WHERE group_key IS NOT NULL
                    DO UPDATE SET
                        group_count = notification.group_count + 1,
                        updated_at = EXCLUDED.updated_at,
                        title = EXCLUDED.title,
                        body = EXCLUDED.body,
                        data = EXCLUDED.data
                    SQL,
                [
                    'type' => $draft->type->value,
                    'level' => $draft->level->value,
                    'audience' => $audience->value,
                    'targetUserId' => $targetUser?->getId(),
                    'targetRole' => $targetRole,
                    'clientId' => $client?->getId(),
                    'environmentId' => $draft->environmentId,
                    'title' => $draft->title,
                    'body' => $draft->body,
                    'link' => $draft->link,
                    'data' => $draft->data !== null ? json_encode($draft->data) : null,
                    'groupKey' => $groupKey,
                    'now' => $now->format('Y-m-d H:i:s'),
                    'expiresAt' => $draft->expiresAt?->format('Y-m-d H:i:s'),
                ],
            );

            return null;
        });
    }

    private function persist(
        NotificationAudienceEnum $audience,
        NotificationDraft $draft,
        ?User $targetUser = null,
        ?string $targetRole = null,
        ?Client $client = null,
    ): Notification {
        $notification = new Notification();
        $notification->setType($draft->type)
            ->setLevel($draft->level)
            ->setAudience($audience)
            ->setTargetUser($targetUser)
            ->setTargetRole($targetRole)
            ->setClient($client)
            ->setTitle($draft->title)
            ->setBody($draft->body)
            ->setLink($draft->link)
            ->setData($draft->data)
            ->setExpiresAt($draft->expiresAt);

        if ($draft->environmentId !== null) {
            $notification->setEnvironment($this->em->getReference(Environment::class, $draft->environmentId));
        }

        $this->em->persist($notification);
        $this->em->flush();

        return $notification;
    }

    /**
     * @template T
     *
     * @param callable(): T $emit
     *
     * @return T|null
     */
    private function safeEmit(callable $emit)
    {
        try {
            return $emit();
        } catch (\Throwable $e) {
            $this->logger->error('Failed to emit in-app notification', ['exception' => $e]);

            return null;
        }
    }

    // -------------------------------------------------------------------
    // Utilidades
    // -------------------------------------------------------------------

    /**
     * @param Notification[] $notifications
     *
     * @return array<int, bool>
     */
    private function buildReadMap(array $notifications, int $userId): array
    {
        if ($notifications === []) {
            return [];
        }

        $ids = array_map(fn (Notification $n) => $n->getId(), $notifications);
        $receipts = $this->receipts->findByNotificationsAndUser($ids, $userId);

        $map = [];
        foreach ($receipts as $receipt) {
            $notification = $receipt->getNotification();
            if ($notification === null) {
                continue;
            }
            $isRead = $receipt->getReadAt() !== null && $receipt->getReadAt() >= $notification->getUpdatedAt();
            $map[$notification->getId()] = $isRead;
        }

        return $map;
    }

    private function parseOrderBy(string $orderBy): array
    {
        $parts = explode(' ', trim($orderBy));
        $field = self::ORDERABLE_FIELDS[$parts[0]] ?? self::ORDERABLE_FIELDS['createdAt'];
        $direction = strtoupper($parts[1] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        return [$field, $direction];
    }

    public function serialize(Notification $n, bool $read): array
    {
        return [
            'id' => (string) $n->getId(),
            'type' => $n->getType()?->value,
            'level' => $n->getLevel()->value,
            'title' => $n->getTitle(),
            'body' => $n->getBody(),
            'link' => $n->getLink(),
            'useRouter' => true,
            'data' => $n->getData(),
            'audience' => $n->getAudience()?->value,
            'groupCount' => $n->getGroupCount(),
            'environmentId' => $n->getEnvironment()?->getId(),
            'read' => $read,
            'createdAt' => $n->getCreatedAt()?->format('c'),
            'expiresAt' => $n->getExpiresAt()?->format('c'),
        ];
    }
}
