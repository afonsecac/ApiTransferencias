<?php

namespace App\Security;

/**
 * TOTP (RFC 6238) and email OTP helper — no external library required.
 */
final class TwoFactorHelper
{
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const TOTP_WINDOW  = 1; // accept ±1 time step (±30 s)

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
        $secretBytes = self::base32Decode($secret);
        $timeStep    = (int) floor(time() / 30);

        for ($i = -self::TOTP_WINDOW; $i <= self::TOTP_WINDOW; $i++) {
            if (hash_equals(self::hotp($secretBytes, $timeStep + $i), $code)) {
                return true;
            }
        }

        return false;
    }

    // ── Email OTP ───────────────────────────────────────────────────────────

    public static function generateEmailCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
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
