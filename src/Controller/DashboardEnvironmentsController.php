<?php

namespace App\Controller;

use App\DTO\CreateEnvironmentDto;
use App\DTO\Out\EnvironmentOutDto;
use App\DTO\UpdateEnvironmentDto;
use App\Exception\MyCurrentException;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Service\EnvironmentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class DashboardEnvironmentsController extends AbstractController
{
    public function __construct(
        private readonly EnvironmentService $environmentService,
    ) {}

    #[Route('/admin/environments/{id}', name: 'dashboard_admin_environments_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Obtener entorno por ID', tag: 'Environments', responseDto: EnvironmentOutDto::class)]
    public function show(int $id): JsonResponse
    {
        try {
            $env = $this->environmentService->findOrFail($id);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage(), 'code' => $e->getCodeWork()]], $e->getCode());
        }

        return $this->json(EnvironmentOutDto::fromEntity($env));
    }

    #[Route('/admin/environments', name: 'dashboard_admin_environments_create', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Crear entorno', tag: 'Environments', responseDto: EnvironmentOutDto::class, responseStatusCode: 201)]
    public function create(CreateEnvironmentDto $dto): JsonResponse
    {
        try {
            $env = $this->environmentService->create($dto);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage(), 'code' => $e->getCodeWork()]], $e->getCode());
        }

        return $this->json(EnvironmentOutDto::fromEntity($env), Response::HTTP_CREATED);
    }

    #[Route('/admin/environments/{id}', name: 'dashboard_admin_environments_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Actualizar entorno', tag: 'Environments', responseDto: EnvironmentOutDto::class)]
    public function update(int $id, UpdateEnvironmentDto $dto): JsonResponse
    {
        try {
            $env = $this->environmentService->findOrFail($id);
            $env = $this->environmentService->update($env, $dto);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage(), 'code' => $e->getCodeWork()]], $e->getCode());
        }

        return $this->json(EnvironmentOutDto::fromEntity($env));
    }
}
