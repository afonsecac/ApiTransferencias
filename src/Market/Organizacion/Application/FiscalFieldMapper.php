<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Application;

use App\Market\Organizacion\Domain\FiscalField;
use App\Market\Organizacion\Domain\InvalidFiscalProfile;

final class FiscalFieldMapper
{
    /**
     * @param list<FiscalFieldInput> $inputs
     * @return list<FiscalField>
     * @throws InvalidFiscalProfile
     */
    public static function toFields(array $inputs): array
    {
        return array_map(
            static fn (FiscalFieldInput $in) => FiscalField::fromArray([
                'key' => $in->key,
                'label' => $in->label,
                'type' => $in->type,
                'required' => $in->required,
                'pattern' => $in->pattern,
                'helpText' => $in->helpText,
                'options' => $in->options,
                'active' => $in->active,
                'position' => $in->position,
            ]),
            $inputs,
        );
    }
}
