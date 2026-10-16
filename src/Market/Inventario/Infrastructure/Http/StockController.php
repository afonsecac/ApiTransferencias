<?php

declare(strict_types=1);

namespace App\Market\Inventario\Infrastructure\Http;

use App\Entity\User;
use App\Market\Inventario\Application\AdjustStock;
use App\Market\Inventario\Application\SetSafetyStock;
use App\Market\Inventario\Application\StockLineDetails;
use App\Market\Inventario\Domain\InvalidStock;
use App\Market\Inventario\Domain\StockActor;
use App\Market\Inventario\Domain\StockBelowReserved;
use App\Market\Inventario\Domain\StockLineNotFound;
use App\Market\Inventario\Domain\StockProductInvalid;
use App\Market\Inventario\Domain\StockWarehouseInvalid;
use App\OpenApi\Attribute\DashboardEndpoint;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Existencias y movimientos de stock (COMR-193 y COMR-194). El controlador solo traduce HTTP.
 */
#[AsController]
#[Route('/market/stock')]
#[IsGranted('ROLE_API_ADMIN')]
final class StockController
{
    public function __construct(
        private readonly AdjustStock $adjustStock,
        private readonly SetSafetyStock $setSafetyStock,
        private readonly Security $security,
    ) {
    }

    #[Route('/adjust', name: 'market_stock_adjust', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Ajustar el stock con motivo y comentario obligatorios; deja un movimiento inmutable', tag: 'Market', requestDto: AdjustStockDto::class, responseDto: StockLineOutDto::class)]
    public function adjust(AdjustStockDto $dto): JsonResponse
    {
        return $this->respond(fn () => ($this->adjustStock)(
            (int) $dto->getWarehouseId(),
            $dto->getItemType() ?? 'product',
            (int) $dto->getItemId(),
            (int) $dto->getDelta(),
            (string) $dto->getReason(),
            (string) $dto->getComment(),
            $dto->getReference(),
            $this->actor(),
        ));
    }

    #[Route('/safety', name: 'market_stock_safety', methods: ['PUT'])]
    #[DashboardEndpoint(summary: 'Fijar el stock de seguridad de una línea de existencias', tag: 'Market', requestDto: SetSafetyStockDto::class, responseDto: StockLineOutDto::class)]
    public function setSafety(SetSafetyStockDto $dto): JsonResponse
    {
        return $this->respond(fn () => ($this->setSafetyStock)((int) $dto->getWarehouseId(), $dto->getItemType() ?? 'product', (int) $dto->getItemId(), (int) $dto->getSafety()));
    }

    private function actor(): StockActor
    {
        $user = $this->security->getUser();

        return new StockActor($user instanceof User ? $user->getId() : null, $user?->getUserIdentifier() ?? 'sistema');
    }

    /**
     * @param callable(): StockLineDetails $action
     */
    private function respond(callable $action): JsonResponse
    {
        try {
            return new JsonResponse(StockLineOutDto::from($action()));
        } catch (StockLineNotFound $e) {
            return $this->error('STOCK_LINE_NOT_FOUND', $e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (StockBelowReserved $e) {
            return $this->error('STOCK_BELOW_RESERVED', $e->getMessage(), Response::HTTP_CONFLICT);
        } catch (StockWarehouseInvalid $e) {
            return $this->error('STOCK_WAREHOUSE_INVALID', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (StockProductInvalid $e) {
            return $this->error('STOCK_PRODUCT_INVALID', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (InvalidStock $e) {
            return $this->error('INVALID_STOCK', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => ['message' => $message, 'code' => $code]], $status);
    }
}
