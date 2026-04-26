<?php

namespace App\Security;

use App\Service\UserService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class DashboardAccessToken extends AbstractAuthenticator
{
    public function __construct(
        private readonly UserService $userService,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization');
    }

    public function authenticate(Request $request): Passport
    {
        $headerToken = $request->headers->get('Authorization');
        if (!$headerToken || !str_starts_with($headerToken, 'Bearer ')) {
            throw new CustomUserMessageAuthenticationException('Missing or invalid authorization header.');
        }

        $token = substr($headerToken, 7);
        if (empty($token)) {
            throw new CustomUserMessageAuthenticationException('Empty authorization token.');
        }

        $user = $this->userService->parser($token);

        return new SelfValidatingPassport(new UserBadge($user->getUserIdentifier()));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'error' => [
                'message' => strtr($exception->getMessageKey(), $exception->getMessageData()),
            ],
        ], Response::HTTP_UNAUTHORIZED);
    }
}
