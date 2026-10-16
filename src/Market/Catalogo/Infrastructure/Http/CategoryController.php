<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

use App\Market\Catalogo\Application\CategoryDetails;
use App\Market\Catalogo\Application\CreateCategory;
use App\Market\Catalogo\Application\GetCategory;
use App\Market\Catalogo\Application\ListCategories;
use App\Market\Catalogo\Application\MoveCategory;
use App\Market\Catalogo\Application\SetCategoryActive;
use App\Market\Catalogo\Application\UpdateCategory;
use App\Market\Catalogo\Domain\CategoryAlreadyExists;
use App\Market\Catalogo\Domain\CategoryCycle;
use App\Market\Catalogo\Domain\CategoryDepthExceeded;
use App\Market\Catalogo\Domain\CategoryNotFound;
use App\Market\Catalogo\Domain\InvalidCategory;
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
 * Categorías del mercado (COMR-70): un árbol de hasta tres niveles. El controlador solo traduce HTTP: el DTO ya viene validado
 * por el resolver, la lógica está en los casos de uso y las excepciones de dominio se convierten en respuestas aquí.
 */
#[AsController]
#[Route('/market/categories')]
#[IsGranted('ROLE_API_ADMIN')]
final class CategoryController
{
    public function __construct(
        private readonly ListCategories $listCategories,
        private readonly GetCategory $getCategory,
        private readonly CreateCategory $createCategory,
        private readonly UpdateCategory $updateCategory,
        private readonly MoveCategory $moveCategory,
        private readonly SetCategoryActive $setCategoryActive,
    ) {
    }

    #[Route('', name: 'market_categories_list', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar las categorías en el orden del árbol, con su nivel (filtros: q, active)', tag: 'Market', responseDto: CategoryOutDto::class, responseIsArray: true)]
    public function list(Request $request): JsonResponse
    {
        $query = $request->query->get('q');
        $active = match ($request->query->get('active')) {
            'true', '1' => true,
            'false', '0' => false,
            default => null,
        };

        return new JsonResponse(array_map(
            static fn (CategoryDetails $d) => CategoryOutDto::from($d),
            ($this->listCategories)(is_string($query) ? $query : null, $active),
        ));
    }

    #[Route('/{id}', name: 'market_categories_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Obtener una categoría', tag: 'Market', responseDto: CategoryOutDto::class)]
    public function get(int $id): JsonResponse
    {
        return $this->respond(fn () => ($this->getCategory)($id));
    }

    #[Route('', name: 'market_categories_create', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Crear una categoría (raíz o bajo un padre, hasta tres niveles)', tag: 'Market', requestDto: CreateCategoryDto::class, responseDto: CategoryOutDto::class, responseStatusCode: 201)]
    public function create(CreateCategoryDto $dto): JsonResponse
    {
        return $this->respond(
            fn () => ($this->createCategory)($dto->getParentId(), (string) $dto->getNameEs(), $dto->getNameEn(), $dto->getSlug(), $dto->getPosition() ?? 0),
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'market_categories_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Cambiar los nombres y la posición de una categoría (el identificador no cambia)', tag: 'Market', requestDto: UpdateCategoryDto::class, responseDto: CategoryOutDto::class)]
    public function update(int $id, UpdateCategoryDto $dto): JsonResponse
    {
        return $this->respond(fn () => ($this->updateCategory)($id, (string) $dto->getNameEs(), $dto->getNameEn(), (int) $dto->getPosition()));
    }

    #[Route('/{id}/move', name: 'market_categories_move', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Mover una categoría con su subárbol bajo otro padre, o a la raíz', tag: 'Market', requestDto: MoveCategoryDto::class, responseDto: CategoryOutDto::class)]
    public function move(int $id, MoveCategoryDto $dto): JsonResponse
    {
        return $this->respond(fn () => ($this->moveCategory)($id, $dto->getParentId()));
    }

    #[Route('/{id}/active', name: 'market_categories_active', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Activar o desactivar una categoría (no se borra)', tag: 'Market', requestDto: SetCategoryActiveDto::class, responseDto: CategoryOutDto::class)]
    public function setActive(int $id, SetCategoryActiveDto $dto): JsonResponse
    {
        return $this->respond(fn () => ($this->setCategoryActive)($id, (bool) $dto->getActive()));
    }

    /**
     * @param callable(): CategoryDetails $action
     */
    private function respond(callable $action, int $status = Response::HTTP_OK): JsonResponse
    {
        try {
            return new JsonResponse(CategoryOutDto::from($action()), $status);
        } catch (CategoryNotFound $e) {
            return $this->error('CATEGORY_NOT_FOUND', $e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (CategoryAlreadyExists $e) {
            return $this->error('CATEGORY_ALREADY_EXISTS', $e->getMessage(), Response::HTTP_CONFLICT);
        } catch (CategoryDepthExceeded $e) {
            return $this->error('CATEGORY_DEPTH_EXCEEDED', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (CategoryCycle $e) {
            return $this->error('CATEGORY_CYCLE', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (InvalidCategory|InvalidSeller|InvalidSlug $e) {
            return $this->error('INVALID_CATEGORY', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => ['message' => $message, 'code' => $code]], $status);
    }
}
