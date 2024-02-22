<?php

namespace App\EventListener;

use App\Exception\MyCurrentException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Core\Exception\InsufficientAuthenticationException;

#[AsEventListener]
class ExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        $message = sprintf(
            'Error: %s with code: %s',
            $exception->getMessage(),
            $exception->getCode()
        );
        $response = new JsonResponse([
            'error' => [
                'message' => $message
            ]
        ]);
        if ($exception instanceof MyCurrentException) {
            $response = new JsonResponse([
                'error' => [
                    'message' => sprintf(
                        'Error: %s with code: %s',
                        $exception->getMessage(),
                        $exception->getCodeWork()
                    ),
                    'code' => $exception->getCodeWork(),
                ]
            ], Response::HTTP_BAD_REQUEST);
        } elseif ($exception instanceof AccessDeniedException || $exception instanceof InsufficientAuthenticationException || $exception instanceof AccessDeniedHttpException) {
            $response = new JsonResponse([
                'error' => [
                    'message' => $exception->getMessage()
                ]
            ], 403);
        } elseif ($exception instanceof HttpExceptionInterface || $exception instanceof \ApiPlatform\Metadata\Exception\HttpExceptionInterface) {
            $response->setStatusCode($exception->getStatusCode());
            $response->headers->replace($exception->getHeaders());
        } else {
            $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $event->setResponse($response);
    }
}
