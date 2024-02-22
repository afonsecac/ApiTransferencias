<?php

namespace App\Security;

use ApiPlatform\Symfony\Security\Exception\AccessDeniedException;
use App\Repository\AccountRepository;
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

class ApiTokenAuthenticator extends AbstractAuthenticator
{
    private AccountRepository $permission;

    public function __construct(AccountRepository $permissionRepo)
    {
        $this->permission = $permissionRepo;
    }

    /**
     * @inheritDoc
     */
    public function supports(Request $request): ?bool
    {
        return $request->headers->has('X-AUTH-TOKEN');
    }

    /**
     * @inheritDoc
     */
    public function authenticate(Request $request): Passport
    {
        $apiToken = $request->headers->get('X-AUTH-TOKEN');
        if (null === $apiToken) {
            // The token header was empty, authentication fails with HTTP Status
            // Code 401 "Unauthorized"
            throw new CustomUserMessageAuthenticationException('No API token provided');
        }
        $currentToken = base64_decode($apiToken, true);
        if (!$currentToken) {
            throw new CustomUserMessageAuthenticationException('No valid token');
        }
        $permission = $this->permission->findOneBy([
            'accessToken' => $currentToken,
            'isActive' => true,
        ]);

        if (is_null($permission)) {
            throw new CustomUserMessageAuthenticationException('User not found');
        }
        $ips = $request->headers->get('X-Forwarded-For') ?? $request->headers->get('x-forwarded-for');
        $referer = $request->headers->get("Referer");
        $host = $request->headers->get("host");
        $isWebPage = !is_null($referer) && strpos($host, $referer) >= 0;

        $isLocal = str_contains($ips, "127.0.0.1") || str_contains($ips, "::1");
        $isRemote = !is_null($ips) && !empty($ips) && !empty($permission->getOrigin()) && (strpos($ips, $permission->getOrigin()) >= 0 || strpos( "*", $permission->getOrigin()) >= 0);
        if (!$isLocal && !$isRemote && !$isWebPage) {
            throw new AccessDeniedException();
        }

        return new SelfValidatingPassport(new UserBadge($permission->getUserIdentifier()));
    }

    /**
     * @inheritDoc
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $data = [
            'error' => strtr($exception->getMessageKey(), $exception->getMessageData()),
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }
}
