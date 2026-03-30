<?php

namespace App\Controller;

use App\Entity\CommunicationProduct;
use App\Entity\CommunicationPromotions;
use App\Entity\Environment;
use App\Repository\CommunicationPromotionsRepository;
use App\Service\CommunicationPromotionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/promotions')]
class DashboardPromotionController extends AbstractController
{
    public function __construct(
        private readonly CommunicationPromotionsRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly NormalizerInterface $serializer,
        private readonly CommunicationPromotionService $promotionService,
    ) {
    }

    #[Route('', name: 'dashboard_promotions_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(0, (int) $request->query->get('page', 0));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $orderBy = $request->query->get('orderBy', 'id DESC');
        $filters = [
            'search' => $request->query->get('search'),
            'environmentId' => $request->query->get('environmentId'),
            'active' => $request->query->get('active'),
        ];

        $result = $this->repository->findAllPaginated($page, $limit, $filters, $orderBy);

        $result['results'] = $this->serializer->normalize(
            $result['results'],
            'json',
            [
                'groups' => ['promotion:list'],
                AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            ]
        );

        return $this->json($result);
    }

    #[Route('/{id}', name: 'dashboard_promotions_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $promotion = $this->repository->find($id);
        if ($promotion === null) {
            return $this->json(['error' => ['message' => 'Promotion not found']], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->normalizeDetail($promotion));
    }

    #[Route('', name: 'dashboard_promotions_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): JsonResponse
    {
        $data = $request->request->all();

        $errors = $this->validatePromotionData($data);
        if (!empty($errors)) {
            return $this->json(['error' => ['message' => 'Validation failed', 'details' => $errors]], Response::HTTP_BAD_REQUEST);
        }

        $promotion = new CommunicationPromotions();
        $this->hydratePromotion($promotion, $data);

        $this->em->persist($promotion);
        $this->em->flush();

        // Crear paquetes para los clientes
        $packagesCreated = $this->promotionService->createPackagesForPromotion($promotion, [
            'currency' => $data['currency'],
            'amountFrom' => $data['amountFrom'],
            'amountTo' => $data['amountTo'],
            'amountStep' => $data['amountStep'],
            'clients' => $data['clients'] ?? [],
        ]);

        $result = $this->normalizeDetail($promotion);
        $result['packagesCreated'] = $packagesCreated;

        return $this->json($result, Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'dashboard_promotions_update', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(int $id, Request $request): JsonResponse
    {
        $promotion = $this->repository->find($id);
        if ($promotion === null) {
            return $this->json(['error' => ['message' => 'Promotion not found']], Response::HTTP_NOT_FOUND);
        }

        $data = $request->request->all();

        $errors = $this->validatePromotionData($data);
        if (!empty($errors)) {
            return $this->json(['error' => ['message' => 'Validation failed', 'details' => $errors]], Response::HTTP_BAD_REQUEST);
        }

        $this->hydratePromotion($promotion, $data);
        $this->em->flush();

        return $this->json($this->normalizeDetail($promotion));
    }

    #[Route('/{id}', name: 'dashboard_promotions_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(int $id): JsonResponse
    {
        $promotion = $this->repository->find($id);
        if ($promotion === null) {
            return $this->json(['error' => ['message' => 'Promotion not found']], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($promotion);
        $this->em->flush();

        return $this->json(['deleted' => true]);
    }

    private function hydratePromotion(CommunicationPromotions $promotion, array $data): void
    {
        $promotion->setName(mb_substr($data['name'], 0, 255));
        $promotion->setDescription(mb_substr($data['description'], 0, 255));
        $promotion->setTerms($data['terms'] ?? []);
        $promotion->setStartAt(new \DateTimeImmutable($data['startAt']));
        $promotion->setEndAt(new \DateTimeImmutable($data['endAt']));

        if (isset($data['infoDescription'])) {
            $promotion->setInfoDescription($data['infoDescription']);
        }
        if (isset($data['knowMore'])) {
            $promotion->setKnowMore(mb_substr($data['knowMore'], 0, 500));
        }
        if (isset($data['validityInfo'])) {
            $promotion->setValidityInfo($data['validityInfo']);
        }

        // Environment
        if (isset($data['environment']['id'])) {
            $environment = $this->em->getRepository(Environment::class)->find($data['environment']['id']);
            if ($environment !== null) {
                $promotion->setEnvironment($environment);
            }
        }

        // Product (la relación ManyToOne obligatoria)
        if (isset($data['productId'])) {
            $product = $this->em->getRepository(CommunicationProduct::class)->find($data['productId']);
            if ($product !== null) {
                $promotion->setProduct($product);
                if ($promotion->getEnvironment() === null) {
                    $promotion->setEnvironment($product->getEnvironment());
                }
            }
        }
    }

    private function validatePromotionData(array $data): array
    {
        $errors = [];
        if (empty($data['name'])) {
            $errors[] = 'name is required';
        }
        if (empty($data['description'])) {
            $errors[] = 'description is required';
        }
        if (empty($data['startAt'])) {
            $errors[] = 'startAt is required';
        }
        if (empty($data['endAt'])) {
            $errors[] = 'endAt is required';
        }
        if (empty($data['currency'])) {
            $errors[] = 'currency is required';
        }
        if (!isset($data['amountFrom']) || $data['amountFrom'] <= 0) {
            $errors[] = 'amountFrom is required and must be greater than 0';
        }
        if (!isset($data['amountTo']) || $data['amountTo'] <= 0) {
            $errors[] = 'amountTo is required and must be greater than 0';
        }
        if (!isset($data['amountStep']) || $data['amountStep'] <= 0) {
            $errors[] = 'amountStep is required and must be greater than 0';
        }
        if (!empty($data['amountFrom']) && !empty($data['amountTo']) && $data['amountFrom'] > $data['amountTo']) {
            $errors[] = 'amountFrom must be less than or equal to amountTo';
        }
        if (empty($data['productId'])) {
            $errors[] = 'productId is required';
        }
        if (empty($data['environment']['id'])) {
            $errors[] = 'environment.id is required';
        }
        return $errors;
    }

    private function normalizeDetail(CommunicationPromotions $promotion): array
    {
        return $this->serializer->normalize(
            $promotion,
            'json',
            [
                'groups' => ['promotion:detail'],
                AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            ]
        );
    }
}
