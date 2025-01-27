<?php

namespace App\Controller;

use App\Service\NavigationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/navigation')]
class NavigationController extends AbstractController
{
    public function __construct(
        private readonly NavigationService $navigationService,
        private readonly SerializerInterface $serializer
    ) {

    }

    /**
     * @throws \JsonException
     */
    #[Route(name: 'navigation', methods: ['GET'])]
    public function navigation(): JsonResponse
    {
        $serializerArray = $this->serializer->normalize(
            $this->navigationService->getNavigationForUsers(),
            'json',
            [
                'groups' => ['navigation'],
                AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            ]
        );

        return $this->json([
            'compact' => $serializerArray,
            'default' => $serializerArray,
            'futuristic' => $serializerArray,
            'horizontal' => $serializerArray,
        ]);
    }
}