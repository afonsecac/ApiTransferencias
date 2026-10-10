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

    /**
     * @throws MyCurrentException con el mismo 404 genérico tanto si la venta
     *     no existe, no está Completed, o el token no coincide — endpoint
     *     público sin autenticación, no debe confirmar a un tercero ni que
     *     el transactionId es válido ni que el token está cerca de ser
     *     correcto (hash_equals evita timing attack en la comparación).
     */
    public function verify(string $transactionId, ?string $accessToken): RechargeVerificationOutDto
    {
        $sale = $this->saleRepository->findOneByTransactionId($transactionId);

        if ($sale === null
            || $sale->getState() !== CommunicationStateEnum::COMPLETED
            || $accessToken === null
            || $sale->getAccessToken() === null
            || !hash_equals($sale->getAccessToken(), $accessToken)
        ) {
            throw new MyCurrentException('RECHARGE_NOT_FOUND', 'Recharge not found', 404);
        }

        $dto = new RechargeVerificationOutDto();
        $dto->transactionId = (string) $sale->getTransactionId();
        $dto->etecsaOrderId = $sale->getTransactionOrder();
        $dto->clientId = $sale->getTenant()?->getClient()?->getId();
        $dto->enteredAt = $sale->getCreatedAt()?->format('Y-m-d\TH:i:s\Z');
        $dto->state = $sale->getState()->value;
        $dto->phone = $sale instanceof CommunicationSaleRecharge
            ? $sale->getPhoneNumber()
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
}
