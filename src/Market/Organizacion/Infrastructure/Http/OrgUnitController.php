<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Infrastructure\Http;

use App\Market\Organizacion\Application\AddCostCenter;
use App\Market\Organizacion\Application\CostCenterInput;
use App\Market\Organizacion\Application\CreateOrgUnit;
use App\Market\Organizacion\Application\GetOrgUnit;
use App\Market\Organizacion\Application\ListOrgUnits;
use App\Market\Organizacion\Application\OrgUnitInput;
use App\Market\Organizacion\Application\SetPrincipalCostCenter;
use App\Market\Organizacion\Application\UpdateCostCenter;
use App\Market\Organizacion\Application\UpdateOrgUnit;
use App\Market\Organizacion\Domain\InvalidFiscalData;
use App\Market\Organizacion\Domain\InvalidOrgUnit;
use App\Market\Organizacion\Domain\OrgUnit;
use App\Market\Organizacion\Domain\OrgUnitAlreadyExists;
use App\Market\Organizacion\Domain\OrgUnitNotFound;
use App\Market\Organizacion\Domain\OrgUnitType;
use App\OpenApi\Attribute\DashboardEndpoint;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Unidades organizativas y sus centros de costo (COMR-271). El controlador solo traduce HTTP: el DTO ya viene
 * validado por el resolver, la lógica está en los casos de uso y las excepciones de dominio se convierten en
 * respuestas aquí.
 */
#[AsController]
#[Route('/market/org-units')]
#[IsGranted('ROLE_API_ADMIN')]
final class OrgUnitController
{
    public function __construct(
        private readonly ListOrgUnits $listUnits,
        private readonly GetOrgUnit $getUnit,
        private readonly CreateOrgUnit $createUnit,
        private readonly UpdateOrgUnit $updateUnit,
        private readonly AddCostCenter $addCostCenter,
        private readonly UpdateCostCenter $updateCostCenter,
        private readonly SetPrincipalCostCenter $setPrincipal,
    ) {
    }

    #[Route('', name: 'market_org_units_list', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar las unidades organizativas (filtros: type, q)', tag: 'Market', responseDto: OrgUnitOutDto::class, responseIsArray: true)]
    public function list(Request $request): JsonResponse
    {
        $type = OrgUnitType::tryFrom((string) $request->query->get('type', ''));
        $query = $request->query->get('q');

        return new JsonResponse(array_map(
            static fn (OrgUnit $u) => OrgUnitOutDto::from($u),
            ($this->listUnits)($type, is_string($query) ? $query : null),
        ));
    }

    #[Route('/{id}', name: 'market_org_units_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Obtener una unidad organizativa', tag: 'Market', responseDto: OrgUnitOutDto::class)]
    public function get(int $id): JsonResponse
    {
        return $this->respond(fn () => ($this->getUnit)($id));
    }

    #[Route('', name: 'market_org_units_create', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Crear una unidad organizativa con su centro de costo principal', tag: 'Market', requestDto: CreateOrgUnitDto::class, responseDto: OrgUnitOutDto::class, responseStatusCode: 201)]
    public function create(CreateOrgUnitDto $dto): JsonResponse
    {
        return $this->respond(
            fn () => ($this->createUnit)(
                (string) $dto->getCountryCode(),
                new OrgUnitInput(
                    (string) $dto->getName(),
                    (string) $dto->getLegalName(),
                    (string) $dto->getTaxId(),
                    (string) $dto->getAddress(),
                    (string) $dto->getBillingEmail(),
                    array_values($dto->getTypes() ?? []),
                    $dto->getFiscalData() ?? [],
                ),
                array_values(array_map(
                    static fn (CostCenterDto $c) => new CostCenterInput((string) $c->getCode(), (string) $c->getName(), $c->getPrincipal() ?? false),
                    $dto->getCostCenters() ?? [],
                )),
            ),
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'market_org_units_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Actualizar una unidad organizativa (el país no cambia)', tag: 'Market', requestDto: UpdateOrgUnitDto::class, responseDto: OrgUnitOutDto::class)]
    public function update(int $id, UpdateOrgUnitDto $dto): JsonResponse
    {
        return $this->respond(fn () => ($this->updateUnit)(
            $id,
            new OrgUnitInput(
                (string) $dto->getName(),
                (string) $dto->getLegalName(),
                (string) $dto->getTaxId(),
                (string) $dto->getAddress(),
                (string) $dto->getBillingEmail(),
                array_values($dto->getTypes() ?? []),
                $dto->getFiscalData() ?? [],
            ),
        ));
    }

    #[Route('/{id}/cost-centers', name: 'market_org_units_cost_centers_add', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Añadir un centro de costo a una unidad', tag: 'Market', requestDto: AddCostCenterDto::class, responseDto: OrgUnitOutDto::class, responseStatusCode: 201)]
    public function addCostCenter(int $id, AddCostCenterDto $dto): JsonResponse
    {
        return $this->respond(fn () => ($this->addCostCenter)($id, (string) $dto->getCode(), (string) $dto->getName()), Response::HTTP_CREATED);
    }

    #[Route('/{id}/cost-centers/{code}', name: 'market_org_units_cost_centers_update', methods: ['PUT'], requirements: ['id' => '\d+', 'code' => '[A-Za-z0-9][A-Za-z0-9_-]*'])]
    #[DashboardEndpoint(summary: 'Cambiar el nombre o el estado de un centro de costo', tag: 'Market', requestDto: UpdateCostCenterDto::class, responseDto: OrgUnitOutDto::class)]
    public function updateCostCenter(int $id, string $code, UpdateCostCenterDto $dto): JsonResponse
    {
        return $this->respond(fn () => ($this->updateCostCenter)($id, $code, (string) $dto->getName(), $dto->getActive() ?? true));
    }

    #[Route('/{id}/cost-centers/{code}/principal', name: 'market_org_units_cost_centers_principal', methods: ['POST'], requirements: ['id' => '\d+', 'code' => '[A-Za-z0-9][A-Za-z0-9_-]*'])]
    #[DashboardEndpoint(summary: 'Elegir el centro de costo principal de una unidad', tag: 'Market', responseDto: OrgUnitOutDto::class)]
    public function setPrincipal(int $id, string $code): JsonResponse
    {
        return $this->respond(fn () => ($this->setPrincipal)($id, $code));
    }

    /**
     * @param callable(): OrgUnit $action
     */
    private function respond(callable $action, int $status = Response::HTTP_OK): JsonResponse
    {
        try {
            return new JsonResponse(OrgUnitOutDto::from($action()), $status);
        } catch (OrgUnitNotFound $e) {
            return $this->error('ORG_UNIT_NOT_FOUND', $e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (OrgUnitAlreadyExists $e) {
            return $this->error('ORG_UNIT_ALREADY_EXISTS', $e->getMessage(), Response::HTTP_CONFLICT);
        } catch (InvalidFiscalData $e) {
            return $this->error('INVALID_FISCAL_DATA', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY, $e->errors());
        } catch (InvalidOrgUnit $e) {
            return $this->error('INVALID_ORG_UNIT', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * @param array<string, string>|null $details
     */
    private function error(string $code, string $message, int $status, ?array $details = null): JsonResponse
    {
        $error = ['message' => $message, 'code' => $code];
        if ($details !== null) {
            $error['details'] = $details;
        }

        return new JsonResponse(['error' => $error], $status);
    }
}
