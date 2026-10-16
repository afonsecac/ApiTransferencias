<?php

declare(strict_types=1);

namespace App\Market\Inventario\Infrastructure\Http;

use App\Entity\User;
use App\Market\Inventario\Application\AdjustStock;
use App\Market\Inventario\Application\ListStock;
use App\Market\Inventario\Application\ListStockMovements;
use App\Market\Inventario\Application\SetSafetyStock;
use App\Market\Inventario\Application\StockLineDetails;
use App\Market\Inventario\Application\StockMovementDetails;
use App\Market\Inventario\Domain\InvalidStock;
use App\Market\Inventario\Domain\StockActor;
use App\Market\Inventario\Domain\StockBelowReserved;
use App\Market\Inventario\Domain\StockLineNotFound;
use App\Market\Inventario\Domain\StockProductInvalid;
use App\Market\Inventario\Domain\StockWarehouseInvalid;
use App\OpenApi\Attribute\DashboardEndpoint;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
        private readonly ListStock $listStock,
        private readonly ListStockMovements $listMovements,
        private readonly Security $security,
    ) {
    }

    #[Route('', name: 'market_stock_list', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar las existencias por almacén y artículo (filtros: warehouse, q, type, low)', tag: 'Market', responseDto: StockLineOutDto::class, responseIsArray: true)]
    public function list(Request $request): JsonResponse
    {
        $warehouse = $request->query->get('warehouse');
        $q = $request->query->get('q');
        $type = $request->query->get('type');

        return new JsonResponse(array_map(
            static fn (StockLineDetails $d) => StockLineOutDto::from($d),
            ($this->listStock)(
                is_string($warehouse) && ctype_digit($warehouse) ? (int) $warehouse : null,
                is_string($q) ? $q : null,
                is_string($type) && in_array($type, ['product', 'combo'], true) ? $type : null,
                in_array($request->query->get('low'), ['1', 'true'], true),
            ),
        ));
    }

    #[Route('/movements', name: 'market_stock_movements', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Últimos movimientos de stock, el más reciente primero (filtros: warehouse, item, limit)', tag: 'Market', responseDto: StockMovementOutDto::class, responseIsArray: true)]
    public function movements(Request $request): JsonResponse
    {
        $warehouse = $request->query->get('warehouse');
        $item = $request->query->get('item');
        $limit = $request->query->get('limit');

        return new JsonResponse(array_map(
            static fn (StockMovementDetails $d) => StockMovementOutDto::from($d),
            ($this->listMovements)(
                is_string($warehouse) && ctype_digit($warehouse) ? (int) $warehouse : null,
                is_string($item) && ctype_digit($item) ? (int) $item : null,
                is_string($limit) && ctype_digit($limit) ? (int) $limit : 50,
            ),
        ));
    }

    #[Route('/adjust', name: 'market_stock_adjust', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Ajustar el stock fijando el nuevo valor en mano, con motivo y comentario obligatorios; deja un movimiento inmutable con la diferencia', tag: 'Market', requestDto: AdjustStockDto::class, responseDto: StockLineOutDto::class)]
    public function adjust(AdjustStockDto $dto): JsonResponse
    {
        return $this->respond(fn () => ($this->adjustStock)(
            (int) $dto->getWarehouseId(),
            $dto->getItemType() ?? 'product',
            (int) $dto->getItemId(),
            (int) $dto->getNewOnHand(),
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
