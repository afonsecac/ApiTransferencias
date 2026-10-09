<?php

namespace App\Service;

use App\DTO\Out\RechargeVerificationOutDto;
use App\Entity\CommunicationSaleRecharge;
use App\Enums\CommunicationStateEnum;
use App\Exception\MyCurrentException;
use App\Repository\CommunicationSaleInfoRepository;

class RechargeVerificationService
{
    public function __construct(
        private readonly CommunicationSaleInfoRepository $saleRepository,
    ) {
    }

    public function verify(string $transactionId): RechargeVerificationOutDto
    {
        $sale = $this->saleRepository->findOneByTransactionId($transactionId);

        // Mismo 404 exista o no la venta, o esté en cualquier estado
        // distinto de Completed — endpoint público sin autenticación, no
        // debe confirmar que un transactionId es válido pero aún no terminó.
        if ($sale === null || $sale->getState() !== CommunicationStateEnum::COMPLETED) {
            throw new MyCurrentException('RECHARGE_NOT_FOUND', 'Recharge not found', 404);
        }

        $dto = new RechargeVerificationOutDto();
        $dto->transactionId = (string) $sale->getTransactionId();
        $dto->etecsaOrderId = $sale->getTransactionOrder();
        $dto->clientId = $sale->getTenant()?->getClient()?->getId();
        $dto->enteredAt = $sale->getCreatedAt()?->format('Y-m-d\TH:i:s\Z');
        $dto->state = $sale->getState()->value;
        $dto->phoneMasked = $sale instanceof CommunicationSaleRecharge
            ? $this->maskPhone($sale->getPhoneNumber())
            : null;
        $dto->package = $sale->getCatalogPackage()?->getName() ?? $sale->getDispatchProduct()?->getDescription();
        $dto->destinationAmount = $sale->getDestinationAmount();
        $dto->destinationCurrency = $sale->getDestinationCurrency();

        $promotion = $sale->getCatalogPackage()?->getPromotion();
        if ($promotion !== null) {
            $dto->promotion = [
                'name' => $promotion->getName(),
                'description' => $promotion->getDescription(),
            ];
        }

        $history = array_slice($sale->getHistorical()->toArray(), 0, 3);
        $dto->history = array_map(
            static fn ($entry) => [
                'state' => $entry->getState()->value,
                'occurredAt' => $entry->getCreatedAt()?->format('Y-m-d\TH:i:s\Z'),
            ],
            $history,
        );

        return $dto;
    }

    /**
     * Deja visibles los primeros 2 dígitos (código de país) y los últimos 4
     * (lo mínimo para que el cliente reconozca que el comprobante es suyo),
     * enmascara el resto — endpoint público sin autenticación.
     */
    private function maskPhone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        $length = \strlen($phone);
        if ($length <= 6) {
            return str_repeat('*', $length);
        }

        $visibleStart = substr($phone, 0, 2);
        $visibleEnd = substr($phone, -4);
        $maskedLength = $length - \strlen($visibleStart) - \strlen($visibleEnd);

        return $visibleStart . str_repeat('*', $maskedLength) . $visibleEnd;
    }
}
