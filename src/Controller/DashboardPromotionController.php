<?php

namespace App\Controller;

use App\DTO\Out\DeletedOutDto;
use App\DTO\Out\PaginatedListOutDto;
use App\DTO\UpsertPromotionDto;
use App\Entity\CommunicationProduct;
use App\Entity\CommunicationPromotions;
use App\Entity\Environment;
use App\OpenApi\Attribute\DashboardEndpoint;
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
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/promotions')]
class DashboardPromotionController extends AbstractController
{
    public function __construct(
        private readonly CommunicationPromotionsRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly NormalizerInterface $serializer,
        private readonly CommunicationPromotionService $promotionService,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'dashboard_promotions_list', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar promociones', tag: 'Promotions', responseDto: PaginatedListOutDto::class)]
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
    #[DashboardEndpoint(summary: 'Obtener detalle de promoción', tag: 'Promotions')]
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
    #[DashboardEndpoint(summary: 'Crear promoción', tag: 'Promotions', requestDto: UpsertPromotionDto::class, responseStatusCode: 201)]
    public function create(UpsertPromotionDto $dto): JsonResponse
    {
        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            $details = [];
            foreach ($violations as $v) {
                $details[] = $v->getPropertyPath() . ': ' . $v->getMessage();
            }
            return $this->json(['error' => ['message' => 'Validation failed', 'details' => $details]], Response::HTTP_BAD_REQUEST);
        }

        $promotion = new CommunicationPromotions();
        $this->hydratePromotion($promotion, $dto);

        $this->em->persist($promotion);
        $this->em->flush();

        $packagesCreated = $this->promotionService->createPackagesForPromotion($promotion, [
            'currency'   => $dto->getCurrency(),
            'amountFrom' => $dto->getAmountFrom(),
            'amountTo'   => $dto->getAmountTo(),
            'amountStep' => $dto->getAmountStep(),
            'clients'    => $dto->getClients() ?? [],
        ]);

        $result = $this->normalizeDetail($promotion);
        $result['packagesCreated'] = $packagesCreated;

        return $this->json($result, Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'dashboard_promotions_update', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    #[DashboardEndpoint(summary: 'Actualizar promoción', tag: 'Promotions', requestDto: UpsertPromotionDto::class)]
    public function update(int $id, UpsertPromotionDto $dto): JsonResponse
    {
        $promotion = $this->repository->find($id);
        if ($promotion === null) {
            return $this->json(['error' => ['message' => 'Promotion not found']], Response::HTTP_NOT_FOUND);
        }

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            $details = [];
            foreach ($violations as $v) {
                $details[] = $v->getPropertyPath() . ': ' . $v->getMessage();
            }
            return $this->json(['error' => ['message' => 'Validation failed', 'details' => $details]], Response::HTTP_BAD_REQUEST);
        }

        $this->hydratePromotion($promotion, $dto);
        $this->em->flush();

        return $this->json($this->normalizeDetail($promotion));
    }

    #[Route('/{id}', name: 'dashboard_promotions_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    #[DashboardEndpoint(summary: 'Eliminar promoción', tag: 'Promotions', responseDto: DeletedOutDto::class)]
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

    private function hydratePromotion(CommunicationPromotions $promotion, UpsertPromotionDto $dto): void
    {
        $promotion->setName($dto->getName());
        $promotion->setDescription($dto->getDescription());
        $promotion->setTerms($dto->getTerms() ?? []);
        $promotion->setStartAt(new \DateTimeImmutable($dto->getStartAt()));
        $promotion->setEndAt(new \DateTimeImmutable($dto->getEndAt()));

        if ($dto->getInfoDescription() !== null) {
            $promotion->setInfoDescription($dto->getInfoDescription());
        }
        if ($dto->getKnowMore() !== null) {
            $promotion->setKnowMore($dto->getKnowMore());
        }
        if ($dto->getValidityInfo() !== null) {
            $promotion->setValidityInfo($dto->getValidityInfo());
        }

        $env = $dto->getEnvironment();
        if (isset($env['id'])) {
            $environment = $this->em->getRepository(Environment::class)->find($env['id']);
            if ($environment !== null) {
                $promotion->setEnvironment($environment);
            }
        }

        if ($dto->getProductId() !== null) {
            $product = $this->em->getRepository(CommunicationProduct::class)->find($dto->getProductId());
            if ($product !== null) {
                $promotion->setProduct($product);
                if ($promotion->getEnvironment() === null) {
                    $promotion->setEnvironment($product->getEnvironment());
                }
            }
        }
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
