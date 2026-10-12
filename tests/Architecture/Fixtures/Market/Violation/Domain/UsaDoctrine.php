<?php

declare(strict_types=1);

namespace App\Market\Violation\Domain;

use Doctrine\ORM\EntityManagerInterface;

/** Fixture: Domain no puede depender de Doctrine. */
final class UsaDoctrine
{
    public function __construct(public readonly EntityManagerInterface $em)
    {
    }
}
