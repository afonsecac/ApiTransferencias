<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Domain;

/**
 * Un registro fiscal, o uno de sus campos, no cumple las reglas del dominio.
 */
class InvalidFiscalProfile extends \DomainException
{
}
