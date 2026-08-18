<?php

namespace App\Tests\Entity;

use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationPromotions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Guard de regresión: `/communication/packages` sirve V1
 * (CommunicationClientPackage) o V2 (CommunicationPackage) según
 * CatalogVersionResolver::isV2() — ambas clases deben publicar el mismo
 * juego de claves en el grupo 'comPackage:read' para que la app móvil no
 * vea un shape distinto al cambiar de versión. Si este test falla tras
 * agregar un campo a una sola de las dos clases, hay que decidir a
 * propósito si el otro catálogo también lo necesita (o documentar por qué
 * no).
 *
 * @covers \App\Entity\CommunicationPackage
 */
class CommunicationPackageShapeParityTest extends TestCase
{
    /**
     * @return list<string> nombres de campo tal como los vería el cliente
     *   (propiedades tal cual, getX()/isX() normalizados a x)
     */
    private function comPackageReadKeys(string $class): array
    {
        $reflection = new \ReflectionClass($class);
        $keys = [];

        foreach ($reflection->getProperties() as $property) {
            if ($this->hasGroup($property->getAttributes(Groups::class), 'comPackage:read')) {
                $keys[] = $property->getName();
            }
        }

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (!$this->hasGroup($method->getAttributes(Groups::class), 'comPackage:read')) {
                continue;
            }
            $name = $method->getName();
            if (preg_match('/^(get|is)([A-Z].*)$/', $name, $m)) {
                $keys[] = lcfirst($m[2]);
            } else {
                $keys[] = $name;
            }
        }

        sort($keys);

        return array_values(array_unique($keys));
    }

    /**
     * @param array<\ReflectionAttribute> $attributes
     */
    private function hasGroup(array $attributes, string $group): bool
    {
        foreach ($attributes as $attribute) {
            /** @var Groups $groups */
            $groups = $attribute->newInstance();
            if (in_array($group, $groups->groups, true)) {
                return true;
            }
        }

        return false;
    }

    public function testV2CoversEveryKeyThatV1Exposes(): void
    {
        $v1Keys = $this->comPackageReadKeys(CommunicationClientPackage::class);
        $v2Keys = $this->comPackageReadKeys(CommunicationPackage::class);

        $missingInV2 = array_values(array_diff($v1Keys, $v2Keys));

        $this->assertSame(
            [],
            $missingInV2,
            'CommunicationPackage (V2) no expone estas claves que sí expone CommunicationClientPackage (V1) en comPackage:read: ' . implode(', ', $missingInV2)
        );
    }

    public function testGetPromotionsReturnsEmptyArrayWhenNoPromotion(): void
    {
        $package = (new CommunicationPackage())
            ->setName('P')->setDescription('P')
            ->setDestinationAmount(500.0)->setDestinationCurrency('CUP');

        $this->assertSame([], $package->getPromotions());
    }

    public function testGetPromotionsReturnsSingleElementArrayWhenPromotionSet(): void
    {
        $promotion = $this->createMock(CommunicationPromotions::class);
        $package = (new CommunicationPackage())
            ->setName('P')->setDescription('P')
            ->setDestinationAmount(500.0)->setDestinationCurrency('CUP')
            ->setPromotion($promotion);

        $this->assertSame([$promotion], $package->getPromotions());
    }

    public function testIsActiveAtWithinOpenEndedWindow(): void
    {
        $now = new \DateTimeImmutable('2026-08-18 12:00:00');
        $package = (new CommunicationPackage())
            ->setName('P')->setDescription('P')
            ->setDestinationAmount(500.0)->setDestinationCurrency('CUP')
            ->setActiveStartAt($now->modify('-1 day'));

        $this->assertTrue($package->isActiveAt($now));
    }

    public function testIsActiveAtFalseBeforeStart(): void
    {
        $now = new \DateTimeImmutable('2026-08-18 12:00:00');
        $package = (new CommunicationPackage())
            ->setName('P')->setDescription('P')
            ->setDestinationAmount(500.0)->setDestinationCurrency('CUP')
            ->setActiveStartAt($now->modify('+1 day'));

        $this->assertFalse($package->isActiveAt($now));
    }

    public function testIsActiveAtFalseAfterEnd(): void
    {
        $now = new \DateTimeImmutable('2026-08-18 12:00:00');
        $package = (new CommunicationPackage())
            ->setName('P')->setDescription('P')
            ->setDestinationAmount(500.0)->setDestinationCurrency('CUP')
            ->setActiveStartAt($now->modify('-2 days'))
            ->setActiveEndAt($now->modify('-1 day'));

        $this->assertFalse($package->isActiveAt($now));
    }

    public function testIsActiveAtFalseWhenNotActive(): void
    {
        $now = new \DateTimeImmutable('2026-08-18 12:00:00');
        $package = (new CommunicationPackage())
            ->setName('P')->setDescription('P')
            ->setDestinationAmount(500.0)->setDestinationCurrency('CUP')
            ->setActiveStartAt($now->modify('-1 day'))
            ->setIsActive(false);

        $this->assertFalse($package->isActiveAt($now));
    }
}
