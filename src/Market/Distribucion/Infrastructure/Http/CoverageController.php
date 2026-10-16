<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Infrastructure\Http;

use App\Market\Distribucion\Application\BulkCoverageByProvince;
use App\Market\Distribucion\Application\CoverageInput;
use App\Market\Distribucion\Application\CoverageRow;
use App\Market\Distribucion\Application\FindServingWarehouse;
use App\Market\Distribucion\Application\ListCoverage;
use App\Market\Distribucion\Application\SetCoverage;
use App\Market\Distribucion\Application\SetCoverageActive;
use App\Market\Distribucion\Application\SetDeliveryCurrency;
use App\Market\Distribucion\Domain\CoverageConfirmationRequired;
use App\Market\Distribucion\Domain\CoverageMunicipalityInvalid;
use App\Market\Distribucion\Domain\CoverageNotFound;
use App\Market\Distribucion\Domain\CoverageWarehouseInvalid;
use App\Market\Distribucion\Domain\InvalidCoverage;
use App\OpenApi\Attribute\DashboardEndpoint;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Cobertura y precio de entrega por municipio de un almacén (COMR-72). El controlador solo traduce HTTP.
 * Por ahora solo el administrador la edita: el acceso del socio (con su casilla «fija su cobertura y precio») llega con su historia.
 */
#[AsController]
#[Route('/market')]
#[IsGranted('ROLE_API_ADMIN')]
final class CoverageController
{
    public function __construct(
        private readonly ListCoverage $listCoverage,
        private readonly SetCoverage $setCoverage,
        private readonly SetCoverageActive $setCoverageActive,
        private readonly BulkCoverageByProvince $bulkCoverage,
        private readonly SetDeliveryCurrency $setCurrency,
        private readonly FindServingWarehouse $findServing,
    ) {
    }

    #[Route('/warehouses/{warehouseId}/coverage', name: 'market_coverage_list', methods: ['GET'], requirements: ['warehouseId' => '\d+'])]
    #[DashboardEndpoint(summary: 'Listar la cobertura y las tarifas de entrega de un almacén por municipio', tag: 'Market')]
    public function list(int $warehouseId): JsonResponse
    {
        return $this->guard(function () use ($warehouseId): JsonResponse {
            $listing = ($this->listCoverage)($warehouseId);

            return new JsonResponse(['currency' => $listing->currency, 'rows' => array_map(static fn (CoverageRow $r) => CoverageRowOutDto::from($r), $listing->rows)]);
        });
    }

    #[Route('/warehouses/{warehouseId}/coverage/{municipalityId}', name: 'market_coverage_upsert', methods: ['PUT'], requirements: ['warehouseId' => '\d+', 'municipalityId' => '\d+'])]
    #[DashboardEndpoint(summary: 'Crear o actualizar la cobertura de un municipio (una tarifa 0 o un máximo vacío piden confirmación)', tag: 'Market', requestDto: UpsertCoverageDto::class, responseDto: CoverageRowOutDto::class)]
    public function upsert(int $warehouseId, int $municipalityId, UpsertCoverageDto $dto): JsonResponse
    {
        return $this->guard(fn () => new JsonResponse(CoverageRowOutDto::from(($this->setCoverage)($warehouseId, $municipalityId, self::input($dto)))));
    }

    #[Route('/warehouses/{warehouseId}/coverage/{municipalityId}/active', name: 'market_coverage_active', methods: ['POST'], requirements: ['warehouseId' => '\d+', 'municipalityId' => '\d+'])]
    #[DashboardEndpoint(summary: 'Dejar de cubrir (o volver a cubrir) un municipio: desactiva la fila, no la borra', tag: 'Market', requestDto: SetCoverageActiveDto::class, responseDto: CoverageRowOutDto::class)]
    public function setActive(int $warehouseId, int $municipalityId, SetCoverageActiveDto $dto): JsonResponse
    {
        return $this->guard(fn () => new JsonResponse(CoverageRowOutDto::from(($this->setCoverageActive)($warehouseId, $municipalityId, (bool) $dto->getActive()))));
    }

