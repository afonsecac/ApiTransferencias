<?php

namespace App\Controller;

use App\Entity\Account;
use App\Entity\CommunicationClientPackage;
use App\Entity\User;
use App\Entity\CommunicationPrice;
use App\Entity\CommunicationPricePackage;
use App\Entity\CommunicationProduct;
use App\Entity\Environment;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class DashboardClientPackagesController extends AbstractController
{
    private const PRICE_SORTABLE = [
        'id' => 'pp.id',
        'price' => 'pp.price',
        'amount' => 'pp.amount',
        'name' => 'pp.name',
        'isActive' => 'pp.isActive',
    ];

    private const PACKAGE_SORTABLE = [
        'id' => 'cp.id',
        'name' => 'cp.name',
        'amount' => 'cp.amount',
        'activeStartAt' => 'cp.activeStartAt',
        'activeEndAt' => 'cp.activeEndAt',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {
    }

    // ═══════════════════════════════════════════
    //  PRICE PACKAGES
    // ═══════════════════════════════════════════

    #[Route('/client/prices', name: 'dashboard_client_prices_list', methods: ['GET'])]
    public function listPrices(Request $request): JsonResponse
    {
        $page = max(0, (int) $request->query->get('page', 0));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $orderBy = $request->query->get('orderBy', 'id DESC');

        $qb = $this->em->getRepository(CommunicationPricePackage::class)
            ->createQueryBuilder('pp')
            ->leftJoin('pp.tenant', 'a')
            ->leftJoin('a.client', 'c')
            ->leftJoin('pp.environment', 'e')
            ->leftJoin('pp.product', 'pr');

        $this->applyPriceFilters($qb, $request);

        $orderParts = explode(' ', $orderBy);
        $field = self::PRICE_SORTABLE[$orderParts[0]] ?? 'pp.id';
        $direction = strtoupper($orderParts[1] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $qb->orderBy($field, $direction);

        $results = $qb->setMaxResults($limit + 1)->setFirstResult($page * $limit)
            ->getQuery()->getResult();

        $hasNext = count($results) > $limit;
        if ($hasNext) {
            array_pop($results);
        }

        return $this->json([
            'limit' => $limit,
            'currentPage' => $page,
            'hasNext' => $hasNext,
            'hasPrevious' => $page > 0,
            'results' => array_map(fn($pp) => $this->serializePricePackage($pp), $results),
        ]);
    }

    #[Route('/client/prices/{id}', name: 'dashboard_client_prices_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function showPrice(int $id): JsonResponse
    {
        $pp = $this->em->getRepository(CommunicationPricePackage::class)->find($id);
        if ($pp === null) {
            return $this->json(['error' => ['message' => 'Price package not found']], Response::HTTP_NOT_FOUND);
        }
        return $this->json($this->serializePricePackage($pp));
    }

    #[Route('/client/prices', name: 'dashboard_client_prices_create', methods: ['POST'])]
    public function createPrice(Request $request): JsonResponse
    {
        $data = $request->request->all();
        $errors = [];
        if (empty($data['price'])) $errors[] = 'price is required';
        if (empty($data['priceCurrency'])) $errors[] = 'priceCurrency is required';
        if (empty($data['amount'])) $errors[] = 'amount is required';
        if (empty($data['currency'])) $errors[] = 'currency is required';
        if (empty($data['tenantId'])) $errors[] = 'tenantId is required';
        if (empty($data['productId'])) $errors[] = 'productId is required';
        if (!empty($errors)) {
            return $this->json(['error' => ['message' => 'Validation failed', 'details' => $errors]], Response::HTTP_BAD_REQUEST);
        }

        $account = $this->em->getRepository(Account::class)->find($data['tenantId']);
        $product = $this->em->getRepository(CommunicationProduct::class)->find($data['productId']);
        if ($account === null || $product === null) {
            return $this->json(['error' => ['message' => 'Account or product not found']], Response::HTTP_NOT_FOUND);
        }

        $priceUsed = null;
        if (!empty($data['priceUsedId'])) {
            $priceUsed = $this->em->getRepository(CommunicationPrice::class)->find($data['priceUsedId']);
        }

        $pp = new CommunicationPricePackage();
        $pp->setProduct($product);
        $pp->setTenant($account);
        $pp->setPrice((float) $data['price']);
        $pp->setPriceCurrency($data['priceCurrency']);
        $pp->setAmount((float) $data['amount']);
        $pp->setCurrency($data['currency']);
        $pp->setName(mb_substr($data['name'] ?? "Cubacel {$data['price']} {$data['priceCurrency']}", 0, 255));
        $pp->setIsActive($data['isActive'] ?? true);
        $pp->setActiveStartAt(new \DateTimeImmutable($data['activeStartAt'] ?? 'now'));
        if (!empty($data['activeEndAt'])) {
            $pp->setActiveEndAt(new \DateTimeImmutable($data['activeEndAt']));
        }
        if ($priceUsed !== null) {
            $pp->setPriceUsed($priceUsed);
        }
        if (!empty($data['environmentId'])) {
            $pp->setEnvironment($this->em->getRepository(Environment::class)->find($data['environmentId']));
        }
        if (!empty($data['description'])) {
            $pp->setDescription(mb_substr($data['description'], 0, 255));
        }

        $this->em->persist($pp);
        $this->em->flush();

        return $this->json($this->serializePricePackage($pp), Response::HTTP_CREATED);
    }

    #[Route('/client/prices/{id}', name: 'dashboard_client_prices_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updatePrice(int $id, Request $request): JsonResponse
    {
        $pp = $this->em->getRepository(CommunicationPricePackage::class)->find($id);
        if ($pp === null) {
            return $this->json(['error' => ['message' => 'Price package not found']], Response::HTTP_NOT_FOUND);
        }

        $data = $request->request->all();
        if (isset($data['price'])) $pp->setPrice((float) $data['price']);
        if (isset($data['priceCurrency'])) $pp->setPriceCurrency($data['priceCurrency']);
        if (isset($data['amount'])) $pp->setAmount((float) $data['amount']);
        if (isset($data['currency'])) $pp->setCurrency($data['currency']);
        if (isset($data['name'])) $pp->setName(mb_substr($data['name'], 0, 255));
        if (isset($data['description'])) $pp->setDescription(mb_substr($data['description'], 0, 255));
        if (isset($data['isActive'])) $pp->setIsActive((bool) $data['isActive']);
        if (isset($data['activeStartAt'])) $pp->setActiveStartAt(new \DateTimeImmutable($data['activeStartAt']));
        if (isset($data['activeEndAt'])) $pp->setActiveEndAt(new \DateTimeImmutable($data['activeEndAt']));

        $this->em->flush();
        return $this->json($this->serializePricePackage($pp));
    }

    #[Route('/client/prices/{id}/toggle', name: 'dashboard_client_prices_toggle', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function togglePrice(int $id): JsonResponse
    {
        $pp = $this->em->getRepository(CommunicationPricePackage::class)->find($id);
        if ($pp === null) {
            return $this->json(['error' => ['message' => 'Price package not found']], Response::HTTP_NOT_FOUND);
        }
        $pp->setIsActive(!$pp->isActive());
        $this->em->flush();
        return $this->json(['id' => $pp->getId(), 'isActive' => $pp->isActive()]);
    }

    #[Route('/client/prices/{id}', name: 'dashboard_client_prices_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deletePrice(int $id): JsonResponse
    {
        $pp = $this->em->getRepository(CommunicationPricePackage::class)->find($id);
        if ($pp === null) {
            return $this->json(['error' => ['message' => 'Price package not found']], Response::HTTP_NOT_FOUND);
        }
        $this->em->remove($pp);
        $this->em->flush();
        return $this->json(['deleted' => true]);
    }

    // ═══════════════════════════════════════════
    //  CLIENT PACKAGES
    // ═══════════════════════════════════════════

    #[Route('/client/packages', name: 'dashboard_client_packages_list', methods: ['GET'])]
    public function listPackages(Request $request): JsonResponse
    {
        $page = max(0, (int) $request->query->get('page', 0));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $orderBy = $request->query->get('orderBy', 'id DESC');

        $qb = $this->em->getRepository(CommunicationClientPackage::class)
            ->createQueryBuilder('cp')
            ->leftJoin('cp.tenant', 'a')
            ->leftJoin('a.client', 'c')
            ->leftJoin('a.environment', 'e');

        $this->applyPackageFilters($qb, $request);

        $orderParts = explode(' ', $orderBy);
        $field = self::PACKAGE_SORTABLE[$orderParts[0]] ?? 'cp.id';
        $direction = strtoupper($orderParts[1] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $qb->orderBy($field, $direction);

        $results = $qb->setMaxResults($limit + 1)->setFirstResult($page * $limit)
            ->getQuery()->getResult();

        $hasNext = count($results) > $limit;
        if ($hasNext) {
            array_pop($results);
        }

        return $this->json([
            'limit' => $limit,
            'currentPage' => $page,
            'hasNext' => $hasNext,
            'hasPrevious' => $page > 0,
            'results' => array_map(fn($cp) => $this->serializeClientPackage($cp), $results),
        ]);
    }

    #[Route('/client/packages/{id}', name: 'dashboard_client_packages_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function showPackage(int $id): JsonResponse
    {
        $cp = $this->em->getRepository(CommunicationClientPackage::class)->find($id);
        if ($cp === null) {
            return $this->json(['error' => ['message' => 'Package not found']], Response::HTTP_NOT_FOUND);
        }
        return $this->json($this->serializeClientPackageDetail($cp));
    }

    #[Route('/client/packages/{id}', name: 'dashboard_client_packages_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updatePackage(int $id, Request $request): JsonResponse
    {
        $cp = $this->em->getRepository(CommunicationClientPackage::class)->find($id);
        if ($cp === null) {
            return $this->json(['error' => ['message' => 'Package not found']], Response::HTTP_NOT_FOUND);
        }

        $data = $request->request->all();
        if (isset($data['name'])) $cp->setName(mb_substr($data['name'], 0, 255));
        if (isset($data['description'])) $cp->setDescription(mb_substr($data['description'], 0, 255));
        if (isset($data['amount'])) $cp->setAmount((float) $data['amount']);
        if (isset($data['currency'])) $cp->setCurrency($data['currency']);
        if (isset($data['activeStartAt'])) $cp->setActiveStartAt(new \DateTimeImmutable($data['activeStartAt']));
        if (isset($data['activeEndAt'])) $cp->setActiveEndAt(new \DateTimeImmutable($data['activeEndAt']));
        if (isset($data['knowMore'])) $cp->setKnowMore(mb_substr($data['knowMore'], 0, 500));
        if (isset($data['benefits'])) $cp->setBenefits($data['benefits']);
        if (isset($data['tags'])) $cp->setTags($data['tags']);
        if (isset($data['service'])) $cp->setService($data['service']);
        if (isset($data['destination'])) $cp->setDestination($data['destination']);
        if (isset($data['validity'])) $cp->setValidity($data['validity']);

        $this->em->flush();
        return $this->json($this->serializeClientPackageDetail($cp));
    }

    #[Route('/client/packages/{id}', name: 'dashboard_client_packages_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deletePackage(int $id): JsonResponse
    {
        $cp = $this->em->getRepository(CommunicationClientPackage::class)->find($id);
        if ($cp === null) {
            return $this->json(['error' => ['message' => 'Package not found']], Response::HTTP_NOT_FOUND);
        }
        $this->em->remove($cp);
        $this->em->flush();
        return $this->json(['deleted' => true]);
    }

    // ═══════════════════════════════════════════
    //  HELPERS
    // ═══════════════════════════════════════════

    private function applyPriceFilters(QueryBuilder $qb, Request $request): void
    {
        $this->applyClientFilter($qb, 'c', $request);
        $this->applyActiveFilter($qb, 'pp');

        if ($envType = $request->query->get('environmentType')) {
            $qb->andWhere('e.type = :envType')->setParameter('envType', $envType);
        }
        if ($productId = $request->query->get('productId')) {
            $qb->andWhere('pr.id = :productId')->setParameter('productId', $productId);
        }
        if ($search = $request->query->get('search')) {
            $qb->andWhere('pp.name LIKE :s')->setParameter('s', "%{$search}%");
        }
    }

    private function applyPackageFilters(QueryBuilder $qb, Request $request): void
    {
        $this->applyClientFilter($qb, 'c', $request);
        $this->applyActivePackageFilter($qb, 'cp');

        if ($envType = $request->query->get('environmentType')) {
            $qb->andWhere('e.type = :envType')->setParameter('envType', $envType);
        }
        if ($tenantId = $request->query->get('tenantId')) {
            $qb->andWhere('a.id = :tenantId')->setParameter('tenantId', $tenantId);
        }
        if ($search = $request->query->get('search')) {
            $qb->andWhere('cp.name LIKE :s')->setParameter('s', "%{$search}%");
        }
    }

    private function applyClientFilter(QueryBuilder $qb, string $clientAlias, Request $request): void
    {
        $clientId = $request->query->get('clientId');

        /** @var User|null $user */
        $user = $this->security->getUser();

        if ($this->isGranted('ROLE_ADMIN')) {
            if ($clientId) {
                $qb->andWhere("{$clientAlias}.id = :clientId")
                    ->setParameter('clientId', $clientId);
            }
        } elseif ($user instanceof User && $user->getCompany() !== null) {
            $qb->andWhere("{$clientAlias}.id = :authClientId")
                ->setParameter('authClientId', $user->getCompany()->getId());
        }
    }

    private function applyActiveFilter(QueryBuilder $qb, string $alias): void
    {
        $qb->andWhere("{$alias}.isActive = :isActive")->setParameter('isActive', true);
        $qb->andWhere("{$alias}.activeStartAt <= :now")->setParameter('now', new \DateTimeImmutable());
        $qb->andWhere("({$alias}.activeEndAt IS NULL OR {$alias}.activeEndAt >= :nowEnd)")
            ->setParameter('nowEnd', new \DateTimeImmutable());
    }

    private function applyActivePackageFilter(QueryBuilder $qb, string $alias): void
    {
        $qb->andWhere("{$alias}.activeStartAt <= :now")->setParameter('now', new \DateTimeImmutable());
        $qb->andWhere("({$alias}.activeEndAt IS NULL OR {$alias}.activeEndAt >= :nowEnd)")
            ->setParameter('nowEnd', new \DateTimeImmutable());
    }

    private function serializePricePackage(CommunicationPricePackage $pp): array
    {
        return [
            'id' => $pp->getId(),
            'name' => $pp->getName(),
            'description' => $pp->getDescription(),
            'price' => $pp->getPrice(),
            'priceCurrency' => $pp->getPriceCurrency(),
            'amount' => $pp->getAmount(),
            'currency' => $pp->getCurrency(),
            'isActive' => $pp->isActive(),
            'activeStartAt' => $pp->getActiveStartAt()?->format('c'),
            'activeEndAt' => $pp->getActiveEndAt()?->format('c'),
            'tenant' => [
                'id' => $pp->getTenant()?->getId(),
                'clientName' => $pp->getTenant()?->getClient()?->getCompanyName(),
            ],
            'environment' => $pp->getEnvironment() ? [
                'id' => $pp->getEnvironment()->getId(),
                'type' => $pp->getEnvironment()->getType(),
            ] : null,
            'product' => $pp->getProduct() ? [
                'id' => $pp->getProduct()->getId(),
                'description' => $pp->getProduct()->getDescription(),
            ] : null,
        ];
    }

    private function serializeClientPackage(CommunicationClientPackage $cp): array
    {
        return [
            'id' => $cp->getId(),
            'name' => $cp->getName(),
            'description' => $cp->getDescription(),
            'amount' => $cp->getAmount(),
            'currency' => $cp->getCurrency(),
            'activeStartAt' => $cp->getActiveStartAt()?->format('c'),
            'activeEndAt' => $cp->getActiveEndAt()?->format('c'),
            'tenant' => [
                'id' => $cp->getTenant()?->getId(),
                'clientName' => $cp->getTenant()?->getClient()?->getCompanyName(),
            ],
        ];
    }

    private function serializeClientPackageDetail(CommunicationClientPackage $cp): array
    {
        $data = $this->serializeClientPackage($cp);
        $data['benefits'] = $cp->getBenefits();
        $data['tags'] = $cp->getTags();
        $data['service'] = $cp->getService();
        $data['destination'] = $cp->getDestination();
        $data['validity'] = $cp->getValidity();
        $data['knowMore'] = $cp->getKnowMore();
        $data['pricePackage'] = $cp->getPriceClientPackage() ? $this->serializePricePackage($cp->getPriceClientPackage()) : null;
        return $data;
    }
}
