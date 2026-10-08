<?php

namespace App\EventListener;

use App\Exception\MyCurrentException;
use MiladRahimi\Jwt\Exceptions\InvalidSignatureException;
use MiladRahimi\Jwt\Exceptions\InvalidTokenException;
use MiladRahimi\Jwt\Exceptions\JsonDecodingException;
use MiladRahimi\Jwt\Exceptions\SigningException;
use MiladRahimi\Jwt\Exceptions\ValidationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Core\Exception\InsufficientAuthenticationException;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException as SerializerUnexpectedValueException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[AsEventListener]
class ExceptionListener
{
    public function __construct(private readonly LoggerInterface $logger) {}

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        $message = sprintf(
            '%s',
            $exception->getMessage()
        );
        $response = new JsonResponse([
            'error' => [
                'message' => $message
            ]
        ]);
        if ($exception instanceof ValidationFailedException) {
            $details = [];
            foreach ($exception->getViolations() as $v) {
                $details[] = $v->getPropertyPath() . ': ' . $v->getMessage();
            }
            $response = new JsonResponse(
                ['error' => ['message' => 'Validation failed', 'details' => $details]],
                Response::HTTP_BAD_REQUEST,
            );
        } elseif ($exception instanceof MyCurrentException) {
            $response = new JsonResponse([
                'error' => [
                    'message' => $exception->getMessage(),
                    'code' => $exception->getCodeWork(),
                ]
            ], Response::HTTP_BAD_REQUEST);
        } elseif (
            $exception instanceof InvalidSignatureException ||
            $exception instanceof InvalidTokenException ||
            $exception instanceof JsonDecodingException ||
            $exception instanceof SigningException ||
            $exception instanceof ValidationException
        ) {
            $response = new JsonResponse([
                'error' => [
                    'message' => $exception->getMessage()
                ]
            ], Response::HTTP_UNAUTHORIZED);
        } elseif ($exception instanceof AccessDeniedException || $exception instanceof InsufficientAuthenticationException || $exception instanceof AccessDeniedHttpException) {
            $response = new JsonResponse([
                'error' => [
                    'message' => $exception->getMessage()
                ]
            ], $exception->getCode());
        } elseif ($this->isClientBodyError($exception, $event->getRequest())) {
            $response = $this->clientBodyErrorResponse($exception);
        } elseif ($exception instanceof HttpExceptionInterface || $exception instanceof \ApiPlatform\Metadata\Exception\HttpExceptionInterface) {
            $response->setStatusCode($exception->getStatusCode());
            $response->headers->replace($exception->getHeaders());
        } else {
            // No exponer el mensaje interno (fragmentos de SQL, nombres de tablas,
            // rutas del servidor). El detalle queda solo en logs.
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
            $response = new JsonResponse([
                'error' => [
                    'message' => 'Internal server error',
                ]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $event->setResponse($response);
    }

    /**
     * Fallos del serializador al leer el cuerpo de una petición con efectos
     * (POST/PUT/PATCH/DELETE): son errores del cliente. En una petición segura
     * (GET) el mismo fallo sería al normalizar la respuesta, es decir, nuestro.
     */
    private function isClientBodyError(\Throwable $exception, Request $request): bool
    {
        if ($request->isMethodSafe()) {
            return false;
        }

        return $exception instanceof SerializerUnexpectedValueException
            || $exception instanceof MissingConstructorArgumentsException
            || $exception instanceof ExtraAttributesException;
    }

    private function clientBodyErrorResponse(\Throwable $exception): JsonResponse
    {
        if ($exception instanceof NotEncodableValueException) {
            return new JsonResponse(
                ['error' => ['message' => 'Invalid JSON body', 'code' => 'INVALID_JSON_BODY']],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $details = match (true) {
            $exception instanceof PartialDenormalizationException => $this->partialDetails($exception),
            $exception instanceof NotNormalizableValueException => [$this->typeDetail($exception)],
            $exception instanceof MissingConstructorArgumentsException => array_map(
                static fn (string $argument): string => $argument . ': this field is required',
                $exception->getMissingConstructorArguments(),
            ),
            $exception instanceof ExtraAttributesException => array_map(
                static fn (string $attribute): string => $attribute . ': unknown field',
                $exception->getExtraAttributes(),
            ),
            default => [],
        };

        $error = ['message' => 'Invalid request body'];
        if ($details !== []) {
            $error['details'] = array_values($details);
        }

        return new JsonResponse(['error' => $error], Response::HTTP_BAD_REQUEST);
    }

    /** @return list<string> */
    private function partialDetails(PartialDenormalizationException $exception): array
    {
        $details = array_map($this->typeDetail(...), $exception->getNotNormalizableValueErrors());
        foreach ($exception->getExtraAttributesError()?->getExtraAttributes() ?? [] as $attribute) {
            $details[] = $attribute . ': unknown field';
        }

        return $details;
    }

    /**
     * Solo campo y tipos: el mensaje original del serializador incluye el
     * nombre de la clase interna.
     */
    private function typeDetail(NotNormalizableValueException $exception): string
    {
        $expected = implode('|', $exception->getExpectedTypes() ?? []);
        $detail = $expected !== ''
            ? sprintf('expected %s, got %s', $expected, $exception->getCurrentType() ?? 'unknown')
            : 'invalid value';

        return $exception->getPath() !== null && $exception->getPath() !== ''
            ? $exception->getPath() . ': ' . $detail
            : $detail;
    }
}
