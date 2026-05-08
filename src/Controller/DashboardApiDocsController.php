<?php

namespace App\Controller;

use App\OpenApi\DashboardOpenApiBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

class DashboardApiDocsController extends AbstractController
{
    public function __construct(
        private readonly DashboardOpenApiBuilder $builder,
        private readonly SerializerInterface $serializer,
    ) {}

    #[Route('/docs.json', name: 'dashboard_docs_json', methods: ['GET'])]
    public function spec(): Response
    {
        $spec = $this->builder->buildStandalone();

        return new Response(
            $this->serializer->serialize($spec, 'json'),
            Response::HTTP_OK,
            ['Content-Type' => 'application/json'],
        );
    }

    #[Route('/docs', name: 'dashboard_docs_ui', methods: ['GET'])]
    public function ui(): Response
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard API Docs</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
    <style>
        body { margin: 0; }
        .swagger-ui .topbar { background-color: #1b1b1b; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function () {
            SwaggerUIBundle({
                url: '/dashboard/api/docs.json',
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset,
                ],
                plugins: [SwaggerUIBundle.plugins.DownloadUrl],
                layout: 'StandaloneLayout',
            });
        };
    </script>
</body>
</html>
HTML;

        return new Response($html, Response::HTTP_OK, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
