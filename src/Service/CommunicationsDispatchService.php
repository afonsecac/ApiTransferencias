<?php

namespace App\Service;

use App\Entity\CommunicationSaleInfo;
use App\Entity\CommunicationSalePackage;
use App\Entity\CommunicationSaleRecharge;
use App\Entity\SysConfig;
use App\Enums\CommunicationProviderEnum;
use App\Exception\MyCurrentException;
use App\Message\DispatchPendingEmailMessage;
use App\Message\SalePackageMessage;
use App\Message\SaleRechargeMessage;
use App\Repository\CommunicationSaleInfoRepository;
use App\Repository\SysConfigRepository;
use App\Service\Provider\ProviderAvailabilityService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

class CommunicationsDispatchService
{
    public const CONFIG_KEY = 'communications.dispatch.enabled';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SysConfigRepository $sysConfigRepo,
        private readonly MessageBusInterface $messageBus,
        private readonly ProviderAvailabilityService $availabilityService,
        #[Autowire('@monolog.logger.provider')] private readonly LoggerInterface $logger,
    ) {}

    public function invalidateCache(): void
    {
        $this->sysConfigRepo->invalidateCache();
    }

    public function isEnabled(): bool
    {
        return $this->sysConfigRepo->findCachedValue(self::CONFIG_KEY) !== '0';
    }

    public function setEnabled(bool $enabled): void
    {
        $config = $this->sysConfigRepo->findOneBy(['propertyName' => self::CONFIG_KEY]);
        if ($config === null) {
            $config = (new SysConfig())->setPropertyName(self::CONFIG_KEY);
            $this->em->persist($config);
        }
        $config->setPropertyValue($enabled ? '1' : '0');
        $this->em->flush();
        $this->sysConfigRepo->invalidateCache();
    }

    public function countPendingUndispatched(): int
    {
        /** @var CommunicationSaleInfoRepository $repo */
        $repo = $this->em->getRepository(CommunicationSaleInfo::class);
        return $repo->countPendingUndispatched();
    }

    /**
     * Encola en RabbitMQ todas las ventas que quedaron sin despachar y envía un email de resumen.
     * Salta (no encola) las ventas cuyo proveedor no esté despachable ahora mismo
     * (App\Service\Provider\ProviderAvailabilityService::canDispatchTo()) — global
     * apagado, proveedor mal configurado/inactivo, o ping en rojo — y las reporta
     * en `skipped`, agrupadas por "PROVIDER|ENVIRONMENT_TYPE".
     * Lanza MyCurrentException si el dispatch está deshabilitado.
     *
     * @return array{recharges: int, packages: int, total: int, skipped: array<string, int>}
     * @throws MyCurrentException
     */
    public function dispatchPending(?string $triggeredBy = null): array
    {
        if (!$this->isEnabled()) {
            throw new MyCurrentException(
                'DISPATCH_DISABLED',
                'El dispatch de comunicaciones está deshabilitado. Habilítalo antes de lanzar los mensajes pendientes.',
                Response::HTTP_CONFLICT
            );
        }

        /** @var CommunicationSaleInfoRepository $repo */
        $repo = $this->em->getRepository(CommunicationSaleInfo::class);
        $sales = $repo->findPendingUndispatched();

        $recharges      = 0;
        $packages       = 0;
        $transactionIds = [];
        $skipped        = [];

        foreach ($sales as $sale) {
            $provider        = $sale->getProvider() ?? CommunicationProviderEnum::ETECSA->value;
            $environmentType = $sale->getTenant()?->getEnvironment()?->getType();

            if (!$this->availabilityService->canDispatchTo($provider, $environmentType)) {
                $key = $provider . '|' . ($environmentType ?? 'PROD');
                $skipped[$key] = ($skipped[$key] ?? 0) + 1;
                continue;
            }

            if ($sale instanceof CommunicationSaleRecharge) {
                $this->messageBus->dispatch(new SaleRechargeMessage($sale->getId()));
                $recharges++;
            } elseif ($sale instanceof CommunicationSalePackage) {
                $this->messageBus->dispatch(new SalePackageMessage($sale->getId()));
                $packages++;
            }
            if ($sale->getTransactionId() !== null) {
                $transactionIds[] = $sale->getTransactionId();
            }
        }

        $total = $recharges + $packages;

        if ($total > 0) {
            $this->messageBus->dispatch(new DispatchPendingEmailMessage(
                recharges:      $recharges,
                packages:       $packages,
                total:          $total,
                dispatchedAt:   (new \DateTimeImmutable('now'))->format(\DateTimeInterface::ATOM),
                triggeredBy:    $triggeredBy,
                transactionIds: $transactionIds,
            ));
        }

        return ['recharges' => $recharges, 'packages' => $packages, 'total' => $total, 'skipped' => $skipped];
    }

    /**
     * Reencola las ventas PENDING sin despachar de un proveedor/entorno
     * concreto. Lo llama App\Schedule\Task\PingProvidersTask (y el ping
     * forzado del dashboard) justo después de un ping que recupera un
     * proveedor — nunca esta clase por sí sola, para no formar un ciclo de
     * dependencia con ProviderAvailabilityService.
     */
    public function redispatchPendingFor(string $provider, ?string $environmentType): int
    {
        /** @var CommunicationSaleInfoRepository $repo */
        $repo = $this->em->getRepository(CommunicationSaleInfo::class);
        $sales = $repo->findPendingUndispatchedByProvider($provider, $environmentType);

        $count = 0;
        foreach ($sales as $sale) {
            if ($sale instanceof CommunicationSaleRecharge) {
                $this->messageBus->dispatch(new SaleRechargeMessage($sale->getId()));
                $count++;
            } elseif ($sale instanceof CommunicationSalePackage) {
                $this->messageBus->dispatch(new SalePackageMessage($sale->getId()));
                $count++;
            }
        }

        if ($count > 0) {
            $this->logger->info('Reencoladas ventas pendientes tras recuperación de proveedor', [
                'provider' => $provider,
                'environmentType' => $environmentType,
                'count' => $count,
            ]);
        }

        return $count;
    }
}
