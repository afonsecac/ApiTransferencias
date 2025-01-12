<?php

namespace App\Controller;

use App\Entity\NavigationItem;
use App\Service\NavigationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
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
        $serializerInfo = $this->serializer->serialize(
            $this->navigationService->getNavigationItems(),
            'json',
            [
                AbstractNormalizer::IGNORED_ATTRIBUTES => ['orderValue', 'createdAt', 'updatedAt', 'parent'],
                AbstractObjectNormalizer::SKIP_NULL_VALUES => true
            ]
        );
        $serializerArray = json_decode($serializerInfo, true, 512, JSON_THROW_ON_ERROR);

        return $this->json([
            'compact' => $serializerArray,
            'default' => $serializerArray,
            'futuristic' => $serializerArray,
            'horizontal' => $serializerArray
        ]);
    }
}