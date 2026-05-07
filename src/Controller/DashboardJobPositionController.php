<?php

namespace App\Controller;

use App\DTO\CreateJobPositionDto;
use App\DTO\UpdateJobPositionDto;
use App\DTO\Out\JobPositionOutDto;
use App\DTO\Out\PaginatedListOutDto;
use App\DTO\Out\ToggleOutDto;
use App\Entity\JobPosition;
use App\Enums\JobPositionAreaEnum;
use App\Exception\MyCurrentException;
use App\OpenApi\Attribute\DashboardEndpoint;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/job-positions')]
class DashboardJobPositionController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'dashboard_job_positions_list', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar puestos de trabajo', tag: 'JobPositions', responseDto: PaginatedListOutDto::class, itemDto: JobPositionOutDto::class)]
    public function list(Request $request): JsonResponse
    {
        $qb = $this->em->getRepository(JobPosition::class)
            ->createQueryBuilder('jp')
            ->orderBy('jp.area', 'ASC')
            ->addOrderBy('jp.code', 'ASC');

        $area = $request->query->get('area');
        if (!empty($area)) {
            $qb->andWhere('jp.area = :area')->setParameter('area', $area);
        }

        $onlyActive = $request->query->get('onlyActive');
        if ($onlyActive !== null && $onlyActive !== 'false') {
            $qb->andWhere('jp.isActive = true');
        }

        $results = $qb->getQuery()->getResult();

        return $this->json(array_map(fn(JobPosition $jp) => $this->serialize($jp), $results));
    }

    #[Route('/{id}', name: 'dashboard_job_positions_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Detalle de puesto de trabajo', tag: 'JobPositions', responseDto: JobPositionOutDto::class)]
    public function show(int $id): JsonResponse
    {
        $jobPosition = $this->em->getRepository(JobPosition::class)->find($id);
        if ($jobPosition === null) {
            return $this->json(['error' => ['message' => 'Job position not found']], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serialize($jobPosition));
    }

    #[Route('', name: 'dashboard_job_positions_create', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Crear puesto de trabajo', tag: 'JobPositions', requestDto: CreateJobPositionDto::class, responseDto: JobPositionOutDto::class, responseStatusCode: 201)]
    #[IsGranted('ROLE_ADMIN')]
    public function create(CreateJobPositionDto $dto): JsonResponse
    {
        $existing = $this->em->getRepository(JobPosition::class)->findOneBy(['code' => strtoupper($dto->getCode())]);
        if ($existing !== null) {
            return $this->json(['error' => ['message' => 'A job position with that code already exists.']], Response::HTTP_CONFLICT);
        }

        $area = JobPositionAreaEnum::tryFrom($dto->getArea());
        if ($area === null) {
            return $this->json(['error' => ['message' => 'Invalid area value.']], Response::HTTP_BAD_REQUEST);
        }

        $jobPosition = new JobPosition();
        $jobPosition->setCode($dto->getCode());
        $jobPosition->setName($dto->getName());
        $jobPosition->setArea($area);

        $this->em->persist($jobPosition);
        $this->em->flush();

        return $this->json($this->serialize($jobPosition), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'dashboard_job_positions_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Actualizar puesto de trabajo', tag: 'JobPositions', requestDto: UpdateJobPositionDto::class, responseDto: JobPositionOutDto::class)]
    #[IsGranted('ROLE_ADMIN')]
    public function update(int $id, UpdateJobPositionDto $dto): JsonResponse
    {
        $jobPosition = $this->em->getRepository(JobPosition::class)->find($id);
        if ($jobPosition === null) {
            return $this->json(['error' => ['message' => 'Job position not found']], Response::HTTP_NOT_FOUND);
        }

        if ($dto->getName() !== null) {
            $jobPosition->setName($dto->getName());
        }
        if ($dto->getArea() !== null) {
            $area = JobPositionAreaEnum::tryFrom($dto->getArea());
            if ($area === null) {
                return $this->json(['error' => ['message' => 'Invalid area value.']], Response::HTTP_BAD_REQUEST);
            }
            $jobPosition->setArea($area);
        }

        $this->em->flush();

        return $this->json($this->serialize($jobPosition));
    }

    #[Route('/{id}/toggle', name: 'dashboard_job_positions_toggle', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Activar/desactivar puesto de trabajo', tag: 'JobPositions', responseDto: ToggleOutDto::class)]
    #[IsGranted('ROLE_ADMIN')]
    public function toggle(int $id): JsonResponse
    {
        $jobPosition = $this->em->getRepository(JobPosition::class)->find($id);
        if ($jobPosition === null) {
            return $this->json(['error' => ['message' => 'Job position not found']], Response::HTTP_NOT_FOUND);
        }

        $jobPosition->setIsActive(!$jobPosition->isActive());
        $this->em->flush();

        return $this->json([
            'id'       => $jobPosition->getId(),
            'isActive' => $jobPosition->isActive(),
        ]);
    }

    private function serialize(JobPosition $jp): array
    {
        return [
            'id'        => $jp->getId(),
            'code'      => $jp->getCode(),
            'name'      => $jp->getName(),
            'area'      => $jp->getArea()?->value,
            'isActive'  => $jp->isActive(),
            'createdAt' => $jp->getCreatedAt()?->format('c'),
            'updatedAt' => $jp->getUpdatedAt()?->format('c'),
        ];
    }
}
