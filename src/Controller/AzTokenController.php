<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/azToken')]
class AzTokenController extends AbstractController
{
    #[Route('', name: 'app_az_token', methods: ['POST', 'GET'])]
    public function index(Request $request): JsonResponse
    {
        $params = $request->attributes->all();
        return $this->json([
            'queries' => $request->query->all(),
            'post' => $request->request->all(),
        ]);
    }
}
