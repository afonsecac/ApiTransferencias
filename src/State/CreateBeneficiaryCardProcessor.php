<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Account;
use App\Entity\BankCard;
use App\Repository\BeneficiaryRepository;
use App\Repository\EnvAuthRepository;
use App\Service\AuthService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
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

final class CreateBeneficiaryCardProcessor implements ProcessorInterface
{

    private Serializer $serializer;
    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $em,
        private readonly BeneficiaryRepository $beneficiaryRepository,
        private readonly HttpClientInterface $httpClient,
        private readonly AuthService $authService,
        private readonly EnvAuthRepository $authRepository,
    )
    {
        $encoders = [new XmlEncoder(), new JsonEncoder()];
        $normalizer = [new DateTimeNormalizer(), new DateIntervalNormalizer(), new ObjectNormalizer()];
        $this->serializer = new Serializer($normalizer, $encoders);
    }

    /**
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $user = $this->security->getUser();
        if ($data instanceof BankCard && $user instanceof Account) {

            $beneficiary = $this->beneficiaryRepository->find($data->getBeneficiaryCardId());
            $data->setBeneficiary($beneficiary);

            $accessToken = null;
            $accessTokens = $this->authRepository->findBy([
                'closedAt' => null,
            ], [
                'createdAt' => 'DESC',
            ]);
            if (is_null($accessTokens) || count($accessTokens) === 0) {
                $token = $this->authService->start();
                $accessToken = $this->authRepository->findOneBy([
                    'tokenAuth' => $token,
                ]);
            } else {
                $accessToken = $accessTokens[0];
                $token = $accessToken->getTokenAuth();
            }

            if (!is_null($accessToken)) {
                $url = $accessToken->getPermission()?->getEnvironment()?->getBasePath()."/api/BeneficiaryRemittances";
                $tokenIn = 'Bearer '.$accessToken->getTokenAuth();
                $beneficiaryInfo = $this->serializer->serialize(
                    [
                        'email' => $data->getBeneficiary()?->getEmail(),
                        'telephone' => trim($data->getBeneficiary()?->getPhone()),
                        'homeTelephone' => trim($data->getBeneficiary()?->getHomePhone()),
                        'displayName' => sprintf(
                            "%s%s %s",
                            $data->getBeneficiary()?->getFirstName(),
                            is_null($data->getBeneficiary()?->getMiddleName()) ? "" : " ".$data->getBeneficiary()?->getMiddleName(),
                            $data->getBeneficiary()?->getLastName()
                        ),
                        'firstName' => sprintf(
                            "%s%s",
                            $data->getBeneficiary()?->getFirstName(),
                            is_null($data->getBeneficiary()?->getMiddleName()) ? "" : " ".$data->getBeneficiary()?->getMiddleName()
                        ),
                        'dob' => $data->getBeneficiary()?->getDateOfBirth()?->format('Y-m-d'),
                        'lastName' => $data->getBeneficiary()?->getLastName(),
                        'addressLine1' => $data->getBeneficiary()?->getAddressLine1(),
                        'addressLine2' => $data->getBeneficiary()?->getAddressLine2(),
                        'nationalIdNumber' => $data->getBeneficiary()?->getIdentificationNumber(),
                        'cityId' => $data->getBeneficiary()?->getCity()?->getRebusId(),
                        'provinceId' => $data->getBeneficiary()?->getCity()?->getProvince()?->getRebusProvinceId(),
                        'countryId' => $data->getBeneficiary()?->getCity()?->getProvince()?->getCountry()?->getRebusId(),
                        'postalCode' => $data->getBeneficiary()?->getZipCode(),
                        'cardNumber' => $data->getCardNumber()
                    ], 'json', []
                );
                $response = $this->httpClient->request(
                    'POST',
                    $url,
                    [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Accept' => 'application/json',
                            'Authorization' => $tokenIn,
                        ],
                        'body' => $beneficiaryInfo,
                    ]
                );


                try {

                    $content = $response->getContent();

                    $responseInfo = (object) $response->toArray();
                    $data->setRebusId($responseInfo->id);

                    $this->em->persist($data);
                    $this->em->flush();
                } catch (RedirectionExceptionInterface|ServerExceptionInterface|TransportExceptionInterface|ClientExceptionInterface $ex) {
                    if ($ex->getCode() === 401) {
                        $accessToken->setClosedAt(new \DateTimeImmutable('now'));
                        $this->em->flush();


                        return $this->process($data, $operation, $uriVariables, $context);
                    }
                    throw $ex;
                }
            }
        }
        return $data;
    }
}
