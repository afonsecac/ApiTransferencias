<?php

namespace App\Service;

use App\Entity\EnvAuth;
use App\Entity\Permission;
use App\Repository\EnvironmentRepository;
use App\Service\CommonService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AuthService extends CommonService
{
    private HttpClientInterface $httpClient;

    public function __construct(
        EntityManagerInterface $em,
        Security $security,
        ParameterBagInterface $parameters,
        MailerInterface $mailer,
        LoggerInterface $logger,
        UserPasswordHasherInterface $passwordHasher,
        EnvironmentRepository $environmentRepository,
        HttpClientInterface $httpClient
    ) {
        parent::__construct($em, $security, $parameters, $mailer, $logger, $passwordHasher, $environmentRepository);
        $this->httpClient = $httpClient;
    }

    /**
     * @return string
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function start(): string {
        $envAuth = new EnvAuth();
        $url = "";

        $permission = $this->security->getUser();
        if ($permission instanceof Permission) {
            $url = $this->parameters->get('app.microsoft.url')."/";

            $url .= $permission->getEnvironment()?->getTenantId()."/oauth2/v2.0/token";
        }

        $tokenInfoResponse = $this->httpClient->request(
            'POST',
            $url,
            [
                'headers' => [
                    'Accept' => 'application/x-www-form-urlencoded',
                ],
                'body' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $permission->getEnvironment()?->getClientId(),
                    'scope' => $permission->getEnvironment()?->getScope(),
                    'client_secret' => $permission->getEnvironment()?->getClientSecret()
                ]
            ]
        );
        $content = $tokenInfoResponse->getContent();
        $response = (object) $tokenInfoResponse->toArray();
        $token  = $response->access_token;

        $envAuth->setTokenAuth($token);
        $envAuth->setPermission($permission);
        $this->em->persist($envAuth);
        $this->em->flush();


        return $token;
    }
}
