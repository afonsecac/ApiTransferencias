<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

use App\Market\Catalogo\Application\ChangeProductStatus;
use App\Market\Catalogo\Application\CreateProduct;
use App\Market\Catalogo\Application\GetProduct;
use App\Market\Catalogo\Application\ListProducts;
use App\Market\Catalogo\Application\ProductDetails;
use App\Market\Catalogo\Application\ProductSpecInput;
use App\Market\Catalogo\Application\SetProductRelevance;
use App\Market\Catalogo\Application\SuggestSku;
use App\Market\Catalogo\Application\UpdateProduct;
use App\Market\Catalogo\Domain\CategoryNotFound;
use App\Market\Catalogo\Domain\InvalidProduct;
use App\Market\Catalogo\Domain\InvalidProductTransition;
use App\Market\Catalogo\Domain\ProductAlreadyExists;
use App\Market\Catalogo\Domain\ProductBrandInvalid;
use App\Market\Catalogo\Domain\ProductCategoryInvalid;
use App\Market\Catalogo\Domain\MeasureSettings;
use App\Market\Catalogo\Domain\MeasureSystem;
use App\Market\Catalogo\Domain\ProductNotFound;
use App\Market\Catalogo\Domain\ProductSellerInvalid;
use App\Market\Catalogo\Domain\ProductStatus;
use App\Market\Catalogo\Domain\SellerNotFound;
use App\Market\Catalogo\Infrastructure\Storage\ImageUrls;
use App\OpenApi\Attribute\DashboardEndpoint;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Productos del mercado (COMR-273). El controlador solo traduce HTTP: el DTO ya viene validado por el resolver, la lógica
 * está en los casos de uso y las excepciones de dominio se convierten en respuestas aquí.
 */
#[AsController]
#[Route('/market/products')]
#[IsGranted('ROLE_API_ADMIN')]
final class ProductController
{
    public function __construct(
        private readonly ListProducts $listProducts,
        private readonly GetProduct $getProduct,
        private readonly CreateProduct $createProduct,
        private readonly UpdateProduct $updateProduct,
        private readonly ChangeProductStatus $changeStatus,
        private readonly SetProductRelevance $setRelevance,
        private readonly SuggestSku $suggestSku,
        private readonly ImageUrls $imageUrls,
        private readonly MeasureSettings $measureSettings,
    ) {
    }

    #[Route('', name: 'market_products_list', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar los productos, paginados (filtros: q, status, seller, brand, category, subcategories, relevant, page, perPage, lang)', tag: 'Market', responseDto: ProductPageOutDto::class)]
    public function list(Request $request): JsonResponse
    {
        $query = $request->query->get('q');
        $page = ($this->listProducts)(
            is_string($query) && trim($query) !== '' ? trim($query) : null,
            ProductStatus::tryFrom((string) $request->query->get('status', '')),
            $this->id($request, 'seller'),
            $this->id($request, 'brand'),
            $this->id($request, 'category'),
            $request->query->get('subcategories', '1') !== '0',
            $this->id($request, 'page') ?? 1,
            $this->id($request, 'perPage') ?? 25,
            $request->query->get('relevant') === '1' ? new \DateTimeImmutable('today') : null,
        );

        return new JsonResponse(ProductPageOutDto::from($page, $this->language($request), $this->imageUrls, MeasureSystem::fromSettings($this->measureSettings)));
    }

