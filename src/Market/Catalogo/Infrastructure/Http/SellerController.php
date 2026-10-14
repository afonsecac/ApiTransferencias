<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

use App\Market\Catalogo\Application\CreateSeller;
use App\Market\Catalogo\Application\GetSeller;
use App\Market\Catalogo\Application\ListSellers;
use App\Market\Catalogo\Application\SellerDetails;
use App\Market\Catalogo\Application\SetSellerActive;
use App\Market\Catalogo\Application\UpdateSeller;
use App\Market\Catalogo\Domain\InvalidSeller;
use App\Market\Catalogo\Domain\PlatformSellerProtected;
use App\Market\Catalogo\Domain\SellerAlreadyExists;
use App\Market\Catalogo\Domain\SellerNotFound;
use App\Market\Catalogo\Domain\SellerOrgUnitInvalid;
use App\OpenApi\Attribute\DashboardEndpoint;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Vendedores del mercado (COMR-103). El controlador solo traduce HTTP: el DTO ya viene validado por el resolver,
 * la lógica está en los casos de uso y las excepciones de dominio se convierten en respuestas aquí.
 */
#[AsController]
#[Route('/market/sellers')]
#[IsGranted('ROLE_API_ADMIN')]
final class SellerController
{
    public function __construct(
        private readonly ListSellers $listSellers,
        private readonly GetSeller $getSeller,
        private readonly CreateSeller $createSeller,
        private readonly UpdateSeller $updateSeller,
        private readonly SetSellerActive $setSellerActive,
    ) {
    }

    #[Route('', name: 'market_sellers_list', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar los vendedores (filtros: q, active)', tag: 'Market', responseDto: SellerOutDto::class, responseIsArray: true)]
    public function list(Request $request): JsonResponse
    {
        $query = $request->query->get('q');
        $active = match ($request->query->get('active')) {
            'true', '1' => true,
            'false', '0' => false,
            default => null,
        };

        return new JsonResponse(array_map(
            static fn (SellerDetails $d) => SellerOutDto::from($d),
            ($this->listSellers)(is_string($query) ? $query : null, $active),
        ));
    }

    #[Route('/{id}', name: 'market_sellers_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Obtener un vendedor', tag: 'Market', responseDto: SellerOutDto::class)]
    public function get(int $id): JsonResponse
    {
        return $this->respond(fn () => ($this->getSeller)($id));
    }

    #[Route('', name: 'market_sellers_create', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Crear un vendedor asociado a una unidad organizativa de tipo vendedor', tag: 'Market', requestDto: CreateSellerDto::class, responseDto: SellerOutDto::class, responseStatusCode: 201)]
    public function create(CreateSellerDto $dto): JsonResponse
    {
        return $this->respond(
            fn () => ($this->createSeller)((int) $dto->getOrgUnitId(), (string) $dto->getName(), $dto->getSlug(), $dto->getCostCenterId()),
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'market_sellers_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Actualizar el nombre y el centro de costo de un vendedor', tag: 'Market', requestDto: UpdateSellerDto::class, responseDto: SellerOutDto::class)]
    public function update(int $id, UpdateSellerDto $dto): JsonResponse
    {
        return $this->respond(fn () => ($this->updateSeller)($id, (string) $dto->getName(), $dto->getCostCenterId()));
    }

    #[Route('/{id}/active', name: 'market_sellers_active', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Activar o desactivar un vendedor (el de plataforma no se desactiva)', tag: 'Market', requestDto: SetSellerActiveDto::class, responseDto: SellerOutDto::class)]
    public function setActive(int $id, SetSellerActiveDto $dto): JsonResponse
    {
        return $this->respond(fn () => ($this->setSellerActive)($id, (bool) $dto->getActive()));
    }

    /**
     * @param callable(): SellerDetails $action
     */
    private function respond(callable $action, int $status = Response::HTTP_OK): JsonResponse
    {
        try {
            return new JsonResponse(SellerOutDto::from($action()), $status);
        } catch (SellerNotFound $e) {
            return $this->error('SELLER_NOT_FOUND', $e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (SellerAlreadyExists $e) {
            return $this->error('SELLER_ALREADY_EXISTS', $e->getMessage(), Response::HTTP_CONFLICT);
        } catch (PlatformSellerProtected $e) {
            return $this->error('PLATFORM_SELLER_PROTECTED', $e->getMessage(), Response::HTTP_CONFLICT);
        } catch (SellerOrgUnitInvalid $e) {
            return $this->error('SELLER_ORG_UNIT_INVALID', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (InvalidSeller $e) {
            return $this->error('INVALID_SELLER', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => ['message' => $message, 'code' => $code]], $status);
    }
}
