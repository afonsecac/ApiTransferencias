<?php

namespace App\OpenApi;

use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\OpenApi;
use App\DTO\Out\ErrorOutDto;
use App\DTO\Out\PaginatedListOutDto;
use App\OpenApi\Attribute\DashboardEndpoint;
use Symfony\Component\Routing\RouterInterface;

final class DashboardOpenApiBuilder
{
    private const PATH_PREFIX = '/dashboard/api';
    private const BODY_METHODS = ['POST', 'PUT', 'PATCH'];

    public function __construct(
        private readonly RouterInterface $router,
        private readonly DtoSchemaReflector $reflector,
    ) {}

    public function build(OpenApi $openApi): OpenApi
    {
        $paths = $openApi->getPaths();
        $schemas = $openApi->getComponents()->getSchemas() ?? new \ArrayObject();

        // Registrar schema de error estándar
        $this->reflector->resetVisited();
        $this->reflector->reflect(ErrorOutDto::class, $schemas);

        // Agrupar rutas por path normalizado
        $routesByPath = [];
        foreach ($this->router->getRouteCollection() as $routeName => $route) {
            $path = $route->getPath();
            if (!str_starts_with($path, self::PATH_PREFIX)) {
                continue;
            }

            $controller = $route->getDefault('_controller');
            if (!$controller) {
                continue;
            }

            if (str_contains($controller, '::')) {
                [$class, $method] = explode('::', $controller, 2);
            } elseif (str_contains($controller, ':')) {
                [$class, $method] = explode(':', $controller, 2);
            } else {
                // Controlador invokable: el _controller es solo el FQCN
                $class = $controller;
                $method = '__invoke';
            }

            if (!class_exists($class) || !method_exists($class, $method)) {
                continue;
            }

            $httpMethods = $route->getMethods() ?: ['GET'];
            foreach ($httpMethods as $httpMethod) {
                $routesByPath[$path][strtolower($httpMethod)] = [
                    'class' => $class,
                    'method' => $method,
                    'route' => $route,
                ];
            }
        }

        foreach ($routesByPath as $path => $methodsData) {
            $pathItem = new PathItem();

            foreach ($methodsData as $httpMethod => $data) {
                $reflMethod = new \ReflectionMethod($data['class'], $data['method']);
                $attrs = $reflMethod->getAttributes(DashboardEndpoint::class);
                $attr = $attrs ? $attrs[0]->newInstance() : new DashboardEndpoint();

                $operation = $this->buildOperation(
                    $httpMethod,
                    $path,
                    $data['route'],
                    $reflMethod,
                    $attr,
                    $schemas,
                );

                $pathItem = match ($httpMethod) {
                    'get'    => $pathItem->withGet($operation),
                    'post'   => $pathItem->withPost($operation),
                    'put'    => $pathItem->withPut($operation),
                    'patch'  => $pathItem->withPatch($operation),
                    'delete' => $pathItem->withDelete($operation),
                    default  => $pathItem,
                };
            }

            $paths->addPath($path, $pathItem);
        }

        // Si schemas estaba null hay que asociarlo al componente
        if ($openApi->getComponents()->getSchemas() === null) {
            $openApi = $openApi->withComponents(
                $openApi->getComponents()->withSchemas($schemas)
            );
        }

        return $openApi;
    }

    private function buildOperation(
        string $httpMethod,
        string $path,
        \Symfony\Component\Routing\Route $route,
        \ReflectionMethod $reflMethod,
        DashboardEndpoint $attr,
        \ArrayObject $schemas,
    ): Operation {
        $tag = $attr->tag ?? $this->inferTag($path);
        $summary = $attr->summary ?? $this->inferSummary($httpMethod, $path);

        $parameters = $this->buildPathParameters($path, $route);

        $requestBody = null;
        if (in_array(strtoupper($httpMethod), self::BODY_METHODS)) {
            $requestBody = $this->buildRequestBody($attr, $reflMethod, $schemas);
        }

        $responses = $this->buildResponses($httpMethod, $attr, $schemas, $path);

        $operationId = strtolower($httpMethod) . '_' . $this->pathToId($path);

        return new Operation(
            operationId: $operationId,
            tags: [$tag],
            responses: $responses,
            summary: $summary,
            description: $attr->description,
            parameters: $parameters ?: null,
            requestBody: $requestBody,
            security: [['Token' => []]],
        );
    }

