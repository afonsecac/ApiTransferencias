<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

use App\Market\Catalogo\Application\BrandDetails;
use App\Market\Catalogo\Application\CreateBrand;
use App\Market\Catalogo\Application\GetBrand;
use App\Market\Catalogo\Application\ListBrands;
use App\Market\Catalogo\Application\SetBrandActive;
use App\Market\Catalogo\Application\UpdateBrand;
use App\Market\Catalogo\Domain\BrandAlreadyExists;
use App\Market\Catalogo\Domain\BrandNotFound;
use App\Market\Catalogo\Domain\BrandSellerInvalid;
use App\Market\Catalogo\Domain\InvalidBrand;
use App\Market\Catalogo\Domain\InvalidSeller;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Shared\Domain\InvalidSlug;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Marcas del mercado (COMR-70). El controlador solo traduce HTTP: el DTO ya viene validado por el resolver, la lógica está en
 * los casos de uso y las excepciones de dominio se convierten en respuestas aquí.
 */
#[AsController]
#[Route('/market/brands')]
#[IsGranted('ROLE_API_ADMIN')]
final class BrandController
{
    public function __construct(
        private readonly ListBrands $listBrands,
        private readonly GetBrand $getBrand,
        private readonly CreateBrand $createBrand,
        private readonly UpdateBrand $updateBrand,
        private readonly SetBrandActive $setBrandActive,
    ) {
    }

    #[Route('', name: 'market_brands_list', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar las marcas (filtros: q, seller, active)', tag: 'Market', responseDto: BrandOutDto::class, responseIsArray: true)]
    public function list(Request $request): JsonResponse
    {
        $query = $request->query->get('q');
        $seller = $request->query->get('seller');
        $active = match ($request->query->get('active')) {
            'true', '1' => true,
            'false', '0' => false,
            default => null,
        };

        return new JsonResponse(array_map(
            static fn (BrandDetails $d) => BrandOutDto::from($d),
            ($this->listBrands)(is_string($query) ? $query : null, is_string($seller) && ctype_digit($seller) ? (int) $seller : null, $active),
        ));
    }

    #[Route('/{id}', name: 'market_brands_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Obtener una marca', tag: 'Market', responseDto: BrandOutDto::class)]
    public function get(int $id): JsonResponse
    {
        return $this->respond(fn () => ($this->getBrand)($id));
    }

    #[Route('', name: 'market_brands_create', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Crear una marca de un vendedor', tag: 'Market', requestDto: CreateBrandDto::class, responseDto: BrandOutDto::class, responseStatusCode: 201)]
    public function create(CreateBrandDto $dto): JsonResponse
    {
        return $this->respond(fn () => ($this->createBrand)((int) $dto->getSellerId(), (string) $dto->getName(), $dto->getSlug()), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'market_brands_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Cambiar el nombre de una marca (el identificador y el vendedor no cambian)', tag: 'Market', requestDto: UpdateBrandDto::class, responseDto: BrandOutDto::class)]
    public function update(int $id, UpdateBrandDto $dto): JsonResponse
    {
        return $this->respond(fn () => ($this->updateBrand)($id, (string) $dto->getName()));
    }

    #[Route('/{id}/active', name: 'market_brands_active', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Activar o desactivar una marca (no se borra)', tag: 'Market', requestDto: SetBrandActiveDto::class, responseDto: BrandOutDto::class)]
    public function setActive(int $id, SetBrandActiveDto $dto): JsonResponse
    {
        return $this->respond(fn () => ($this->setBrandActive)($id, (bool) $dto->getActive()));
    }

    /**
     * @param callable(): BrandDetails $action
     */
    private function respond(callable $action, int $status = Response::HTTP_OK): JsonResponse
    {
        try {
            return new JsonResponse(BrandOutDto::from($action()), $status);
        } catch (BrandNotFound $e) {
            return $this->error('BRAND_NOT_FOUND', $e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (BrandAlreadyExists $e) {
            return $this->error('BRAND_ALREADY_EXISTS', $e->getMessage(), Response::HTTP_CONFLICT);
        } catch (BrandSellerInvalid $e) {
            return $this->error('BRAND_SELLER_INVALID', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (InvalidBrand|InvalidSeller|InvalidSlug $e) {
            return $this->error('INVALID_BRAND', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => ['message' => $message, 'code' => $code]], $status);
    }
}
