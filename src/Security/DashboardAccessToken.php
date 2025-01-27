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
    )
    {

    }
    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization');
    }

    /**
     * @throws \MiladRahimi\Jwt\Exceptions\InvalidTokenException
     * @throws \MiladRahimi\Jwt\Exceptions\SigningException
     * @throws \MiladRahimi\Jwt\Exceptions\ValidationException
     * @throws \MiladRahimi\Jwt\Exceptions\InvalidSignatureException
     * @throws \MiladRahimi\Jwt\Exceptions\JsonDecodingException
     * @throws \DateMalformedStringException
     */
    public function authenticate(Request $request): Passport
    {
        $headerToken = $request->headers->get('Authorization');
        if (!$headerToken) {
            throw new CustomUserMessageAuthenticationException('Missing authorization header.');
        }
        $token = substr($headerToken, 7);
        if (!$token) {
            throw new CustomUserMessageAuthenticationException('User don\'t have authorization.');
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
        $data = [
            // you may want to customize or obfuscate the message first
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData()),

            // or to translate this message
            // $this->translator->trans($exception->getMessageKey(), $exception->getMessageData())
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }

}