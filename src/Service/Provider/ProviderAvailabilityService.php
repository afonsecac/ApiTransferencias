<?php

namespace App\Service\Provider;

use App\DTO\NotificationDraft;
use App\Entity\ProviderAvailability;
use App\Entity\User;
use App\Enums\CommunicationProviderEnum;
use App\Enums\NotificationAudienceEnum;
use App\Enums\NotificationLevelEnum;
use App\Enums\NotificationTypeEnum;
use App\Enums\ProviderActionTypeEnum;
use App\Exception\MyCurrentException;
use App\Provider\Contract\ProviderPingResult;
use App\Provider\ProviderCredentialsResolver;
use App\Provider\ProviderRegistry;
use App\Repository\ProviderAvailabilityRepository;
use App\Repository\SysConfigRepository;
use App\Service\CommunicationsDispatchService;
use App\Service\NotificationCenterService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;

/**
 * Combina tres condiciones en AND para decidir si se puede despachar hacia
 * un proveedor/entorno — ver el modelo completo en el plan de
 * implementación (ping periódico de proveedores):
 *
 *   despachable = dispatchGloballyEnabled() && credentialsResolver->isEnabled() && auto_enabled
 *
 * Invariante de escritura: esta clase solo escribe auto_enabled (vía
 * recordPing(), llamado por el ping). El interruptor MANUAL en sí sigue
 * viviendo en sys_config (provider.{code}.{type}.active) y solo lo escribe
 * setManual(), vía ProviderCredentialsAdminService — nunca al revés. Así el
 * ping nunca invalida el tag de caché `sys_config` (que purgaría TODA la
 * config cacheada de la app cada 15 min).
 *
 * No depende de CommunicationsDispatchService (solo referencia su constante
 * CONFIG_KEY) a propósito: CommunicationsDispatchService::dispatchPending()
 * necesita canDispatchTo(), así que una dependencia en el otro sentido
 * formaría un ciclo. El reencolado de pendientes al recuperarse un proveedor
 * lo dispara el llamador de recordPing() (ver App\Schedule\Task\PingProvidersTask)
 * cuando esta devuelve true, no esta clase.
 */
