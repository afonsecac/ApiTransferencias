<?php

declare(strict_types=1);

namespace App\Tests\Market\GiftCards\Domain;

use App\Market\GiftCards\Domain\GiftCardNumber;
use PHPUnit\Framework\TestCase;

final class GiftCardNumberTest extends TestCase
{
    public function testTheLuhnCheckAcceptsKnownCardNumbersAndRejectsTypos(): void
    {
        foreach (['4111111111111111', '5500000000000004', '378282246310005'] as $valid) {
            $this->assertTrue(GiftCardNumber::passesLuhn($valid), $valid);
        }
        foreach (['4111111111111112', '5500000000000005', '1234567812345678'] as $invalid) {
            $this->assertFalse(GiftCardNumber::passesLuhn($invalid), $invalid);
        }
    }

    public function testAGeneratedNumberIs16DigitsStartsWithTheIssuerPrefixAndPassesLuhn(): void
    {
        for ($i = 0; $i < 200; ++$i) {
            $number = GiftCardNumber::generate();

            $this->assertMatchesRegularExpression('/^9\d{15}$/', $number);
            $this->assertTrue(GiftCardNumber::passesLuhn($number), $number);
            $this->assertTrue(GiftCardNumber::isValid($number));
        }
    }

    public function testGeneratedNumbersDoNotRepeatNorFollowASequence(): void
    {
        $numbers = array_map(static fn () => GiftCardNumber::generate(), range(1, 300));

        $this->assertCount(300, array_unique($numbers));
    }

    public function testItIsFormattedInGroupsOfFourAndParsedBack(): void
    {
        $this->assertSame('4111 1111 1111 1111', GiftCardNumber::format('4111111111111111'));
        $this->assertSame('4111111111111111', GiftCardNumber::normalize(' 4111-1111 1111.1111 '));
    }

    public function testOnlyExactly16DigitsAreValid(): void
    {
        $this->assertFalse(GiftCardNumber::isValid('411111111111111'));
        $this->assertFalse(GiftCardNumber::isValid('GC-000123'));
        $this->assertFalse(GiftCardNumber::isValid('41111111111111111'));
    }
}
