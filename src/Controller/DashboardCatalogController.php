<?php

namespace App\Controller;

use App\DTO\SyncProductsDto;
use App\DTO\UpdateProductDto;
use App\DTO\Out\PaginatedListOutDto;
use App\DTO\Out\SyncProductsOutDto;
use App\DTO\Out\ToggleOutDto;
use App\Entity\Account;
use App\Entity\Client;
use App\Entity\CommunicationProduct;
use App\Entity\Environment;
use App\Entity\User;
use App\Exception\MyCurrentException;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Service\CommunicationProductService;
use App\Service\TakeProductService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
class DashboardCatalogController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NormalizerInterface $serializer,
        private readonly TakeProductService $takeProductService,
        private readonly CommunicationProductService $productService,
    ) {
    }

    #[Route('/environments', name: 'dashboard_environments_list', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar entornos', tag: 'Catalog')]
    public function environments(): JsonResponse
    {
        $environments = $this->em->getRepository(Environment::class)->findBy(
            ['isActive' => true],
            ['type' => 'ASC', 'providerName' => 'ASC']
        );

        return $this->json(
            $this->serializer->normalize($environments, 'json', [
                'groups' => ['env:list'],
            ])
        );
    }

    #[Route('/clients', name: 'dashboard_clients_list', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar clientes', tag: 'Catalog')]
    public function clients(Request $request): JsonResponse
    {
        $envType = $request->query->get('type');

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => ['message' => 'Unauthorized']], Response::HTTP_UNAUTHORIZED);
        }

        if ($this->isGranted('ROLE_ADMIN')) {
            $clients = $this->getClientsByEnvironmentType($envType);
        } else {
            $ownClient = $user->getCompany();
            if ($ownClient === null || !$ownClient->isActive()) {
                $clients = [];
            } else {
                if ($envType !== null) {
                    $hasAccount = $this->clientHasAccountInEnvType($ownClient, $envType);
                    $clients = $hasAccount ? [$ownClient] : [];
                } else {
                    $clients = [$ownClient];
                }
            }
        }

        return $this->json(
            $this->serializer->normalize($clients, 'json', [
                'groups' => ['client:list'],
                AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            ])
        );
    }

    private const PRODUCT_SORTABLE_FIELDS = [
        'id' => 'p.id',
        'packageId' => 'p.packageId',
        'description' => 'p.description',
        'packageType' => 'p.packageType',
        'productType' => 'p.productType',
        'price' => 'p.price',
        'enabled' => 'p.enabled',
        'initialDate' => 'p.initialDate',
        'endDateAt' => 'p.endDateAt',
        'environment' => 'e.type',
    ];

    #[Route('/products', name: 'dashboard_products_list', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar productos', tag: 'Catalog', responseDto: PaginatedListOutDto::class)]
    #[IsGranted('ROLE_ADMIN')]
    public function products(Request $request): JsonResponse
    {
        $page = max(0, (int) $request->query->get('page', 0));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $envType = $request->query->get('type');
        $search = $request->query->get('search');
        $orderBy = $request->query->get('orderBy', 'packageId DESC');

        $qb = $this->em->getRepository(CommunicationProduct::class)
            ->createQueryBuilder('p')
            ->leftJoin('p.environment', 'e')
            ->where('p.enabled = :enabled')
            ->setParameter('enabled', true);

        if ($envType !== null) {
            $qb->andWhere('e.type = :type')->setParameter('type', $envType);
        }
        if (!empty($search)) {
            $qb->andWhere('p.description LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $orderParts = explode(' ', $orderBy);
        $field = self::PRODUCT_SORTABLE_FIELDS[$orderParts[0]] ?? 'p.packageId';
        $direction = strtoupper($orderParts[1] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $qb->orderBy($field, $direction);

        $results = $qb->setMaxResults($limit + 1)
            ->setFirstResult($page * $limit)
            ->getQuery()
            ->getResult();

        $hasNext = count($results) > $limit;
        if ($hasNext) {
            array_pop($results);
        }

        return $this->json([
            'limit' => $limit,
            'currentPage' => $page,
            'hasNext' => $hasNext,
            'hasPrevious' => $page > 0,
            'results' => $this->serializer->normalize($results, 'json', [
                'groups' => ['product:read'],
                AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            ]),
        ]);
    }

    #[Route('/products/{id}', name: 'dashboard_products_update', methods: ['PUT'])]
    #[DashboardEndpoint(summary: 'Actualizar producto', tag: 'Catalog', requestDto: UpdateProductDto::class)]
    #[IsGranted('ROLE_ADMIN')]
    public function updateProduct(int $id, UpdateProductDto $dto): JsonResponse
    {
        $product = $this->em->getRepository(CommunicationProduct::class)->find($id);
        if ($product === null) {
            return $this->json(['error' => ['message' => 'Product not found']], Response::HTTP_NOT_FOUND);
        }

        try {
            $product = $this->productService->updateProduct($product, $dto);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json(
            $this->serializer->normalize($product, 'json', [
                'groups' => ['product:read'],
            ])
        );
    }

    #[Route('/products/{id}/toggle', name: 'dashboard_products_toggle', methods: ['PATCH'])]
    #[DashboardEndpoint(summary: 'Activar/desactivar producto', tag: 'Catalog', responseDto: ToggleOutDto::class)]
    #[IsGranted('ROLE_ADMIN')]
    public function toggleProduct(int $id): JsonResponse
    {
        $product = $this->em->getRepository(CommunicationProduct::class)->find($id);
        if ($product === null) {
            return $this->json(['error' => ['message' => 'Product not found']], Response::HTTP_NOT_FOUND);
        }

        $product->setEnabled(!$product->isEnabled());
        $this->em->flush();

        return $this->json([
            'id' => $product->getId(),
            'enabled' => $product->isEnabled(),
        ]);
    }

    #[Route('/products/sync', name: 'dashboard_products_sync', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Sincronizar productos con proveedor', tag: 'Catalog', requestDto: SyncProductsDto::class, responseDto: SyncProductsOutDto::class)]
    #[IsGranted('ROLE_ADMIN')]
    public function syncProducts(SyncProductsDto $dto): JsonResponse
    {
        try {
            $result = $this->takeProductService->takeProduct($dto->getEnvironmentType());

            return $this->json([
                'synced' => true,
                'items' => $result['items'],
                'environmentType' => $dto->getEnvironmentType(),
            ]);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => ['message' => $e->getMessage()]],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    private function getClientsByEnvironmentType(?string $envType): array
    {
        if ($envType === null) {
            return $this->em->getRepository(Client::class)->findBy(
                ['isActive' => true],
                ['companyName' => 'ASC']
            );
        }

        $accounts = $this->em->getRepository(Account::class)
            ->createQueryBuilder('a')
            ->leftJoin('a.environment', 'e')
            ->leftJoin('a.client', 'c')
            ->where('a.isActive = :active')
            ->andWhere('c.isActive = :active')
            ->andWhere('e.type = :type')
            ->setParameter('active', true)
            ->setParameter('type', $envType)
            ->getQuery()
            ->getResult();

        $clients = [];
        $seen = [];
        foreach ($accounts as $account) {
            $client = $account->getClient();
            if ($client !== null && !isset($seen[$client->getId()])) {
                $clients[] = $client;
                $seen[$client->getId()] = true;
            }
        }

        usort($clients, fn(Client $a, Client $b) => strcmp($a->getCompanyName(), $b->getCompanyName()));

        return $clients;
    }

    private function clientHasAccountInEnvType(Client $client, string $envType): bool
    {
        $account = $this->em->getRepository(Account::class)
            ->createQueryBuilder('a')
            ->leftJoin('a.environment', 'e')
            ->where('a.client = :client')
            ->andWhere('a.isActive = :active')
            ->andWhere('e.type = :type')
            ->setParameter('client', $client)
            ->setParameter('active', true)
            ->setParameter('type', $envType)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $account !== null;
    }
}
