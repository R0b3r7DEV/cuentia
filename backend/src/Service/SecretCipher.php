<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Symmetric encryption for secrets stored at rest (users' API keys), using AES-256-GCM with a key
 * derived from the application secret. The IV and auth tag are stored alongside the ciphertext.
 *
 * EN: Keys are never stored in plaintext; only this service (with the app secret) can decrypt them, and
 * the plaintext is never sent back to the browser.
 * ES: Las claves nunca se guardan en claro; solo este servicio (con el secreto de la app) puede
 * descifrarlas, y el texto en claro nunca se devuelve al navegador.
 */
class SecretCipher
{
    private const CIPHER = 'aes-256-gcm';

    private string $key;

    public function __construct(#[Autowire('%kernel.secret%')] string $appSecret)
    {
        // 32-byte key derived from the app secret (namespaced so it's specific to credentials).
        $this->key = hash('sha256', 'cuentia:credentials:' . $appSecret, true);
    }

    /** Encrypt plaintext → base64(iv · tag · ciphertext). */
    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag);

        return base64_encode($iv . $tag . $ciphertext);
    }

    /** Decrypt a value produced by encrypt(); returns null if it can't be authenticated. */
    public function decrypt(string $stored): ?string
    {
        $raw = base64_decode($stored, true);
        if ($raw === false || strlen($raw) < 28) {
            return null;
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ciphertext = substr($raw, 28);

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag);

        return $plaintext === false ? null : $plaintext;
    }
}
