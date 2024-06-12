<?php

namespace App\Service;

use App\Entity\ProductComm;
use App\Entity\ProductCommBenefits;
use App\Exception\MyCurrentException;
use App\Repository\CommunicationProductRepository;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use App\Service\CommonService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ProductBenefitService extends CommonService
{
    private CommunicationProductRepository $productRepository;
    public function __construct(
        EntityManagerInterface $em,
        Security $security,
        ParameterBagInterface $parameters,
        MailerInterface $mailer,
        LoggerInterface $logger,
        UserPasswordHasherInterface $passwordHasher,
        EnvironmentRepository $environmentRepository,
        SysConfigRepository $sysConfigRepo,
        CommunicationProductRepository $communicationProductRepository
    ) {
        parent::__construct(
            $em,
            $security,
            $parameters,
            $mailer,
            $logger,
            $passwordHasher,
            $environmentRepository,
            $sysConfigRepo
        );
        $this->productRepository = $communicationProductRepository;
    }

    public function getImportProducts(): array {
        try {
            $products = $this->productRepository->findAll();
            foreach ($products as $product) {
                $description = $product->getDescription();
                $myProduct = new ProductComm();
                if (str_contains($description, 'PREPAGO')) {
                    $myProduct->setIsProcessed(true);
                    $benefit = new ProductCommBenefits();
                    $benefit->setProductCommId($myProduct);
                }
            }

            return $products;
        } catch (MyCurrentException $ex) {
            throw $ex;
        }
    }

}
