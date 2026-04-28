<?php

namespace App\Security;

use App\Repository\AccountRepository;
use App\Service\IpMatcherService;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
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

#[WithMonologChannel('security')]
class ApiTokenAuthenticator extends AbstractAuthenticator
{
    private AccountRepository $permission;
    private LoggerInterface $logger;
    private IpMatcherService $ipMatcherService;

    public function __construct(
        LoggerInterface $logger,
        AccountRepository $permissionRepo,
        IpMatcherService $ipMatcherService,
    )
    {
        $this->permission = $permissionRepo;
        $this->logger = $logger;
        $this->ipMatcherService = $ipMatcherService;
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

        if (is_null($permission) || !$permission->getClient()?->isIsActive()) {
            throw new CustomUserMessageAuthenticationException('User not found');
        }
        $ips = $request->headers->get('X-Forwarded-For') ?? $request->headers->get('x-forwarded-for');
        $otherIps = $request->getClientIps();
        if (is_null($ips) && count($otherIps) !== 0) {
            $ips = implode(",", $otherIps);
        }
        $referer = $request->headers->get("Referer");
        $host = $request->headers->get("host");
        $isWebPage = !is_null($referer) && !is_null($host) && str_contains($referer, $host);

        $isLocal = !empty($ips) && (str_contains($ips, "127.0.0.1") || str_contains($ips, "::1"));
        $origin = $permission->getOrigin();
        $isWildcard = $origin === '*';
        $isOriginMatch = false;
        if (!$isWildcard && !empty($ips) && !empty($origin)) {
            $isOriginMatch = $this->ipMatcherService->isIpAllowed($ips, $origin);
        }
        $isRemote = $isWildcard || $isOriginMatch;
        if (!$isLocal && !$isRemote && !$isWebPage) {
            $this->logger->debug('The logger in reques URL {url} info Local: {isLocal}, IsRemote: {isRemote}, IPs: {ips}, IsWebPage: {isWebPage}', [
                'isLocal' => $isLocal,
                'isRemote' => $isRemote,
                'isWebPage' => $isWebPage,
                'ips' => $ips,
                'url' => $request->getPathInfo(),
                'headers' => $request->headers->all(),
                'servers' => $request->server->all(),
                'allIps' => $request->getClientIps()
            ]);
            throw new CustomUserMessageAuthenticationException('Access not allowed from this IP address');
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
