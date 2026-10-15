<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Persistence;

use App\Entity\CommunicationContract;
use App\Entity\CommunicationPackage;
use App\Market\Catalogo\Domain\Amount;
use App\Market\Catalogo\Domain\PackageOffer;
use App\Market\Catalogo\Domain\Product;
use App\Market\Catalogo\Domain\ProductPackages;
use App\Repository\CommunicationContractRepository;
use App\Repository\CommunicationPackageRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Guarda el paquete de un producto del mercado en el catálogo del comprador, que es el de comunicaciones (COMR-275).
 *
 * El paquete lleva la clasificación del producto (servicio, subservicio y área), los textos en español y el enlace
 * `market_product_id`. Su precio sale de un contrato por defecto (sin cuenta) de su misma categoría, y la tupla de destino
 * del contrato es el propio precio: dos productos del mismo precio comparten contrato, y por construcción nunca hay dos
 * precios en un mismo contrato. Es una solución provisional hasta las tarifas del hito 3.
 *
 * El proveedor es el propio (OWN): los paquetes del mercado no tienen producto de proveedor, así que el catálogo los trata
 * como vinculados por serlo (CommunicationPackage::isMarket()).
 */
final class LegacyProductPackages implements ProductPackages
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CommunicationPackageRepository $packages,
        private readonly CommunicationContractRepository $contracts,
    ) {
    }

    public function publish(Product $product, Amount $price, string $currency): PackageOffer
    {
        $spec = $product->spec();
        $name = $spec->texts->forLanguage('es')['es']['name'] ?? $product->sku()->value();
        $description = $spec->texts->forLanguage('es')['es']['description'] ?? $name;
        $classification = $spec->classification;
        $service = ['name' => $classification->service(), 'subservice' => ['name' => $classification->subservice()]];
        if ($classification->area() !== null) {
            $service['area'] = ['name' => $classification->area()];
        }
        $amount = (float) $price->toString();

        $package = $this->packageOf((int) $product->id()) ?? (new CommunicationPackage())
            ->setMarketProductId((int) $product->id())
            ->setActiveStartAt(new \DateTimeImmutable());
        $package
            ->setName(mb_substr($name, 0, 255))
            ->setDescription(mb_substr($description, 0, 255))
            ->setService($service)
            ->setDestinationAmount($amount)
            ->setDestinationCurrency($currency)
            ->setIsActive(true);
        $this->em->persist($package);

        $this->assignContract($package, $amount, $currency, $classification->service(), $classification->subservice(), $classification->area());
        $this->em->flush();

        return new PackageOffer((int) $package->getId(), $price, $currency, true);
    }

    public function retire(int $productId): bool
    {
        $package = $this->packageOf($productId);
        if ($package === null) {
            return false;
        }
        $package->setIsActive(false);
        $this->em->flush();

        return true;
    }

    public function findFor(int $productId): ?PackageOffer
    {
        $package = $this->packageOf($productId);
        if ($package === null) {
            return null;
        }

        return new PackageOffer(
            (int) $package->getId(),
            Amount::fromString(number_format((float) $package->getDestinationAmount(), 2, '.', '')),
            (string) $package->getDestinationCurrency(),
            $package->isActive(),
        );
    }

    private function packageOf(int $productId): ?CommunicationPackage
    {
        return $this->packages->findOneBy(['marketProductId' => $productId]);
    }

    /**
     * Deja el paquete solo en el contrato por defecto abierto de su categoría y precio, creándolo si hace falta, y lo saca de
     * los demás (el precio anterior).
     */
    private function assignContract(CommunicationPackage $package, float $amount, string $currency, string $service, string $subservice, ?string $area): void
    {
        $contract = $this->contracts->findOpenContract(null, $amount, $currency, $package->getServiceKey());
        foreach ($this->contracts->findBy(['tenant' => null, 'endAt' => null]) as $other) {
            if ($other !== $contract && $other->getPackages()->contains($package)) {
                $other->removePackage($package);
            }
        }
        if ($contract === null) {
            $contract = (new CommunicationContract())
                ->setTenant(null)
                ->setDestinationAmount($amount)
                ->setDestinationCurrency($currency)
                ->setServiceCategory($service, $subservice, $area)
                ->setPrice($amount)
                ->setCurrency($currency);
            $this->em->persist($contract);
        }
        $contract->addPackage($package);
    }
}
