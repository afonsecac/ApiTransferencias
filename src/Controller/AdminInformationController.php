<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\RequestInfo;
use App\Service\CommunicationInfoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminInformationController extends AbstractController
{
    public function __construct(private readonly CommunicationInfoService $infoService)
    {
    }

    /**
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     */
    #[Route('/information', name: 'admin_information', methods: ['POST'])]
    public function index(RequestInfo $requestInfo): JsonResponse
    {
        return $this->json(
            $this->infoService->querySale($requestInfo)
        );
    }
}
