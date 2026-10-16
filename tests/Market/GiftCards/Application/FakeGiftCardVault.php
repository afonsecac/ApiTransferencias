<?php

declare(strict_types=1);

namespace App\Tests\Market\GiftCards\Application;

use App\Market\GiftCards\Domain\GiftCardPinVault;

final class FakeGiftCardVault implements GiftCardPinVault
{
    private int $n = 0;

    public function generate(): string
    {
        return sprintf('AAAA-BBBB-CCCC-%04d', ++$this->n);
    }

    public function encrypt(string $pin): string
    {
        return 'enc:' . $pin;
    }

    public function decrypt(string $cipher): string
    {
        return substr($cipher, 4);
    }

    public function hash(string $pin): string
    {
        return 'h:' . strtoupper(str_replace(['-', ' '], '', $pin));
    }
}
