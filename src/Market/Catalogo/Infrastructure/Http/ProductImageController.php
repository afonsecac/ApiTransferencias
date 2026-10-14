<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

use App\Market\Catalogo\Application\DeleteProductImage;
use App\Market\Catalogo\Application\DescribeProductImage;
use App\Market\Catalogo\Application\ListProductImages;
use App\Market\Catalogo\Application\ReorderProductImages;
use App\Market\Catalogo\Application\SetProductImageReference;
use App\Market\Catalogo\Application\UploadProductImage;
use App\Market\Catalogo\Domain\ImageSettings;
use App\Market\Catalogo\Domain\InvalidGalleryOrder;
use App\Market\Catalogo\Domain\InvalidProductImage;
use App\Market\Catalogo\Domain\ProductGalleryFull;
use App\Market\Catalogo\Domain\ProductImage;
use App\Market\Catalogo\Domain\ProductImageNotFound;
use App\Market\Catalogo\Domain\ProductNotFound;
use App\Market\Catalogo\Infrastructure\Storage\ImageUrls;
use App\OpenApi\Attribute\DashboardEndpoint;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Galería de imágenes de un producto (COMR-274). La subida es multipart (campo `file`), así que es el único punto que lee
 * el Request: un archivo no cabe en el DTO JSON del resolver. Todo lo demás son DTO de entrada validados. El controlador solo
 * traduce HTTP; las reglas están en los casos de uso y en ProductGallery.
 */
#[AsController]
#[Route('/market/products/{id}/images', requirements: ['id' => '\d+'])]
#[IsGranted('ROLE_API_ADMIN')]
final class ProductImageController
{
    public function __construct(
        private readonly ListProductImages $list,
        private readonly UploadProductImage $upload,
        private readonly ReorderProductImages $reorder,
        private readonly SetProductImageReference $setReference,
        private readonly DescribeProductImage $describe,
        private readonly DeleteProductImage $delete,
        private readonly ImageUrls $urls,
        private readonly ImageSettings $settings,
    ) {
    }

    #[Route('', name: 'market_product_images_list', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar la galería de imágenes de un producto, con los límites vigentes', tag: 'Market', responseDto: ProductImagesOutDto::class)]
    public function list(int $id): JsonResponse
    {
        return $this->respond(fn () => $this->gallery(($this->list)($id)));
    }

    #[Route('', name: 'market_product_images_upload', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Subir una imagen (multipart, campo file; JPEG, PNG o WebP) al final de la galería', tag: 'Market', responseDto: ProductImageOutDto::class, responseStatusCode: 201)]
    public function upload(int $id, Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return $this->error('PRODUCT_IMAGE_REQUIRED', 'Falta el archivo de la imagen (campo "file").', Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (!$file->isValid()) {
            return $this->error('PRODUCT_IMAGE_INVALID', 'La imagen no se pudo recibir: ' . $file->getErrorMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->respond(
            fn () => ProductImageOutDto::from(($this->upload)($id, $file->getPathname(), $file->getClientOriginalName(), (int) $file->getSize()), $this->urls),
            Response::HTTP_CREATED,
        );
    }

    #[Route('/order', name: 'market_product_images_reorder', methods: ['PUT'])]
    #[DashboardEndpoint(summary: 'Reordenar la galería: todas las imágenes, una vez cada una', tag: 'Market', requestDto: ReorderProductImagesDto::class, responseDto: ProductImagesOutDto::class)]
    public function reorder(int $id, ReorderProductImagesDto $dto): JsonResponse
    {
        return $this->respond(fn () => $this->gallery(($this->reorder)($id, $dto->getIds() ?? [])));
    }

    #[Route('/{imageId}/reference', name: 'market_product_images_reference', methods: ['POST'], requirements: ['imageId' => '\d+'])]
    #[DashboardEndpoint(summary: 'Hacer de una imagen la referencia del producto (la de la tarjeta del catálogo)', tag: 'Market', responseDto: ProductImagesOutDto::class)]
    public function setReference(int $id, int $imageId): JsonResponse
    {
        return $this->respond(fn () => $this->gallery(($this->setReference)($id, $imageId)));
    }

    #[Route('/{imageId}', name: 'market_product_images_describe', methods: ['PUT'], requirements: ['imageId' => '\d+'])]
    #[DashboardEndpoint(summary: 'Describir una imagen: tipo y texto alternativo en español e inglés', tag: 'Market', requestDto: DescribeProductImageDto::class, responseDto: ProductImageOutDto::class)]
    public function describe(int $id, int $imageId, DescribeProductImageDto $dto): JsonResponse
    {
        return $this->respond(fn () => ProductImageOutDto::from(($this->describe)($id, $imageId, $dto->getKind(), $dto->getAltEs(), $dto->getAltEn()), $this->urls));
    }

    #[Route('/{imageId}', name: 'market_product_images_delete', methods: ['DELETE'], requirements: ['imageId' => '\d+'])]
    #[DashboardEndpoint(summary: 'Quitar una imagen de la galería y borrar sus archivos', tag: 'Market', responseStatusCode: 204)]
    public function delete(int $id, int $imageId): JsonResponse
    {
        return $this->respond(function () use ($id, $imageId) {
            ($this->delete)($id, $imageId);

            return null;
        }, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param list<ProductImage> $images
     */
    private function gallery(array $images): ProductImagesOutDto
    {
        return new ProductImagesOutDto(
            array_map(fn (ProductImage $image): ProductImageOutDto => ProductImageOutDto::from($image, $this->urls), $images),
            $this->settings->maxBytes(),
            $this->settings->maxPerProduct(),
        );
    }

    /**
     * @param callable(): (ProductImagesOutDto|ProductImageOutDto|null) $action
     */
    private function respond(callable $action, int $status = Response::HTTP_OK): JsonResponse
    {
        try {
            $result = $action();

            return $result === null ? new JsonResponse(null, $status) : new JsonResponse($result, $status);
        } catch (ProductNotFound $e) {
            return $this->error('PRODUCT_NOT_FOUND', $e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (ProductImageNotFound $e) {
            return $this->error('PRODUCT_IMAGE_NOT_FOUND', $e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (ProductGalleryFull $e) {
            return $this->error('PRODUCT_GALLERY_FULL', $e->getMessage(), Response::HTTP_CONFLICT);
        } catch (InvalidGalleryOrder $e) {
            return $this->error('INVALID_GALLERY_ORDER', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (InvalidProductImage $e) {
            return $this->error('PRODUCT_IMAGE_INVALID', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => ['message' => $message, 'code' => $code]], $status);
    }
}
