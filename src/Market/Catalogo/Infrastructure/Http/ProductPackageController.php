<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

use App\Market\Catalogo\Application\GetProductPackage;
use App\Market\Catalogo\Application\PublishProductPackage;
use App\Market\Catalogo\Application\RetireProductPackage;
use App\Market\Catalogo\Domain\InvalidProduct;
use App\Market\Catalogo\Domain\PackageOffer;
use App\Market\Catalogo\Domain\ProductNotFound;
use App\OpenApi\Attribute\DashboardEndpoint;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * El paquete con el que un producto del mercado se ofrece al comprador (COMR-275): un producto suelto también es un paquete.
 * El controlador solo traduce HTTP; la lógica está en los casos de uso.
 */
#[AsController]
#[Route('/market/products/{id}/package', requirements: ['id' => '\d+'])]
#[IsGranted('ROLE_API_ADMIN')]
final class ProductPackageController
{
    public function __construct(
        private readonly GetProductPackage $getPackage,
        private readonly PublishProductPackage $publishPackage,
        private readonly RetireProductPackage $retirePackage,
    ) {
    }

    #[Route('', name: 'market_product_package_get', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'El paquete con el que se ofrece el producto al comprador (404 si no se ha ofrecido)', tag: 'Market', responseDto: ProductPackageOutDto::class)]
    public function get(int $id): JsonResponse
    {
        try {
            $offer = ($this->getPackage)($id);
        } catch (ProductNotFound $e) {
            return $this->error('PRODUCT_NOT_FOUND', $e->getMessage(), Response::HTTP_NOT_FOUND);
        }

        return $offer === null ? $this->notOffered() : new JsonResponse(ProductPackageOutDto::from($offer));
    }

    #[Route('', name: 'market_product_package_publish', methods: ['PUT'])]
    #[DashboardEndpoint(summary: 'Ofrecer el producto publicado al comprador como paquete, con un precio mínimo (provisional hasta el hito 3); si ya tenía paquete, cambia el precio y lo reactiva', tag: 'Market', requestDto: PublishProductPackageDto::class, responseDto: ProductPackageOutDto::class)]
    public function publish(int $id, PublishProductPackageDto $dto): JsonResponse
    {
        try {
            $offer = ($this->publishPackage)($id, (string) $dto->getPrice(), (string) $dto->getCurrency());
        } catch (ProductNotFound $e) {
            return $this->error('PRODUCT_NOT_FOUND', $e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (InvalidProduct $e) {
            return $this->error('INVALID_PRODUCT', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(ProductPackageOutDto::from($offer));
    }

    #[Route('', name: 'market_product_package_retire', methods: ['DELETE'])]
    #[DashboardEndpoint(summary: 'Dejar de ofrecer el paquete del producto al comprador, sin borrarlo', tag: 'Market', responseDto: ProductPackageOutDto::class)]
    public function retire(int $id): JsonResponse
    {
        try {
            $retired = ($this->retirePackage)($id);
            $offer = $retired ? ($this->getPackage)($id) : null;
        } catch (ProductNotFound $e) {
            return $this->error('PRODUCT_NOT_FOUND', $e->getMessage(), Response::HTTP_NOT_FOUND);
        }

        return $offer instanceof PackageOffer ? new JsonResponse(ProductPackageOutDto::from($offer)) : $this->notOffered();
    }

    private function notOffered(): JsonResponse
    {
        return $this->error('PRODUCT_PACKAGE_NOT_FOUND', 'El producto no se ha ofrecido como paquete.', Response::HTTP_NOT_FOUND);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => ['message' => $message, 'code' => $code]], $status);
    }
}
