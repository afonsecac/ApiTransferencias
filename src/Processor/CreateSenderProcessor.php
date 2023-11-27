<?php

namespace App\Processor;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Sender;
use App\Repository\EnvAuthRepository;
use App\Service\AuthService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CreateSenderProcessor implements ProcessorInterface
{
    private Serializer $serializer;
    public function __construct(
        private AuthService $authService,
        private EnvAuthRepository $authRepository,
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $em
    ) {
        $encoders = [new XmlEncoder(), new JsonEncoder()];
        $normalizer = [new DateTimeNormalizer(), new DateIntervalNormalizer(), new ObjectNormalizer()];
        $this->serializer = new Serializer($normalizer, $encoders);
    }

    /**
     * @inheritDoc
     * @param mixed $data
     * @param Operation $operation
     * @param array $uriVariables
     * @param array $context
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $accessToken = null;
        $accessTokens = $this->authRepository->findBy([
            'closedAt' => null
        ], [
            'createdAt' => 'DESC'
        ]);
        if (is_null($accessTokens) || count($accessTokens) === 0) {
            $token = $this->authService->start();
            $accessToken = $this->authRepository->findOneBy([
                'tokenAuth' => $token
            ]);
        } else {
            $accessToken = $accessTokens[0];
            $token = $accessToken->getTokenAuth();
        }
        if ($data instanceof Sender) {
            $url = $accessToken->getPermission()?->getEnvironment()?->getBasePath()."/api/Senders";
            $tokenIn = 'Bearer '.$accessToken->getTokenAuth();
            $response = $this->httpClient->request(
                'POST',
                $url,
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'Authorization' => $tokenIn
                    ],
                    'body' => $this->serializer->serialize(
                        [
                            'email' => $data->getEmail(),
                            'phone' => trim($data->getPhone()),
                            'name' => sprintf("%s%s %s", $data->getFirstName(), is_null($data->getMiddleName()) ? "" : " ".$data->getMiddleName(), $data->getLastName()),
                            'firstName' => sprintf("%s%s", $data->getFirstName(), is_null($data->getMiddleName()) ? "" : " ".$data->getMiddleName()),
                            'lastName' => $data->getLastName(),
                            'address' => $data->getAddress(),
                            'identification' => $data->getIdentification()
                        ], 'json', []
                    )
                ]
            );


            try {
                $content = $response->getContent();

                $object = (object) $response->toArray();

                $data->setRebusSenderId($object->id);
                $data->setTenant($accessToken);
                $this->em->persist($data);

                $this->em->flush();
            } catch (RedirectionExceptionInterface|ServerExceptionInterface|TransportExceptionInterface|ClientExceptionInterface $ex) {
                if ($ex->getCode() === 401) {
                    $accessToken->setClosedAt(new \DateTimeImmutable('now'));
                    $this->em->flush();
                    $this->process($data, $operation, $uriVariables, $context);
                    return;
                }
                throw $ex;
            }
        }
    }
}