    private function buildPathParameters(string $path, \Symfony\Component\Routing\Route $route): array
    {
        preg_match_all('/\{(\w+)\}/', $path, $matches);
        $params = [];

        foreach ($matches[1] as $varName) {
            $requirement = $route->getRequirement($varName);
            $type = ($requirement && preg_match('/^\\\?d\+$|^\d+$/', $requirement))
                ? 'integer'
                : 'string';

            $params[] = new Parameter(
                name: $varName,
                in: 'path',
                description: '',
                required: true,
                schema: ['type' => $type],
            );
        }

        return $params;
    }

    private function buildRequestBody(
        DashboardEndpoint $attr,
        \ReflectionMethod $reflMethod,
        \ArrayObject $schemas,
    ): RequestBody {
        $dtoClass = $attr->requestDto;

        // Auto-detectar IInput en los parámetros del método si no está en el atributo
        if ($dtoClass === null) {
            foreach ($reflMethod->getParameters() as $param) {
                $paramType = $param->getType();
                if ($paramType instanceof \ReflectionNamedType && class_exists($paramType->getName())) {
                    if (is_subclass_of($paramType->getName(), \App\DTO\IInput::class)) {
                        $dtoClass = $paramType->getName();
                        break;
                    }
                }
            }
        }

        if ($dtoClass !== null && class_exists($dtoClass)) {
            $this->reflector->resetVisited();
            $ref = $this->reflector->reflect($dtoClass, $schemas);
            $schemaData = $ref;
        } else {
            $schemaData = ['type' => 'object'];
        }

        return new RequestBody(
            description: 'Request body',
            content: new \ArrayObject([
                'application/json' => new MediaType(
                    schema: new \ArrayObject($schemaData),
                ),
            ]),
            required: true,
        );
    }

    private function buildResponses(
        string $httpMethod,
        DashboardEndpoint $attr,
        \ArrayObject $schemas,
        string $path,
    ): array {
        $statusCode = (string) ($attr->responseStatusCode ?? ($httpMethod === 'post' ? 201 : 200));

        $responseDto = $attr->responseDto;
        $itemDto = $attr->itemDto;

        if ($responseDto !== null && class_exists($responseDto)) {
            $this->reflector->resetVisited();
            $ref = $this->reflector->reflect($responseDto, $schemas, $itemDto);
            if ($attr->responseIsArray) {
                $successSchema = new \ArrayObject(['type' => 'array', 'items' => $ref]);
            } else {
                $successSchema = new \ArrayObject($ref);
            }
        } elseif ($httpMethod === 'delete') {
            $successSchema = new \ArrayObject(['type' => 'object', 'properties' => ['deleted' => ['type' => 'boolean']]]);
        } else {
            $successSchema = new \ArrayObject(['type' => 'object']);
        }

        $responses = [
            $statusCode => new Response(
                description: 'Éxito',
                content: new \ArrayObject([
                    'application/json' => new MediaType(schema: $successSchema),
                ]),
            ),
            '401' => new Response(description: 'No autenticado'),
            '403' => new Response(description: 'Sin permisos'),
        ];

        if (in_array(strtoupper($httpMethod), self::BODY_METHODS)) {
            $responses['400'] = new Response(
                description: 'Datos inválidos',
                content: new \ArrayObject([
                    'application/json' => new MediaType(
                        schema: new \ArrayObject(['$ref' => '#/components/schemas/ErrorOutDto']),
                    ),
                ]),
            );
        }

        if (str_contains($path, '{')) {
            $responses['404'] = new Response(description: 'No encontrado');
        }

        return $responses;
    }

    private function inferTag(string $path): string
    {
        // /dashboard/api/client/packages/{id} → "Client Packages"
        $segments = array_values(array_filter(
            explode('/', str_replace(self::PATH_PREFIX . '/', '', $path)),
            fn(string $s) => $s !== '' && !str_starts_with($s, '{'),
        ));

        $parts = array_slice($segments, 0, 2);

        return implode(' ', array_map('ucfirst', $parts));
    }

    private function inferSummary(string $httpMethod, string $path): string
    {
        $action = match (strtoupper($httpMethod)) {
            'GET'    => str_contains($path, '{') ? 'Obtener' : 'Listar',
            'POST'   => 'Crear',
            'PUT', 'PATCH' => 'Actualizar',
            'DELETE' => 'Eliminar',
            default  => ucfirst($httpMethod),
        };

        $resource = implode(' ', array_map('ucfirst', array_values(array_filter(
            explode('/', str_replace(self::PATH_PREFIX . '/', '', $path)),
            fn(string $s) => $s !== '' && !str_starts_with($s, '{'),
        ))));

        return "{$action} {$resource}";
    }

    private function pathToId(string $path): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $path));
    }
}
