<?php

namespace App\Controller;

use App\Entity\Transaction;
use App\Entity\User;
use App\Entity\UserLogo;
use App\Service\CredentialStore;
use App\Service\ImageSanitizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Account management: clear a user's data, or delete the account entirely (GDPR art. 17).
 * ES: Gestión de cuenta: limpiar los datos del usuario, o borrar la cuenta por completo (RGPD art. 17).
 */
class AccountController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    /** Delete all of the current user's transactions (keeps the account). */
    #[Route('/api/account/clear', name: 'api_account_clear', methods: ['POST'])]
    public function clear(EntityManagerInterface $em, #[CurrentUser] User $user): JsonResponse
    {
        $cleared = $this->deleteUserTransactions($em, $user);
        return $this->json(['cleared' => $cleared]);
    }

    /** Delete the account and all its data, then invalidate the session (right to erasure). */
    #[Route('/api/account', name: 'api_account_delete', methods: ['DELETE'])]
    public function delete(EntityManagerInterface $em, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $this->deleteUserTransactions($em, $user);
        $em->remove($user);
        $em->flush();

        // End the session so the (now deleted) user is fully logged out.
        // ES: Cierra la sesión para que el usuario (ya borrado) quede completamente deslogueado.
        $request->getSession()->invalidate();

        return $this->json(['deleted' => true]);
    }

    /** The user's billing settings: mode (standard/verifactu) + issuer fiscal profile. */
    #[Route('/api/account/settings', name: 'api_account_settings', methods: ['GET'])]
    public function settings(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json($this->settingsPayload($user));
    }

    /** Update the billing mode and/or issuer fiscal profile. */
    #[Route('/api/account/settings', name: 'api_account_settings_save', methods: ['PUT'])]
    public function saveSettings(Request $request, EntityManagerInterface $em, #[CurrentUser] User $user): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON'], 400);
        }
        if (array_key_exists('billingMode', $data)) {
            $user->setBillingMode((string) $data['billingMode']);
        }
        if (array_key_exists('businessName', $data)) {
            $v = trim((string) $data['businessName']);
            $user->setBusinessName($v !== '' ? $v : null);
        }
        if (array_key_exists('fiscalAddress', $data)) {
            $v = trim((string) $data['fiscalAddress']);
            $user->setFiscalAddress($v !== '' ? $v : null);
        }
        if (array_key_exists('taxId', $data)) {
            $v = trim((string) $data['taxId']);
            $user->setTaxId($v !== '' ? $v : null);
        }
        foreach (['installerRegNumber' => 40, 'phone' => 30] as $field => $max) {
            if (array_key_exists($field, $data)) {
                $v = mb_substr(trim((string) $data[$field]), 0, $max);
                $user->{'set' . ucfirst($field)}($v !== '' ? $v : null);
            }
        }
        if (array_key_exists('iban', $data)) {
            $v = \App\Util\Iban::normalize((string) $data['iban']);
            if ($v !== '' && !\App\Util\Iban::isValid($v)) {
                return $this->json(['error' => 'invalid_iban'], 400);
            }
            $user->setIban($v !== '' ? $v : null);
        }
        if (array_key_exists('defaultLaborRate', $data)) {
            // Empty clears it; otherwise a non-negative amount, "35,50" or "35.50".
            $v = str_replace(',', '.', trim((string) $data['defaultLaborRate']));
            if ($v !== '' && (!is_numeric($v) || (float) $v < 0)) {
                return $this->json(['error' => 'invalid_amount'], 400);
            }
            $user->setDefaultLaborRate($v !== '' ? number_format((float) $v, 2, '.', '') : null);
        }
        $em->flush();

        return $this->json($this->settingsPayload($user));
    }

    /** @return array<string,mixed> */
    private function settingsPayload(User $user): array
    {
        return [
            'billingMode'      => $user->getBillingMode(),
            'businessName'     => $user->getBusinessName(),
            'fiscalAddress'    => $user->getFiscalAddress(),
            'taxId'            => $user->getTaxId(),
            'defaultLaborRate' => $user->getDefaultLaborRate(),
            'installerRegNumber' => $user->getInstallerRegNumber(),
            'phone'            => $user->getPhone(),
            'iban'             => $user->getIban() ? \App\Util\Iban::format($user->getIban()) : null,
            'logoUrl'          => $this->hasLogo($user) ? '/api/account/logo' : null,
            // What is still missing to issue each kind of document (empty = ready).
            // ES: Lo que aún falta para emitir cada documento (vacío = listo).
            'missing' => [
                'invoice' => array_keys(array_filter([
                    'businessName'  => !$user->getBusinessName(),
                    'taxId'         => !$user->getTaxId(),
                    'fiscalAddress' => !$user->getFiscalAddress(),
                ])),
                'certificate' => array_keys(array_filter([
                    'businessName'       => !$user->getBusinessName(),
                    'taxId'              => !$user->getTaxId(),
                    'installerRegNumber' => !$user->getInstallerRegNumber(),
                ])),
            ],
        ];
    }

    private function hasLogo(User $user): bool
    {
        return $this->em->getRepository(UserLogo::class)->count(['user' => $user]) > 0;
    }

    /** The user's logo (PNG). Only ever their own: there is no id in the URL. */
    #[Route('/api/account/logo', name: 'api_account_logo', methods: ['GET'])]
    public function logo(#[CurrentUser] User $user): Response
    {
        $logo = $this->em->getRepository(UserLogo::class)->findOneBy(['user' => $user]);
        if ($logo === null) {
            return $this->json(['error' => 'not_found'], 404);
        }

        return new Response($logo->getPng(), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-cache',
        ]);
    }

    /** Multipart upload, field "logo": sniffed, size-capped and re-encoded (ImageSanitizer). */
    #[Route('/api/account/logo', name: 'api_account_logo_save', methods: ['POST'])]
    public function saveLogo(Request $request, ImageSanitizer $images, #[CurrentUser] User $user): JsonResponse
    {
        $file = $request->files->get('logo');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return $this->json(['error' => 'invalid_image'], 400);
        }
        try {
            $clean = $images->logo((string) file_get_contents($file->getPathname()));
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        $logo = $this->em->getRepository(UserLogo::class)->findOneBy(['user' => $user]) ?? new UserLogo($user);
        $logo->setPng($clean['png'], $clean['width'], $clean['height']);
        $this->em->persist($logo);
        $this->em->flush();

        return $this->json($this->settingsPayload($user));
    }

    #[Route('/api/account/logo', name: 'api_account_logo_delete', methods: ['DELETE'])]
    public function deleteLogo(#[CurrentUser] User $user): JsonResponse
    {
        $logo = $this->em->getRepository(UserLogo::class)->findOneBy(['user' => $user]);
        if ($logo !== null) {
            $this->em->remove($logo);
            $this->em->flush();
        }

        return $this->json($this->settingsPayload($user));
    }

    /** Status of the user's own API integrations (never returns the keys). */
    #[Route('/api/account/integrations', name: 'api_account_integrations', methods: ['GET'])]
    public function integrations(CredentialStore $credentials, #[CurrentUser] User $user): JsonResponse
    {
        return $this->json($credentials->status($user));
    }

    /** Save (or update) the user's own Anthropic API key. Send an empty key to clear it. */
    #[Route('/api/account/integrations/anthropic', name: 'api_account_anthropic', methods: ['PUT'])]
    public function setAnthropic(Request $request, CredentialStore $credentials, #[CurrentUser] User $user): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $credentials->setAnthropicKey($user, is_array($data) ? (string) ($data['key'] ?? '') : '');

        return $this->json($credentials->status($user));
    }

    #[Route('/api/account/integrations/anthropic', name: 'api_account_anthropic_delete', methods: ['DELETE'])]
    public function deleteAnthropic(CredentialStore $credentials, #[CurrentUser] User $user): JsonResponse
    {
        $credentials->setAnthropicKey($user, null);

        return $this->json($credentials->status($user));
    }

    /** Save (or update) the user's own GoCardless credentials. */
    #[Route('/api/account/integrations/gocardless', name: 'api_account_gocardless', methods: ['PUT'])]
    public function setGocardless(Request $request, CredentialStore $credentials, #[CurrentUser] User $user): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $id = is_array($data) ? (string) ($data['secretId'] ?? '') : '';
        $key = is_array($data) ? (string) ($data['secretKey'] ?? '') : '';
        $credentials->setGocardless($user, $id, $key);

        return $this->json($credentials->status($user));
    }

    #[Route('/api/account/integrations/gocardless', name: 'api_account_gocardless_delete', methods: ['DELETE'])]
    public function deleteGocardless(CredentialStore $credentials, #[CurrentUser] User $user): JsonResponse
    {
        $credentials->setGocardless($user, null, null);

        return $this->json($credentials->status($user));
    }

    private function deleteUserTransactions(EntityManagerInterface $em, User $user): int
    {
        return $em->createQuery('DELETE FROM ' . Transaction::class . ' t WHERE t.user = :u')
            ->setParameter('u', $user)
            ->execute();
    }
}