    #[Route('/warehouses/{warehouseId}/coverage/bulk', name: 'market_coverage_bulk', methods: ['POST'], requirements: ['warehouseId' => '\d+'])]
    #[DashboardEndpoint(summary: 'Alta masiva por provincia: con apply=false solo cuenta cuántos crea y cuántos actualiza', tag: 'Market', requestDto: BulkCoverageDto::class)]
    public function bulk(int $warehouseId, BulkCoverageDto $dto): JsonResponse
    {
        return $this->guard(function () use ($warehouseId, $dto): JsonResponse {
            $result = ($this->bulkCoverage)(
                $warehouseId,
                (int) $dto->getProvinceId(),
                new CoverageInput((string) $dto->getRatePerKg(), (string) $dto->getRatePerL(), (string) $dto->getMinPrice(), $dto->getMaxPrice(), (int) $dto->getPriority(), (bool) $dto->getConfirmZeroRate(), (bool) $dto->getConfirmNoMax()),
                (bool) $dto->getOverwrite(),
                (bool) $dto->getApply(),
            );

            return new JsonResponse(['create' => $result->create, 'update' => $result->update, 'skipped' => $result->skipped, 'applied' => $result->applied]);
        });
    }

    #[Route('/warehouses/{warehouseId}/delivery-currency', name: 'market_coverage_currency', methods: ['PUT'], requirements: ['warehouseId' => '\d+'])]
    #[DashboardEndpoint(summary: 'Fijar la moneda de las tarifas de entrega del almacén (una sola por almacén)', tag: 'Market', requestDto: SetDeliveryCurrencyDto::class)]
    public function setCurrency(int $warehouseId, SetDeliveryCurrencyDto $dto): JsonResponse
    {
        return $this->guard(fn () => new JsonResponse(['currency' => ($this->setCurrency)($warehouseId, (string) $dto->getCurrency())]));
    }

    #[Route('/delivery/serving', name: 'market_delivery_serving', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Qué almacén atiende un municipio (filtro: municipality): solo los activos, gana la prioridad más baja', tag: 'Market')]
    public function serving(Request $request): JsonResponse
    {
        $municipality = $request->query->get('municipality');
        $serving = is_string($municipality) && ctype_digit($municipality) ? ($this->findServing)((int) $municipality) : null;

        return new JsonResponse(['serving' => $serving === null ? null : [
            'warehouseId' => $serving->coverage->warehouseId(),
            'municipalityId' => $serving->coverage->municipalityId(),
            'priority' => $serving->coverage->priority(),
            'tie' => $serving->tie,
        ]]);
    }

    private static function input(UpsertCoverageDto $dto): CoverageInput
    {
        return new CoverageInput((string) $dto->getRatePerKg(), (string) $dto->getRatePerL(), (string) $dto->getMinPrice(), $dto->getMaxPrice(), (int) $dto->getPriority(), (bool) $dto->getConfirmZeroRate(), (bool) $dto->getConfirmNoMax());
    }

    /**
     * @param callable(): JsonResponse $action
     */
    private function guard(callable $action): JsonResponse
    {
        try {
            return $action();
        } catch (CoverageNotFound $e) {
            return $this->error('COVERAGE_NOT_FOUND', $e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (CoverageConfirmationRequired $e) {
            return $this->error('COVERAGE_CONFIRMATION_REQUIRED', $e->getMessage(), Response::HTTP_CONFLICT, ['confirm' => $e->what()]);
        } catch (CoverageWarehouseInvalid $e) {
            return $this->error('COVERAGE_WAREHOUSE_INVALID', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (CoverageMunicipalityInvalid $e) {
            return $this->error('COVERAGE_MUNICIPALITY_INVALID', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (InvalidCoverage $e) {
            return $this->error('INVALID_COVERAGE', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function error(string $code, string $message, int $status, array $extra = []): JsonResponse
    {
        return new JsonResponse(['error' => ['message' => $message, 'code' => $code] + $extra], $status);
    }
}
