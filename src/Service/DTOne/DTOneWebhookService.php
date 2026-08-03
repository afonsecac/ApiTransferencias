<?php

namespace App\Service\DTOne;

use App\DTO\DTOneWebhookPayloadDto;
use App\Message\CheckSaleMessage;
use App\Repository\CommunicationSaleInfoRepository;
use App\Repository\SysConfigRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Callback de DTOne (https://developers.dtone.com): sin firma, sin
 * autenticación, entrega única sin reintentos, timeout 5s en su lado. Por
 * eso este servicio NUNCA usa el body como fuente de verdad — solo extrae
 * `external_id` para localizar la venta y dispara un CheckSaleMessage
 * inmediato (sin delay: el callback ES el acelerador de latencia) que
 * consulta el estado real vía la API autenticada de DTOne. El polling
 * periódico ya existente sigue siendo la red de seguridad si el callback
 * nunca llega.
 */
class DTOneWebhookService
{
    private const CALLBACK_TOKEN_KEY = 'provider.dtone.callback_token';
    private const DEDUP_TTL_SECONDS = 300;

    public function __construct(
        private readonly SysConfigRepository $sysConfigRepo,
        private readonly CommunicationSaleInfoRepository $saleInfoRepo,
        private readonly MessageBusInterface $messageBus,
        #[Autowire(service: 'cache.app')]
        private readonly CacheInterface $cache,
        #[Autowire('@monolog.logger.dtone')]
        private readonly LoggerInterface $dtoneLogger,
    ) {
    }

    /**
     * Comparación en tiempo constante contra el token configurado. Si no hay
     * token configurado, el webhook queda deshabilitado por diseño (nunca
     * acepta nada) en vez de aceptar cualquier valor.
     */
    public function isValidToken(string $token): bool
    {
        $configured = $this->sysConfigRepo->findCachedValue(self::CALLBACK_TOKEN_KEY, mustBeActive: true);

        return $configured !== null && $configured !== '' && hash_equals($configured, $token);
    }

    public function handle(DTOneWebhookPayloadDto $payload): void
    {
        $externalId = $payload->getExternalId();
        if ($externalId === null || $externalId === '') {
            $this->dtoneLogger->warning('DTOne webhook: payload sin external_id, se ignora.');

            return;
        }

        if ($this->isDuplicateDelivery($payload->getEventId())) {
            $this->dtoneLogger->info('DTOne webhook: entrega duplicada ignorada.', ['eventId' => $payload->getEventId()]);

            return;
        }

        $sale = $this->saleInfoRepo->findOneByTransactionId($externalId);
        if ($sale === null) {
            $this->dtoneLogger->warning('DTOne webhook: no se encontró venta para external_id.', ['externalId' => $externalId]);

            return;
        }

        $this->dtoneLogger->info('DTOne webhook: disparando re-check de estado.', [
            'externalId' => $externalId,
            'saleId' => $sale->getId(),
        ]);

        $this->messageBus->dispatch(new CheckSaleMessage($sale->getId()));
    }

    private function isDuplicateDelivery(?string $eventId): bool
    {
        if ($eventId === null || $eventId === '') {
            // Sin id de evento no se puede deduplicar; se procesa igualmente
            // (CheckSaleMessageHandler es seguro ante llamadas repetidas).
            return false;
        }

        $key = 'dtone_webhook_seen_' . md5($eventId);
        $seenBefore = true;

        $this->cache->get($key, function (ItemInterface $item) use (&$seenBefore) {
            $item->expiresAfter(self::DEDUP_TTL_SECONDS);
            $seenBefore = false;

            return true;
        });

        return $seenBefore;
    }
}