class ProviderAvailabilityService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProviderAvailabilityRepository $repository,
        private readonly ProviderCredentialsResolver $credentialsResolver,
        private readonly ProviderCredentialsAdminService $credentialsAdminService,
        private readonly ProviderRegistry $registry,
        private readonly SysConfigRepository $sysConfigRepo,
        private readonly NotificationCenterService $notificationCenter,
        private readonly Security $security,
        #[Autowire('@monolog.logger.provider')] private readonly LoggerInterface $logger,
    ) {
    }

    public function canDispatchTo(?string $providerRaw, ?string $environmentType): bool
    {
        $provider = CommunicationProviderEnum::tryFrom($providerRaw ?? '') ?? CommunicationProviderEnum::ETECSA;
        $environmentType = $environmentType ?? 'PROD';

        if (!$this->dispatchGloballyEnabled()) {
            return false;
        }

        // isEnabled() = isFullyConfigured() && isActive(): cubre a la vez el
        // requisito de "no despachar si no está bien configurado" y el
        // interruptor MANUAL.
        if (!$this->credentialsResolver->isEnabled($provider, $environmentType)) {
            return false;
        }

        $row = $this->repository->findOneByProviderAndType($provider->value, $environmentType);

        return $row === null || $row->isAutoEnabled();
    }

    /**
     * Registra el resultado de un ping y aplica la transición AUTO
     * correspondiente. Devuelve true si, con este ping, el proveedor pasó a
     * ser despachable de verdad (recuperación real: también manual y global
     * están en verde) — el llamador debe entonces reencolar sus pendientes
     * (CommunicationsDispatchService::redispatchPendingFor()).
     */
    public function recordPing(CommunicationProviderEnum $provider, string $environmentType, ProviderPingResult $result): bool
    {
        $row = $this->repository->findOneByProviderAndType($provider->value, $environmentType);
        if ($row === null) {
            $row = (new ProviderAvailability())
                ->setProvider($provider->value)
                ->setEnvironmentType($environmentType);
            $this->em->persist($row);
        }

        $row->setLastPingAt($result->checkedAt)
            ->setLastPingSuccess($result->inconclusive ? null : $result->available)
            ->setLastPingLatencyMs($result->latencyMs)
            ->setLastPingError($result->error)
            ->setLastPingDetails($result->details === [] ? null : $result->details)
            ->touch();

        if ($result->inconclusive) {
            // Nuestra credencial/config está mal, no el proveedor: no se
            // toca auto_enabled — apagarlo por esto sería un falso positivo.
            $this->logger->error('Ping de proveedor inconcluso', [
                'provider' => $provider->value,
                'environmentType' => $environmentType,
                'error' => $result->error,
            ]);
            $this->notifyAdmins(
                NotificationTypeEnum::CUSTOM,
                NotificationLevelEnum::WARNING,
                sprintf('Ping de %s (%s) inconcluso — revisar credenciales', $provider->value, $environmentType),
                $result->error,
                $provider,
                $environmentType,
            );

            $this->flushSafely($provider, $environmentType);

            return false;
        }

        $wasAutoEnabled = $row->isAutoEnabled();
        $justRecovered = false;

        if (!$result->available && $wasAutoEnabled) {
            $row->setAutoEnabled(false)
                ->setLastActionType(ProviderActionTypeEnum::AUTO)
                ->setLastActionBy(null)
                ->setLastActionAt($result->checkedAt)
                ->setLastActionReason($result->error);

            $this->logger->error('Proveedor deshabilitado automáticamente: ping fallido', [
                'provider' => $provider->value,
                'environmentType' => $environmentType,
                'error' => $result->error,
            ]);
            $this->notifyAdmins(
                NotificationTypeEnum::PROVIDER_UNAVAILABLE,
                NotificationLevelEnum::CRITICAL,
                sprintf('Proveedor %s (%s) deshabilitado automáticamente', $provider->value, $environmentType),
                $result->error,
                $provider,
                $environmentType,
            );
        } elseif ($result->available && !$wasAutoEnabled) {
            $row->setAutoEnabled(true)
                ->setLastActionType(ProviderActionTypeEnum::AUTO)
                ->setLastActionBy(null)
                ->setLastActionAt($result->checkedAt)
                ->setLastActionReason('Ping recuperado');

            $this->notifyAdmins(
                NotificationTypeEnum::PROVIDER_RECOVERED,
                NotificationLevelEnum::SUCCESS,
                sprintf('Proveedor %s (%s) recuperado', $provider->value, $environmentType),
                null,
                $provider,
                $environmentType,
            );

            // Solo cuenta como recuperación real de despacho si con esto
            // queda despachable (manual y global también en verde) — si un
            // admin lo apagó a mano, AUTO puede volver a true sin que se
            // reencole nada (requisito 4).
            $justRecovered = $this->canDispatchTo($provider->value, $environmentType);
        }

        if (!$this->flushSafely($provider, $environmentType)) {
            return false;
        }

        return $justRecovered;
    }

    /**
     * Interruptor MANUAL, auditado. Un enable resetea AUTO (el siguiente
     * ping lo volverá a apagar si de verdad sigue caído) para que un falso
     * positivo del ping nunca le impida a un admin encender el proveedor —
     * un disable nunca toca AUTO.
     */
    public function setManual(CommunicationProviderEnum $provider, string $environmentType, bool $active, ?string $reason = null): void
    {
        if ($active && !$this->credentialsResolver->isFullyConfigured($provider, $environmentType)) {
            throw new MyCurrentException(
                'PROVIDER_NOT_CONFIGURED',
                'El proveedor no tiene credenciales completas para este entorno; no se puede activar el despacho.',
                Response::HTTP_CONFLICT,
            );
        }

        $this->credentialsAdminService->setActive($provider, $environmentType, $active);

        $row = $this->repository->findOneByProviderAndType($provider->value, $environmentType);
        if ($row === null) {
            $row = (new ProviderAvailability())
                ->setProvider($provider->value)
                ->setEnvironmentType($environmentType);
            $this->em->persist($row);
        }

        $user = $this->security->getUser();
        $row->setLastActionType(ProviderActionTypeEnum::MANUAL)
            ->setLastActionBy($user instanceof User ? $user : null)
            ->setLastActionAt(new \DateTimeImmutable('now'))
            ->setLastActionReason($reason)
            ->touch();

        if ($active) {
            $row->setAutoEnabled(true);
        }

        $this->em->flush();
    }

    /**
     * @return list<array{
     *     provider: string, environmentType: string, configured: bool,
     *     manualActive: bool, autoEnabled: bool, effective: bool,
     *     lastActionType: ?string, lastActionBy: ?string, lastActionAt: ?string, lastActionReason: ?string,
     *     lastPingAt: ?string, lastPingSuccess: ?bool, lastPingLatencyMs: ?int, lastPingError: ?string,
     * }>
     */
    public function statusMatrix(): array
    {
        $matrix = [];
        foreach ($this->registry->registered() as $provider) {
            foreach (['TEST', 'PROD'] as $environmentType) {
                $row = $this->repository->findOneByProviderAndType($provider->value, $environmentType);

                $matrix[] = [
                    'provider' => $provider->value,
                    'environmentType' => $environmentType,
                    'configured' => $this->credentialsResolver->isFullyConfigured($provider, $environmentType),
                    'manualActive' => $this->credentialsResolver->isActive($provider, $environmentType),
                    'autoEnabled' => $row?->isAutoEnabled() ?? true,
                    'effective' => $this->canDispatchTo($provider->value, $environmentType),
                    'lastActionType' => $row?->getLastActionType()?->value,
                    'lastActionBy' => $row?->getLastActionBy()?->getUserIdentifier(),
                    'lastActionAt' => $row?->getLastActionAt()?->format(DATE_ATOM),
                    'lastActionReason' => $row?->getLastActionReason(),
                    'lastPingAt' => $row?->getLastPingAt()?->format(DATE_ATOM),
                    'lastPingSuccess' => $row?->isLastPingSuccess(),
                    'lastPingLatencyMs' => $row?->getLastPingLatencyMs(),
                    'lastPingError' => $row?->getLastPingError(),
                ];
            }
        }

        return $matrix;
    }

    private function dispatchGloballyEnabled(): bool
    {
        return $this->sysConfigRepo->findCachedValue(CommunicationsDispatchService::CONFIG_KEY) !== '0';
    }

    /**
     * Protege el upsert contra la carrera entre el ping periódico y un ping
     * forzado desde el dashboard corriendo casi a la vez para el mismo
     * (provider, environmentType) — el índice único de la tabla la
     * convertiría en una excepción de flush(), no algo que deba tumbar el
     * ciclo de ping completo.
     */
    private function flushSafely(CommunicationProviderEnum $provider, string $environmentType): bool
    {
        try {
            $this->em->flush();

            return true;
        } catch (UniqueConstraintViolationException) {
            $this->em->clear();
            $this->logger->warning('Carrera al persistir ProviderAvailability, se omite este ciclo', [
                'provider' => $provider->value,
                'environmentType' => $environmentType,
            ]);

            return false;
        }
    }

    private function notifyAdmins(
        NotificationTypeEnum $type,
        NotificationLevelEnum $level,
        string $title,
        ?string $body,
        CommunicationProviderEnum $provider,
        string $environmentType,
    ): void {
        $this->notificationCenter->bumpGroup(
            groupKey: sprintf('provider_availability:%s:%s', $provider->value, $environmentType),
            audience: NotificationAudienceEnum::ROLE,
            draft: new NotificationDraft(
                type: $type,
                title: $title,
                level: $level,
                body: $body,
                link: '/providers/availability',
                data: ['provider' => $provider->value, 'environmentType' => $environmentType],
            ),
            targetRole: 'ROLE_ADMIN',
        );
    }
}
