<?php

namespace App\Security;

/**
 * TOTP (RFC 6238) and email OTP helper — no external library required.
 */
final class TwoFactorHelper
{
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const TOTP_WINDOW  = 1; // accept ±1 time step (±30 s)

    /** Sin 0/O ni 1/I/L: el usuario transcribe estos códigos a mano. */
    private const BACKUP_CHARS       = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    private const BACKUP_CODE_COUNT  = 10;

    // ── TOTP ────────────────────────────────────────────────────────────────

    public static function generateSecret(): string
    {
        $bytes = random_bytes(20);
        return self::base32Encode($bytes);
    }

    public static function generateTotpUri(string $secret, string $email, string $issuer = 'Dashboard'): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            rawurlencode($issuer),
            rawurlencode($email),
            $secret,
            rawurlencode($issuer)
        );
    }

    public static function verifyTotp(string $secret, string $code): bool
    {
        return self::matchTotpTimeStep($secret, $code) !== null;
    }

    /**
     * Devuelve el time step con el que casa el código, o null si no casa con ninguno de
     * la ventana aceptada. Quien lo llame debe persistir el valor devuelto y rechazar
     * cualquier time step que no sea estrictamente mayor al último usado: sin eso, un
     * código interceptado puede reutilizarse durante toda su ventana de validez.
     */
    public static function matchTotpTimeStep(string $secret, string $code): ?int
    {
        $secretBytes = self::base32Decode($secret);
        $timeStep    = (int) floor(time() / 30);

        for ($i = -self::TOTP_WINDOW; $i <= self::TOTP_WINDOW; $i++) {
            $candidate = $timeStep + $i;
            if (hash_equals(self::hotp($secretBytes, $candidate), $code)) {
                return $candidate;
            }
        }

        return null;
    }

    // ── Email OTP ───────────────────────────────────────────────────────────

    public static function generateEmailCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    // ── Códigos de respaldo ─────────────────────────────────────────────────

    /**
     * Genera códigos de un solo uso con formato `XXXX-XXXX`. Se excluyen los caracteres
     * ambiguos (0/O, 1/I/L) porque el usuario los va a transcribir a mano desde papel.
     *
     * @return string[] códigos en claro; solo se muestran una vez
     */
    public static function generateBackupCodes(int $cantidad = self::BACKUP_CODE_COUNT): array
    {
        $codigos = [];

        for ($i = 0; $i < $cantidad; $i++) {
            $bruto = '';
            for ($j = 0; $j < 8; $j++) {
                $bruto .= self::BACKUP_CHARS[random_int(0, strlen(self::BACKUP_CHARS) - 1)];
            }
            $codigos[] = substr($bruto, 0, 4) . '-' . substr($bruto, 4);
        }

        return $codigos;
    }

    /**
     * Hash de un código de respaldo. SHA-256 sin sal es suficiente aquí: a diferencia de
     * una contraseña, el código lo genera el sistema con ~41 bits de entropía, así que no
     * es susceptible de ataque por diccionario y no hace falta un hash lento.
     */
    public static function hashBackupCode(string $code): string
    {
        return hash('sha256', self::normalizeBackupCode($code));
    }

    /** Acepta el código con o sin guion, en mayúsculas o minúsculas. */
    public static function normalizeBackupCode(string $code): string
    {
        return strtoupper(str_replace(['-', ' '], '', trim($code)));
    }

    // ── Internals ───────────────────────────────────────────────────────────

    private static function hotp(string $secretBytes, int $counter): string
    {
        $msg  = pack('J', $counter); // 64-bit big-endian
        $hash = hash_hmac('sha1', $msg, $secretBytes, true);
        $offset = ord($hash[19]) & 0xF;
        $code = (
            ((ord($hash[$offset])     & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8)  |
            ( ord($hash[$offset + 3]) & 0xFF)
        ) % 1_000_000;

        return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $bytes): string
    {
        $bits   = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $output = '';
        foreach (str_split(str_pad($bits, (int) ceil(strlen($bits) / 5) * 5, '0'), 5) as $chunk) {
            $output .= self::BASE32_CHARS[bindec($chunk)];
        }

        return $output;
    }

    private static function base32Decode(string $input): string
    {
        $input = strtoupper($input);
        $bits  = '';
        foreach (str_split($input) as $char) {
            $pos = strpos(self::BASE32_CHARS, $char);
            if ($pos === false) {
                continue;
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $output = '';
        foreach (str_split(substr($bits, 0, (int) floor(strlen($bits) / 8) * 8), 8) as $chunk) {
            $output .= chr(bindec($chunk));
        }

        return $output;
    }
}
