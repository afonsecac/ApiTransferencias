<?php

namespace App\Processor;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Sender;
use App\Repository\EnvAuthRepository;
use App\Service\AuthService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CreateSenderProcessor implements ProcessorInterface
{
    public function __construct(
        private AuthService $authService,
        private EnvAuthRepository $authRepository,
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $em
    ) {
    }

    /**
     * @inheritDoc
     * @param mixed $data
     * @param Operation $operation
     * @param array $uriVariables
     * @param array $context
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $accessToken = $this->authRepository->findBy([
            'closedAt' => null
        ], [
            'createdAt' => 'DESC'
        ]);
        if (is_null($accessToken)) {
            $token = $this->authService->start();
            $accessToken = $this->authRepository->findOneBy([
                'tokenAuth' => $token
            ]);
        } else {
            $token = $accessToken->getTokenAuth();
        }
        if ($data instanceof Sender) {
            $response = $this->httpClient->request(
                'POST',
                $accessToken->getPermission()?->getEnvironment()?->getBasePath()."/api/Sender",
                [
                    'body' => [
                        'email' => $data->getEmail(),
                        'phone' => $data->getPhone(),
                        'firstName' => $data->getFirstName(),
                        'lastName' => $data->getLastName(),
                        'address' => $data->getAddress(),
                        'identification' => $data->getIdentification()
                    ]
                ]
            );

            $content = $response->getContent();
            var_dump($content);

            $object = (object) $response->toArray();

            $data->setRebusSenderId($object->id);
            $this->em->persist($data);

            $this->em->flush();
        }
    }
}
