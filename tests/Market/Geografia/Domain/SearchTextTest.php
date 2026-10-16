<?php

declare(strict_types=1);

namespace App\Tests\Market\Geografia\Domain;

use App\Market\Geografia\Domain\SearchText;
use PHPUnit\Framework\TestCase;

final class SearchTextTest extends TestCase
{
    public function testItRemovesAccentsAndLowercases(): void
    {
        $this->assertSame('guines', SearchText::normalize('Güines'));
        $this->assertSame('cienaga de zapata', SearchText::normalize('Ciénaga de Zapata'));
        $this->assertSame('la habana del este', SearchText::normalize('  La  Habana   del Este '));
    }

    public function testItKeepsDigitsAndDropsPunctuation(): void
    {
        $this->assertSame('songo la maya', SearchText::normalize('Songo-La Maya'));
        $this->assertSame('carlos m de cespedes', SearchText::normalize('Carlos M. de Céspedes'));
    }
}
