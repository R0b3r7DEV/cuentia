<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The single place that resolves per-user API credentials (BYOK). It decrypts a user's own keys when set,
 * and otherwise falls back to the application-level environment variables. It also stores keys (encrypted)
 * and reports a safe status for the UI — the plaintext key is never returned to the client.
 *
 * ES: El único sitio que resuelve las credenciales de API por usuario. Descifra las claves propias del
 * usuario si las tiene y, si no, usa las variables de entorno de la app. También guarda claves (cifradas)
 * y reporta un estado seguro para la UI — la clave en claro nunca se devuelve al cliente.
 */
class CredentialStore
{
    public function __construct(
        private SecretCipher $cipher,
        private EntityManagerInterface $em,
        #[Autowire('%env(ANTHROPIC_API_KEY)%')] private string $envAnthropic = '',
        #[Autowire('%env(GOCARDLESS_SECRET_ID)%')] private string $envGcId = '',
        #[Autowire('%env(GOCARDLESS_SECRET_KEY)%')] private string $envGcKey = '',
    ) {}

    /** Effective Anthropic key: the user's own if set, else the app env fallback, else ''. */
    public function anthropicKey(User $user): string
    {
        return $this->decryptOr($user->getAnthropicKey(), $this->envAnthropic);
    }

    /** Effective GoCardless credentials (user's own, else env). @return array{id:string,key:string} */
    public function gocardless(User $user): array
    {
        $id = $this->decryptOr($user->getGocardlessSecretId(), '');
        $key = $this->decryptOr($user->getGocardlessSecretKey(), '');
        if ($id !== '' && $key !== '') {
            return ['id' => $id, 'key' => $key];
        }

        return ['id' => $this->envGcId, 'key' => $this->envGcKey];
    }

    public function setAnthropicKey(User $user, ?string $key): void
    {
        $user->setAnthropicKey($this->encryptOrNull($key));
        $this->em->flush();
    }

    public function setGocardless(User $user, ?string $id, ?string $key): void
    {
        $user->setGocardlessSecretId($this->encryptOrNull($id));
        $user->setGocardlessSecretKey($this->encryptOrNull($key));
        $this->em->flush();
    }

    /**
     * Safe status for the UI — whether the user configured their OWN key, plus a masked hint. Never the key.
     * @return array<string,mixed>
     */
    public function status(User $user): array
    {
        $anthropic = $this->decryptOr($user->getAnthropicKey(), '');
        $gcId = $this->decryptOr($user->getGocardlessSecretId(), '');
        $gcKey = $this->decryptOr($user->getGocardlessSecretKey(), '');

        return [
            'anthropic' => [
                'configured' => $anthropic !== '',
                'hint' => $anthropic !== '' ? '…' . substr($anthropic, -4) : null,
            ],
            'gocardless' => [
                'configured' => $gcId !== '' && $gcKey !== '',
                'hint' => $gcId !== '' ? '…' . substr($gcId, -4) : null,
            ],
        ];
    }

    private function decryptOr(?string $stored, string $fallback): string
    {
        if ($stored === null || $stored === '') {
            return $fallback;
        }

        return $this->cipher->decrypt($stored) ?? $fallback;
    }

    private function encryptOrNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $this->cipher->encrypt($value) : null;
    }
}
