<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Domain;

enum FiscalFieldType: string
{
    case TEXT = 'text';
    case NUMBER = 'number';
    case DATE = 'date';
    case SELECT = 'select';
}
