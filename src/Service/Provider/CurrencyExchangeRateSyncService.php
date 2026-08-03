<?php

namespace App\Service\Provider;

use App\Entity\CurrencyExchangeRate;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Puebla el histórico de currency_exchange_rate desde Frankfurter. Pensado
 * para correr programado (cron externo → app:provider:sync-exchange-rates),
 * NUNCA en el momento de una importación de catálogo — ver
 * CurrencyConversionService, que solo lee lo que este servicio ya guardó.
 *
 * Una sola llamada por corrida (GET /latest?from=EUR, sin `to`) trae la
 * tasa de EUR contra TODAS las monedas soportadas a la vez — evita pedir un
 * par por cada combinación (producto, cliente) que pudiera necesitarse.
 * CurrencyConversionService deriva cualquier par cruzado (p.ej. USD→GBP)
 * combinando dos tasas base-EUR ya guardadas.
 *
 * A propósito deja propagar cualquier fallo (Frankfurter inalcanzable,
 * respuesta inesperada): esto corre desde un comando de consola programado,
 * no en el camino caliente de una petición — debe fallar ruidosamente para
 * que la alerta de cron lo note, no tragarse el error en silencio.
 */
class CurrencyExchangeRateSyncService
{
    public const BASE_CURRENCY = 'EUR';

    private const FRANKFURTER_URL = 'https://api.frankfurter.dev/v1/latest';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $em,
        #[Autowire('@monolog.logger.provider')]
        private readonly LoggerInterface $providerLogger,
    ) {
    }

    public function sync(): ExchangeRateSyncResult
    {
        $response = $this->httpClient->request('GET', self::FRANKFURTER_URL, [
            'query' => ['from' => self::BASE_CURRENCY],
        ]);
        $body = json_decode($response->getContent(), true);

        $rateDateRaw = $body['date'] ?? null;
        $rates = $body['rates'] ?? null;

        if (!is_string($rateDateRaw) || !is_array($rates)) {
            throw new \RuntimeException('Respuesta inesperada de Frankfurter: faltan "date" o "rates".');
        }

        $rateDate = new \DateTimeImmutable($rateDateRaw);
        $repo = $this->em->getRepository(CurrencyExchangeRate::class);
        $fetchedAt = new \DateTimeImmutable('now');
        $created = 0;

        foreach ($rates as $currency => $rate) {
            $existing = $repo->findOneBy([
                'baseCurrency' => self::BASE_CURRENCY,
                'targetCurrency' => $currency,
                'rateDate' => $rateDate,
            ]);
            if ($existing !== null) {
                continue;
            }

            $entity = new CurrencyExchangeRate();
            $entity->setBaseCurrency(self::BASE_CURRENCY);
            $entity->setTargetCurrency((string) $currency);
            $entity->setRate((float) $rate);
            $entity->setRateDate($rateDate);
            $entity->setFetchedAt($fetchedAt);
            $this->em->persist($entity);
            ++$created;
        }

        if ($created > 0) {
            $this->em->flush();
        }

        $this->providerLogger->info('Tasas de cambio sincronizadas desde Frankfurter.', [
            'rateDate' => $rateDate->format('Y-m-d'),
            'created' => $created,
            'totalCurrencies' => count($rates),
        ]);

        return new ExchangeRateSyncResult($created, $rateDate->format('Y-m-d'), self::BASE_CURRENCY);
    }
}
