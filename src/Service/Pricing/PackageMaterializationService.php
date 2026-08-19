<?php

namespace App\Service\Pricing;

use App\Entity\Account;
use App\Entity\CommunicationClientPackage;
use App\Repository\CommunicationClientPackageRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Clona un paquete REFERENCIA (CommunicationClientPackage con tenant IS
 * NULL) en una copia propia de un cliente concreto. Equivalente al
 * PackagePriceService::createPackageClient() anterior, pero partiendo de un
 * ClientPackage referencia en vez de un CommunicationPricePackage — desde
 * este rediseño el precio ya NO se copia aquí (era $pricePackage->getAmount()
 * antes; ahora la copia no fija ni priceClientPackage ni un precio propio,
 * PackageSalePriceResolver lo resuelve en el momento vía `referencePackage`).
 *
 * Consumido por ClientCatalogImportService (alta al enrutar un cliente a un
 * proveedor) y por CommunicationClientPackageProvider (auto-copia perezosa
 * en el primer GET de un tenant sin paquetes propios).
 */
class PackageMaterializationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Idempotente: si el tenant ya tiene una copia de esta referencia,
     * devuelve la existente sin duplicar (protegido además por el índice
     * único parcial uniq_ccp_tenant_reference a nivel de BD). No hace
     * flush() — el llamador decide cuándo (mismo patrón que
     * PackagePriceService::createPackageClient()).
     */
    public function materializeForTenant(CommunicationClientPackage $referencePackage, Account $tenant): CommunicationClientPackage
    {
        /** @var CommunicationClientPackageRepository $repo */
        $repo = $this->em->getRepository(CommunicationClientPackage::class);
        $existing = $repo->findOneBy([
            'tenant' => $tenant,
            'referencePackage' => $referencePackage,
        ]);
        if ($existing !== null) {
            return $existing;
        }

        $clientPackage = (new CommunicationClientPackage())
            ->setTenant($tenant)
            ->setReferencePackage($referencePackage)
            ->setProduct($referencePackage->getProduct())
            ->setName($referencePackage->getName())
            ->setDescription($referencePackage->getDescription())
            ->setBenefits($referencePackage->getBenefits())
            ->setTags($referencePackage->getTags())
            ->setService($referencePackage->getService())
            ->setDestination($referencePackage->getDestination())
            ->setValidity($referencePackage->getValidity())
            ->setActiveStartAt($referencePackage->getActiveStartAt())
            ->setActiveEndAt($referencePackage->getActiveEndAt());

        if ($referencePackage->getKnowMore() !== null) {
            $clientPackage->setKnowMore($referencePackage->getKnowMore());
        }
        if ($referencePackage->getEnvironment() !== null) {
            $clientPackage->setEnvironment($referencePackage->getEnvironment());
        }

        $this->em->persist($clientPackage);

        return $clientPackage;
    }
}
