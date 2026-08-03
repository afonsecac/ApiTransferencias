<?php

namespace App\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Base para tests funcionales que ejercitan servicios/repositorios reales
 * contra una base de datos Postgres real (no mocks).
 *
 * Aísla cada test envolviéndolo en una transacción que se revierte en
 * tearDown(). Doctrine DBAL 4 anida automáticamente con savepoints
 * cualquier begin/commit interno del código de negocio (p.ej. los claims
 * atómicos de saldo) dentro de esta transacción exterior, así que un solo
 * rollBack() al final deshace todo. Requiere que la base de datos de test
 * ya tenga el esquema migrado.
 */
abstract class FunctionalTestCase extends KernelTestCase
{
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);
        $this->em = $em;

        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $connection = $this->em->getConnection();

        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }

        $this->em->close();

        parent::tearDown();
    }
}
