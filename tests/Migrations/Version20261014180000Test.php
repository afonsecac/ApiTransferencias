<?php

namespace App\Tests\Migrations;

use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use DoctrineMigrations\Version20261014180000;
use Psr\Log\NullLogger;

// Las migraciones no están en el autoload de Composer.
require_once dirname(__DIR__, 2) . '/migrations/Version20261014180000.php';

/**
 * COMR-276 sobre COMR-273: esta migración añadió el soporte de productos digitales. Sus columnas y su CHECK los sustituye
 * Version20261014190000 (clasificación por servicio, subservicio y área, y valor); las restricciones vigentes se prueban en
 * Version20261014190000Test. Aquí solo queda lo que sigue siendo cierto de ella.
 *
 * @covers \DoctrineMigrations\Version20261014180000
 */
class Version20261014180000Test extends ProviderFunctionalTestCase
{
    public function testItHasADescription(): void
    {
        $migration = new Version20261014180000($this->em->getConnection(), new NullLogger());

        $this->assertNotSame('', $migration->getDescription());
    }
}
