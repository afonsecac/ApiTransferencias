<?php

namespace App\DTO\Out;

final class ExportResultOutDto
{
    public string $name;
    /** @var ExportOperationItemOutDto[] */
    public array $operations = [];
}
