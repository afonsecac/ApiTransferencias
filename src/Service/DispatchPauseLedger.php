<?php

namespace App\Service;

use App\Entity\SysConfig;
use App\Repository\SysConfigRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Registro de los periodos en que la plataforma tuvo la entrega
 * deshabilitada (communications.dispatch.enabled = '0'). El tope de espera
 * de una venta aceptada sin proveedor no corre durante esos periodos — una
 * pausa decidida por la plataforma no debe hacer caducar ventas.
 *
 * El switch se puede cambiar desde varios sitios (dashboard, sys-config
 * genérico), así que no se engancha en ninguno: cada barrido de
 * CommunicationSaleService::dispatchAwaitingProviderSales() lo observa y
 * abre/cierra el periodo (resolución: la del cron, 5 min). Se guarda como
 * JSON en sys_config, sin esquema nuevo.
 */
class DispatchPauseLedger
{
    public const PAUSES_KEY = 'communications.dispatch.pauses';
    private const RETENTION = '-30 days';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SysConfigRepository $sysConfigRepo,
    ) {
    }

    /**
     * Abre un periodo si la entrega está deshabilitada y no hay uno abierto;
     * lo cierra si está habilitada y había uno abierto.
     */
    public function observe(\DateTimeImmutable $now): void
    {
        $enabled = $this->sysConfigRepo->findCachedValue(CommunicationsDispatchService::CONFIG_KEY) !== '0';
        $pauses = $this->load();

        $openIndex = null;
        foreach ($pauses as $i => $pause) {
            if ($pause['to'] === null) {
                $openIndex = $i;
            }
        }

        if (!$enabled && $openIndex === null) {
            $pauses[] = ['from' => $now->format(\DateTimeInterface::ATOM), 'to' => null];
        } elseif ($enabled && $openIndex !== null) {
            $pauses[$openIndex]['to'] = $now->format(\DateTimeInterface::ATOM);
        } else {
            return;
        }

        $this->save($pauses, $now);
    }

    /**
     * Segundos de pausa que caen dentro de [$from, $to]; un periodo todavía
     * abierto cuenta hasta $to.
     */
    public function pausedSeconds(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        $total = 0;
        foreach ($this->load() as $pause) {
            $start = new \DateTimeImmutable($pause['from']);
            $end = $pause['to'] !== null ? new \DateTimeImmutable($pause['to']) : $to;

            $overlapStart = max($start->getTimestamp(), $from->getTimestamp());
            $overlapEnd = min($end->getTimestamp(), $to->getTimestamp());
            if ($overlapEnd > $overlapStart) {
                $total += $overlapEnd - $overlapStart;
            }
        }

        return $total;
    }

    /**
     * @return list<array{from: string, to: ?string}>
     */
    private function load(): array
    {
        $config = $this->em->getRepository(SysConfig::class)->findOneBy(['propertyName' => self::PAUSES_KEY]);
        $decoded = json_decode($config?->getPropertyValue() ?? '[]', true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    /**
     * @param list<array{from: string, to: ?string}> $pauses
     */
    private function save(array $pauses, \DateTimeImmutable $now): void
    {
        $limit = $now->modify(self::RETENTION)->getTimestamp();
        $pauses = array_values(array_filter(
            $pauses,
            static fn (array $pause) => $pause['to'] === null || (new \DateTimeImmutable($pause['to']))->getTimestamp() >= $limit,
        ));

        $repo = $this->em->getRepository(SysConfig::class);
        $config = $repo->findOneBy(['propertyName' => self::PAUSES_KEY]);
        if ($config === null) {
            $config = (new SysConfig())->setPropertyName(self::PAUSES_KEY)->setIsActive(true);
            $this->em->persist($config);
        }
        $config->setPropertyValue(json_encode($pauses, JSON_THROW_ON_ERROR));
        $this->em->flush();
        $this->sysConfigRepo->invalidateCache();
    }
}
