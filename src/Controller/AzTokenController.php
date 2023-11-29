<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/azToken')]
class AzTokenController extends AbstractController
{

//    private CommonService $commonService;
//
//    /**
//     * @param CommonService $commonService
//     */
//    public function __construct(CommonService $commonService)
//    {
//        $this->commonService = $commonService;
//    }

    #[Route('', name: 'app_az_token', methods: ['POST', 'GET'])]
    public function index(Request $request): JsonResponse
    {
        $params = $request->attributes->all();

//        $this->commonService->onCreatedAdmin();
//        $this->commonService->onCreateCompany();

        return $this->json([
            'queries' => $request->query->all(),
            'post' => $request->request->all(),
        ]);
    }
}
