<?php

namespace App\Controller;

use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/admin/permission')]
class AdminPermissionController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService,
        private readonly SerializerInterface $serializer
    ) {

    }

    #[Route(name: 'admin_permission_index', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return $this->json(
            $this->serializer->normalize(
                $this->userService->getPermissionUsed(),
                'json',
                [
                    'groups' => ['accounts:read'],
                ]
            )
        );
    }
}