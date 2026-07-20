<?php
/**
 * Genera el código TOTP actual para un secreto Base32, usando el mismo helper que la
 * aplicación. Herramienta de pruebas manuales del flujo 2FA.
 *
 * Uso: php scripts/2fa-totp.php <SECRETO_BASE32>
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Security\TwoFactorHelper;

$secret = $argv[1] ?? null;

if ($secret === null) {
    fwrite(STDERR, "Uso: php scripts/2fa-totp.php <SECRETO_BASE32>\n");
    exit(1);
}

$ref = new ReflectionClass(TwoFactorHelper::class);

$decode = $ref->getMethod('base32Decode');
$decode->setAccessible(true);

$hotp = $ref->getMethod('hotp');
$hotp->setAccessible(true);

$key       = $decode->invoke(null, $secret);
$timestep  = (int) floor(time() / 30);

echo $hotp->invoke(null, $key, $timestep) . "\n";
