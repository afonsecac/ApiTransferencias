<?php

namespace App\Security;

/**
 * Cifrado simétrico autenticado (AES-256-GCM) para secretos que deben poder recuperarse
 * en claro, como el secreto TOTP: no sirve un hash porque hay que regenerar códigos con él.
 *
 * El formato almacenado es `v1:base64(nonce || tag || ciphertext)`. El prefijo de versión
 * permite migrar de algoritmo más adelante, y distinguir un valor cifrado de uno heredado
 * en claro sin necesidad de una columna extra.
 *
 * La clave se deriva por HKDF a partir de un secreto de aplicación, de modo que esta
 * función nunca usa el secreto crudo y un cambio de propósito no reutiliza material de
 * clave. Ojo: si ese secreto cambia, los valores existentes dejan de poder descifrarse y
 * los usuarios afectados tendrán que volver a enrolar su 2FA.
 */
class SecretCipher
{
    private const CIPHER  = 'aes-256-gcm';
    private const PREFIX  = 'v1:';
    private const INFO    = '2fa-secret-encryption';
    private const TAG_LEN = 16;

    private readonly string $key;

    public function __construct(string $appSecret)
    {
        $this->key = hash_hkdf('sha256', $appSecret, 32, self::INFO);
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(openssl_cipher_iv_length(self::CIPHER));
        $tag   = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            self::TAG_LEN
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Unable to encrypt secret.');
        }

        return self::PREFIX . base64_encode($nonce . $tag . $ciphertext);
    }

    /**
     * Descifra un valor. Los valores sin el prefijo de versión se devuelven tal cual: son
     * secretos anteriores al cifrado y deben seguir funcionando hasta que se migren.
     */
    public function decrypt(string $stored): string
    {
        if (!str_starts_with($stored, self::PREFIX)) {
            return $stored;
        }

        $raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if ($raw === false) {
            throw new \RuntimeException('Unable to decode encrypted secret.');
        }

        $nonceLen = openssl_cipher_iv_length(self::CIPHER);
        $nonce    = substr($raw, 0, $nonceLen);
        $tag      = substr($raw, $nonceLen, self::TAG_LEN);
        $cipher   = substr($raw, $nonceLen + self::TAG_LEN);

        $plaintext = openssl_decrypt($cipher, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $nonce, $tag);

        if ($plaintext === false) {
            throw new \RuntimeException('Unable to decrypt secret.');
        }

        return $plaintext;
    }

    public function isEncrypted(string $stored): bool
    {
        return str_starts_with($stored, self::PREFIX);
    }
}
