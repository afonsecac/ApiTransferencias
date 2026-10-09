<?php

namespace App\Service\Etecsa;

use App\Entity\CommunicationSaleRecharge;
use App\Enums\CommunicationProviderEnum;
use App\Enums\CommunicationStateEnum;
use App\Enums\ProviderOutcomeEnum;
use App\Exception\MyCurrentException;
use App\Provider\TransactionStatus;
use App\Service\HistoricalSaleService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Reintento SEGURO de una recarga ETECSA, vía POST /sale/retry — reemplaza,
 * solo para ETECSA, al reenvío ciego de
 * CommunicationSaleService::tryAgainWithTransaction() (que vuelve a mandar
 * /sale/recharge con el mismo transactionId sin verificar antes si la
 * recarga original ya le llegó a ETECSA, con riesgo real de cobrar dos
 * veces). DTOne/CSQ no tienen hoy un endpoint equivalente documentado y
 * siguen usando tryAgainWithTransaction() tal cual.
 *
 * Servicio nuevo con pocas dependencias (no extiende CommonService, ver
 * CLAUDE.md regla 5) en vez de crecer más el constructor ya grande de
 * CommunicationSaleService.
 */
class EtecsaRetryService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EtecsaGatewayClient $etecsaGatewayClient,
        private readonly HistoricalSaleService $historicalSaleService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{transactionId: ?string, previousStatus: ?string, status: ?string, action: ?string, message: ?string}
     */
    public function retry(int $saleId, string $mode, string $reason): array
    {
        $recharge = $this->em->getRepository(CommunicationSaleRecharge::class)->find($saleId);
        if (!$recharge instanceof CommunicationSaleRecharge) {
            throw new MyCurrentException('SALE_NOT_FOUND', 'Sale not found', 404);
        }

        if ($recharge->getProvider() !== CommunicationProviderEnum::ETECSA->value) {
            throw new MyCurrentException(
                'RETRY_PROVIDER_NOT_SUPPORTED',
                'Safe retry via /sale/retry is only implemented for ETECSA sales',
                422,
            );
        }

        $previousState = $recharge->getState();
        $allowedStates = [CommunicationStateEnum::PENDING, CommunicationStateEnum::FAILED];
        if ($previousState === null || !in_array($previousState, $allowedStates, true)) {
            throw new MyCurrentException(
                'RETRY_INVALID_STATE',
                'Retry only allowed for Pending or Failed sales.',
                400,
            );
        }

        $environment = $recharge->getTenant()?->getEnvironment();
        if ($environment === null) {
            throw new MyCurrentException('RETRY_NO_ENVIRONMENT', 'Sale has no resolvable environment', 500);
        }

        $transactionId = $recharge->getTransactionId();
        if ($transactionId === null) {
            throw new MyCurrentException('RETRY_NO_TRANSACTION_ID', 'Sale has no transactionId', 500);
        }

        $result = $this->etecsaGatewayClient->retrySale(
            $environment,
            $transactionId,
            $mode,
            $reason,
            $previousState->value,
        );

        $status = $result['status'];
        $body = $result['body'];

        if ($status !== 200) {
            $message = $this->stringOrNull($body['message'] ?? null)
                ?? $this->stringOrNull($body['error'] ?? null)
                ?? 'ETECSA rejected the retry request.';

            throw new MyCurrentException($this->codeWorkForStatus($status), $message, $status);
        }

        $newStatusRaw = $this->stringOrNull($body['status'] ?? null);
        $newState = $newStatusRaw !== null ? CommunicationStateEnum::tryFrom($newStatusRaw) : null;
        $message = $this->stringOrNull($body['message'] ?? null);

        $envelope = TransactionStatus::fromRetryResult(
            outcome: $newState !== null ? $this->outcomeFor($newState) : ProviderOutcomeEnum::PENDING,
            provider: CommunicationProviderEnum::ETECSA->value,
            providerReference: $recharge->getTransactionOrder(),
            providerCode: $this->stringOrNull($body['etecsaStateCode'] ?? null),
            message: $message,
            raw: $body,
            context: ['retryMode' => $mode, 'reason' => $reason],
        );

        if ($newState !== null) {
            $recharge->setState($newState);
            $recharge->setStateProcess($newState->value);
        } else {
            // No hay case en CommunicationStateEnum para Started/Cancelled/
            // Ordered/AuthFailed (ver docstring de /sale/retry): se deja el
            // estado local intacto en vez de forzar un mapeo inventado —
            // el envelope igual queda con el status real de ETECSA para
            // auditoría.
            $this->logger->warning(sprintf(
                'ETECSA retry sale %d: unmapped status "%s", local state left unchanged.',
                $saleId,
                $newStatusRaw ?? 'null',
            ));
        }

        $recharge->setTransactionStatus($envelope);
        $this->em->flush();

        $this->historicalSaleService->createHistoricalCommunication(
            $saleId,
            $newState ?? $previousState,
            $envelope,
        );
        $this->em->flush();

        return [
            'transactionId' => $this->stringOrNull($body['transactionId'] ?? null) ?? $transactionId,
            'previousStatus' => $this->stringOrNull($body['previousStatus'] ?? null) ?? $previousState->value,
            'status' => $newStatusRaw,
            'action' => $this->stringOrNull($body['action'] ?? null),
            'message' => $message,
        ];
    }

    private function outcomeFor(CommunicationStateEnum $state): ProviderOutcomeEnum
    {
        return match ($state) {
            CommunicationStateEnum::COMPLETED => ProviderOutcomeEnum::COMPLETED,
            CommunicationStateEnum::REJECTED => ProviderOutcomeEnum::REJECTED,
            CommunicationStateEnum::FAILED => ProviderOutcomeEnum::FAILED,
            CommunicationStateEnum::CREATED,
            CommunicationStateEnum::RESERVED,
            CommunicationStateEnum::PENDING => ProviderOutcomeEnum::PENDING,
        };
    }

    private function codeWorkForStatus(int $status): string
    {
        return match ($status) {
            404 => 'RETRY_NOT_FOUND',
            409 => 'RETRY_CONFLICT',
            422 => 'RETRY_NOT_A_RECHARGE',
            503 => 'RETRY_PROVIDER_UNAVAILABLE',
            401, 403 => 'RETRY_UNAUTHORIZED',
            default => 'RETRY_FAILED',
        };
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
