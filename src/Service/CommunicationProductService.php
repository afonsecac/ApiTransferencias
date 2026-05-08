<?php

namespace App\Service;

use App\DTO\CreateCommunicationProductDto;
use App\DTO\UpdateProductDto;
use App\Entity\CommunicationProduct;
use App\Entity\Environment;
use App\Exception\MyCurrentException;
use App\Util\IPaginationResponse;

class CommunicationProductService extends CommonService
{
    public function getProducts(int $page = 0, int $limit = 10, string $env = 'TEST', ?string $query = null): IPaginationResponse
    {
        /** @var \App\Repository\CommunicationProductRepository $productRepo */
        $productRepo = $this->em->getRepository(CommunicationProduct::class);
        return $productRepo->getProducts($page, $limit, $env, $query);
    }

    public function createProduct(CreateCommunicationProductDto $dto): CommunicationProduct
    {
        $env = $this->assertEnvironment((int) $dto->getEnvironmentId());
        $this->assertNoDuplicate($env, (int) $dto->getPackageId());

        $start = $dto->getInitialDate() !== null ? new \DateTimeImmutable($dto->getInitialDate()) : null;
        $end   = $dto->getEndDateAt()   !== null ? new \DateTimeImmutable($dto->getEndDateAt())   : null;
        $this->assertDateRange($start, $end);

        $product = new CommunicationProduct();
        $product->setPackageId((int) $dto->getPackageId());
        $product->setPackageType((string) $dto->getPackageType());
        $product->setPrice((float) $dto->getPrice());
        $product->setEnabled((bool) $dto->getEnabled());
        $product->setEnvironment($env);
        if ($dto->getProductType() !== null)  $product->setProductType($dto->getProductType());
        if ($dto->getDescription() !== null)  $product->setDescription($dto->getDescription());
        if ($start !== null)                  $product->setInitialDate($start);
        if ($end !== null)                    $product->setEndDateAt($end);

        $this->em->persist($product);
        $this->em->flush();

        return $product;
    }

    public function updateProduct(CommunicationProduct $product, UpdateProductDto $dto): CommunicationProduct
    {
        if ($dto->getDescription() !== null) {
            $product->setDescription(mb_substr($dto->getDescription(), 0, 255));
        }
        if ($dto->getPackageType() !== null) {
            $product->setPackageType($dto->getPackageType());
        }
        if ($dto->getProductType() !== null) {
            $product->setProductType($dto->getProductType());
        }
        if ($dto->getPrice() !== null) {
            $product->setPrice($dto->getPrice());
        }
        if ($dto->getEnabled() !== null) {
            $product->setEnabled($dto->getEnabled());
        }

        $newPackageId     = $dto->getPackageId();
        $newEnvironmentId = $dto->getEnvironmentId();

        $envChanged     = $newEnvironmentId !== null && $newEnvironmentId !== $product->getEnvironment()?->getId();
        $packageChanged = $newPackageId !== null && $newPackageId !== $product->getPackageId();

        if ($envChanged || $packageChanged) {
            $env       = $newEnvironmentId !== null ? $this->assertEnvironment($newEnvironmentId) : $product->getEnvironment();
            $packageId = $newPackageId ?? $product->getPackageId();
            $this->assertNoDuplicate($env, (int) $packageId, $product->getId());
            if ($newEnvironmentId !== null) $product->setEnvironment($env);
            if ($newPackageId !== null)     $product->setPackageId($newPackageId);
        }

        if ($dto->getInitialDate() !== null) {
            $start = new \DateTimeImmutable($dto->getInitialDate());
            $this->assertDateRange($start, $product->getEndDateAt());
            $product->setInitialDate($start);
        }
        if ($dto->getEndDateAt() !== null) {
            $end = new \DateTimeImmutable($dto->getEndDateAt());
            $this->assertDateRange($product->getInitialDate(), $end);
            $product->setEndDateAt($end);
        }

        $this->em->flush();

        return $product;
    }

    private function assertEnvironment(int $envId): Environment
    {
        $env = $this->em->getRepository(Environment::class)->find($envId);
        if ($env === null) {
            throw new MyCurrentException('ENVIRONMENT_NOT_FOUND', 'Environment not found', 404);
        }
        return $env;
    }

    private function assertNoDuplicate(Environment $env, int $packageId, ?int $excludeId = null): void
    {
        $existing = $this->em->getRepository(CommunicationProduct::class)
            ->findOneBy(['packageId' => $packageId, 'environment' => $env]);

        if ($existing !== null && $existing->getId() !== $excludeId) {
            throw new MyCurrentException('DUPLICATE_PRODUCT', 'A product with this packageId already exists in the given environment', 409);
        }
    }

    private function assertDateRange(?\DateTimeImmutable $start, ?\DateTimeImmutable $end): void
    {
        if ($start !== null && $end !== null && $end < $start) {
            throw new MyCurrentException('INVALID_DATE_RANGE', 'endDateAt must be equal to or after initialDate', 422);
        }
    }
}
