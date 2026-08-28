<?php

namespace App\Service\Provider;

use App\Entity\CommunicationSaleInfo;
use App\Entity\CommunicationSaleRecharge;
use App\Enums\CommunicationProviderEnum;
use App\Enums\CommunicationStateEnum;
use App\Enums\ProviderOutcomeEnum;
use App\Provider\ProviderDispatchResolver;
use App\Provider\TransactionStatus;
use App\Repository\SysConfigRepository;
use App\Service\HistoricalSaleService;

/**
 * Único salto principal → secundario tras un RECHAZO DETERMINISTA del
 * proveedor — nunca tras un timeout/error de transporte, que los
 * adaptadores ya absorben como ProviderOutcomeEnum::UNKNOWN antes de llegar
 * a los call sites de este servicio (ver CommunicationSaleService, catch
 * genérico tras dispatch: "Jamás debe reintentarse el ENVÍO mismo tras un
 * UNKNOWN: eso podría cobrar dos veces la misma recarga"). REJECTED sí es
 * seguro: el proveedor confirmó que NO procesó la operación.
 *
 * Reutiliza ProviderDispatchResolver::selectExcluding() — la misma lista de
 * candidatos (proveedor + fallbackProvider de cada fila de
 * ClientProviderRouting aplicable) que ya usó la selección inicial, solo
 * que saltando el proveedor que acaba de rechazar.
 *
 * Sin extender CommonService (servicio nuevo con pocas dependencias, ver
 * CLAUDE.md).
 */
class SaleProviderFailoverService
{
    public const FAILOVER_ENABLED_KEY = 'communications.provider.failover.enabled';

    public function __construct(
        private readonly ProviderDispatchResolver $dispatchResolver,
        private readonly SysConfigRepository $sysConfigRepo,
        private readonly HistoricalSaleService $historicalSaleService,
    ) {
    }

    /**
     * Muta $sale (provider/dispatchProduct/dispatchExternalRef/stateProcess/
     * transactionStatus) y registra un CommunicationSaleHistory si encuentra
     * un candidato — NO hace flush ni reencola el mensaje de despacho, eso
     * es responsabilidad de quien llama (ya está en medio de su propio
     * flujo de persistencia). Devuelve false sin mutar nada si el failover
     * no aplica.
     */
    public function promoteToFallback(CommunicationSaleInfo $sale, string $reason): bool
    {
        if ($this->sysConfigRepo->findCachedValue(self::FAILOVER_ENABLED_KEY) === '0') {
            return false;
        }

        $currentStatus = $sale->getTransactionStatus();
        // Un único salto por venta: si ya hay un failover registrado, no se
        // intenta un segundo (evita ping-pong entre dos proveedores que
        // rechazan por turnos). El marcador sobrevive a los sucesivos
        // fromDispatch()/fromStatus() vía TransactionStatus::carryRetryBlock().
        if (TransactionStatus::failoverFromOf($currentStatus) !== null) {
            return false;
        }

        $account = $sale->getTenant();
        $package = $sale->getCatalogPackage();
        $currentProvider = CommunicationProviderEnum::tryFrom($sale->getProvider() ?? '');
        if ($account === null || $package === null || $currentProvider === null) {
            return false;
        }

        $saleType = $sale instanceof CommunicationSaleRecharge ? 'recharge' : 'sale';

        $selected = $this->dispatchResolver->selectExcluding($account, $package, $saleType, [$currentProvider]);
        if ($selected === null) {
            return false;
        }

        $sale->setProvider($selected->provider->value);
        $sale->setDispatchProduct($selected->product);
        $sale->setDispatchExternalRef($selected->externalRef);
        $sale->setStateProcess(CommunicationStateEnum::CREATED->value);
        $sale->setTransactionStatus(TransactionStatus::withRetry(
            $currentStatus,
            ProviderOutcomeEnum::RETRYABLE,
            'INTERNAL_PROVIDER_FAILOVER',
            $reason,
            [
                'failoverFrom' => $currentProvider->value,
                'failoverTo' => $selected->provider->value,
                'reason' => $reason,
            ],
        ));

        $this->historicalSaleService->createHistoricalCommunication(
            $sale->getId(),
            CommunicationStateEnum::CREATED,
            $sale->getTransactionStatus(),
        );

        return true;
    }
}
