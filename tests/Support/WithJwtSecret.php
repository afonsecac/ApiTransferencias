<?php

declare(strict_types=1);

namespace App\Tests\Support;

/**
 * Las pruebas que firman un JWT de verdad necesitan un APP_SECRET de 32 bytes o más (la firma HS512 lo exige); en CI el secreto es
 * corto a propósito. Fija uno suficiente solo mientras dura la prueba y restaura el original al terminar. Se llama
 * `useLongJwtSecret()` ANTES de arrancar el kernel y `restoreJwtSecret()` al terminar.
 */
trait WithJwtSecret
{
    private ?string $previousAppSecret = null;
    private bool $jwtSecretChanged = false;

    protected function useLongJwtSecret(): void
    {
        $current = (string) ($_SERVER['APP_SECRET'] ?? $_ENV['APP_SECRET'] ?? getenv('APP_SECRET') ?: '');
        if (strlen($current) >= 32) {
            return;
        }
        $this->previousAppSecret = $current === '' ? null : $current;
        $this->jwtSecretChanged = true;
        $long = 'test-only-jwt-secret-0123456789-abcdefghijklmnop';
        putenv('APP_SECRET=' . $long);
        $_SERVER['APP_SECRET'] = $_ENV['APP_SECRET'] = $long;
    }

    protected function restoreJwtSecret(): void
    {
        if (!$this->jwtSecretChanged) {
            return;
        }
        if ($this->previousAppSecret === null) {
            putenv('APP_SECRET');
            unset($_SERVER['APP_SECRET'], $_ENV['APP_SECRET']);
        } else {
            putenv('APP_SECRET=' . $this->previousAppSecret);
            $_SERVER['APP_SECRET'] = $_ENV['APP_SECRET'] = $this->previousAppSecret;
        }
        $this->jwtSecretChanged = false;
    }
}
