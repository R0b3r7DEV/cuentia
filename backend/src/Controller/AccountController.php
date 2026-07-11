<?php

namespace App\Controller;

use App\Entity\Transaction;
use App\Entity\User;
use App\Service\CredentialStore;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Account management: clear a user's data, or delete the account entirely (GDPR art. 17).
 * ES: Gestión de cuenta: limpiar los datos del usuario, o borrar la cuenta por completo (RGPD art. 17).
 */
class AccountController extends AbstractController
{
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
        $em->flush();

        return $this->json($this->settingsPayload($user));
    }

    /** @return array<string,mixed> */
    private function settingsPayload(User $user): array
    {
        return [
            'billingMode'   => $user->getBillingMode(),
            'businessName'  => $user->getBusinessName(),
            'fiscalAddress' => $user->getFiscalAddress(),
            'taxId'         => $user->getTaxId(),
        ];
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