    #[Route('/sku-suggestion', name: 'market_products_sku_suggestion', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Proponer el sku de un producto nuevo: CATEGORÍA-NOMBRE-NNN en mayúsculas, con el siguiente consecutivo libre (parámetros: name, category)', tag: 'Market', responseDto: SkuSuggestionOutDto::class)]
    public function suggestSku(Request $request): JsonResponse
    {
        $name = $request->query->get('name');
        try {
            $sku = ($this->suggestSku)(is_string($name) ? $name : '', $this->id($request, 'category'));
        } catch (CategoryNotFound $e) {
            return $this->error('CATEGORY_NOT_FOUND', $e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (InvalidProduct $e) {
            return $this->error('INVALID_PRODUCT', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(new SkuSuggestionOutDto($sku->value()));
    }

    #[Route('/{id}', name: 'market_products_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Obtener un producto (con lang, solo los textos de ese idioma)', tag: 'Market', responseDto: ProductOutDto::class)]
    public function get(int $id, Request $request): JsonResponse
    {
        return $this->respond(fn () => ($this->getProduct)($id), $this->language($request));
    }

    #[Route('', name: 'market_products_create', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Crear un producto (con contenido, o con valor si es digital) en borrador (sin vendedor, el de plataforma)', tag: 'Market', requestDto: CreateProductDto::class, responseDto: ProductOutDto::class, responseStatusCode: 201)]
    public function create(CreateProductDto $dto): JsonResponse
    {
        return $this->respond(
            fn () => ($this->createProduct)((string) $dto->getSku(), $dto->getSellerId(), $this->input($dto)),
            null,
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'market_products_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Actualizar un producto (el sku y el vendedor no cambian)', tag: 'Market', requestDto: UpdateProductDto::class, responseDto: ProductOutDto::class)]
    public function update(int $id, UpdateProductDto $dto): JsonResponse
    {
        return $this->respond(fn () => ($this->updateProduct)($id, $this->input($dto)));
    }

    #[Route('/{id}/status', name: 'market_products_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Publicar o retirar un producto', tag: 'Market', requestDto: ChangeProductStatusDto::class, responseDto: ProductOutDto::class)]
    public function changeStatus(int $id, ChangeProductStatusDto $dto): JsonResponse
    {
        return $this->respond(fn () => ($this->changeStatus)($id, ProductStatus::from((string) $dto->getStatus())));
    }

    #[Route('/{id}/relevance', name: 'market_products_relevance', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Marcar un producto como relevante en las búsquedas (nivel 0 a 3, con vigencia opcional) o quitarle la marca (nivel 0)', tag: 'Market', requestDto: SetProductRelevanceDto::class, responseDto: ProductOutDto::class)]
    public function setRelevance(int $id, SetProductRelevanceDto $dto): JsonResponse
    {
        return $this->respond(fn () => ($this->setRelevance)($id, (int) $dto->getLevel(), $dto->getFrom(), $dto->getUntil()));
    }

    private function input(ProductSpecDto $dto): ProductSpecInput
    {
        return new ProductSpecInput(
            $dto->getTexts() ?? [],
            (string) $dto->getService(),
            (string) $dto->getSubservice(),
            $dto->getArea(),
            $dto->getContentValue(),
            $dto->getContentUnit(),
            $dto->getUnitWeightValue(),
            $dto->getUnitWeightUnit(),
            $dto->getUnitVolumeValue(),
            $dto->getUnitVolumeUnit(),
            $dto->getLengthMm(),
            $dto->getWidthMm(),
            $dto->getHeightMm(),
            $dto->getDeliveryKind(),
            $dto->getDeliveryUnit(),
            $dto->getBarcode(),
            $dto->getValueCurrency(),
            $dto->getValueMin(),
            $dto->getValueMax(),
            $dto->getBrandId(),
            $dto->getCategoryIds() ?? [],
            $dto->getDimensionsUnit(),
        );
    }

    private function id(Request $request, string $name): ?int
    {
        $value = $request->query->get($name);

        return is_string($value) && ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function language(Request $request): ?string
    {
        $lang = $request->query->get('lang');

        return is_string($lang) && $lang !== '' ? $lang : null;
    }

    /**
     * @param callable(): ProductDetails $action
     */
    private function respond(callable $action, ?string $language = null, int $status = Response::HTTP_OK): JsonResponse
    {
        try {
            return new JsonResponse(ProductOutDto::from($action(), $language, $this->imageUrls, MeasureSystem::fromSettings($this->measureSettings)), $status);
        } catch (ProductNotFound $e) {
            return $this->error('PRODUCT_NOT_FOUND', $e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (SellerNotFound $e) {
            return $this->error('SELLER_NOT_FOUND', $e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (ProductAlreadyExists $e) {
            return $this->error('PRODUCT_ALREADY_EXISTS', $e->getMessage(), Response::HTTP_CONFLICT);
        } catch (InvalidProductTransition $e) {
            return $this->error('INVALID_PRODUCT_TRANSITION', $e->getMessage(), Response::HTTP_CONFLICT);
        } catch (ProductBrandInvalid $e) {
            return $this->error($e->reason(), $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (ProductCategoryInvalid $e) {
            return $this->error('PRODUCT_CATEGORY_INVALID', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (ProductSellerInvalid $e) {
            return $this->error('PRODUCT_SELLER_INVALID', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (InvalidProduct $e) {
            return $this->error('INVALID_PRODUCT', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => ['message' => $message, 'code' => $code]], $status);
    }
}
