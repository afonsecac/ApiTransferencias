<?php

namespace App\Tests\Functional;

use App\Entity\SysConfig;

/**
 * Verifica que FunctionalTestCase aísla correctamente cada test: ambos
 * métodos insertan una fila con el mismo `propertyName` (columna UNIQUE).
 * Si el rollback entre tests no funcionara, el segundo test violaría la
 * restricción única y fallaría con un error de integridad, no con un fallo
 * de aserción.
 */
class FunctionalTestCaseIsolationTest extends FunctionalTestCase
{
    private const PROPERTY_NAME = 'test.functional.isolation.marker';

    public function testFirstTestInsertsRow(): void
    {
        $config = (new SysConfig())
            ->setPropertyName(self::PROPERTY_NAME)
            ->setPropertyValue('first');

        $this->em->persist($config);
        $this->em->flush();

        $this->assertNotNull($config->getId());
    }

    public function testSecondTestCanInsertSamePropertyNameBecauseFirstWasRolledBack(): void
    {
        $existing = $this->em->getRepository(SysConfig::class)->findOneBy(['propertyName' => self::PROPERTY_NAME]);
        $this->assertNull($existing, 'La fila del test anterior no debería sobrevivir entre tests.');

        $config = (new SysConfig())
            ->setPropertyName(self::PROPERTY_NAME)
            ->setPropertyValue('second');

        $this->em->persist($config);
        $this->em->flush();

        $this->assertNotNull($config->getId());
    }
}
