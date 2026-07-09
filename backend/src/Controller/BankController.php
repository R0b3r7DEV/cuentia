<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\CredentialStore;
use App\Service\GoCardlessClient;
use App\Service\OpenBankingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Open-banking endpoints (GoCardless Bank Account Data). Uses the current user's own credentials (BYOK),
 * falling back to the app-level env vars. Every action but /status returns 503 when not configured.
 * ES: Endpoints de banca abierta. Usa las credenciales propias del usuario (BYOK), con las variables de
 * entorno de la app como fallback. Todo salvo /status devuelve 503 si no está configurado.
 */
class BankController extends AbstractController
{
    #[Route('/api/bank/status', name: 'api_bank_status', methods: ['GET'])]
    public function status(GoCardlessClient $client, CredentialStore $credentials, #[CurrentUser] User $user): JsonResponse
    {
        return $this->json(['enabled' => $this->ready($client, $credentials, $user)]);
    }

    #[Route('/api/bank/institutions', name: 'api_bank_institutions', methods: ['GET'])]
    public function institutions(GoCardlessClient $client, CredentialStore $credentials, #[CurrentUser] User $user): JsonResponse
    {
        if (!$this->ready($client, $credentials, $user)) {
            return $this->disabled();
        }

        $rows = array_map(static fn (array $i): array => [
            'id'   => $i['id'],
            'name' => $i['name'],
            'logo' => $i['logo'] ?? null,
        ], $client->listInstitutions('es'));

        return $this->json($rows);
    }

    #[Route('/api/bank/connect', name: 'api_bank_connect', methods: ['POST'])]
    public function connect(Request $request, GoCardlessClient $client, CredentialStore $credentials, OpenBankingService $service, #[CurrentUser] User $user): JsonResponse
    {
        if (!$this->ready($client, $credentials, $user)) {
            return $this->disabled();
        }
        $data = json_decode($request->getContent(), true);
        $institutionId = is_array($data) ? (string) ($data['institutionId'] ?? '') : '';
        if ($institutionId === '') {
            return $this->json(['error' => 'institutionId is required'], 400);
        }
        $redirect = (is_array($data) ? ($data['redirect'] ?? null) : null)
            ?: $request->getSchemeAndHttpHost();

        try {
            return $this->json($service->begin($user, $institutionId, (string) $redirect));
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 502);
        }
    }

    #[Route('/api/bank/import', name: 'api_bank_import', methods: ['POST'])]
    public function import(Request $request, GoCardlessClient $client, CredentialStore $credentials, OpenBankingService $service, #[CurrentUser] User $user): JsonResponse
    {
        if (!$this->ready($client, $credentials, $user)) {
            return $this->disabled();
        }
        $data = json_decode($request->getContent(), true);
        $requisitionId = is_array($data) ? (string) ($data['requisitionId'] ?? '') : '';
        if ($requisitionId === '') {
            return $this->json(['error' => 'requisitionId is required'], 400);
        }

        try {
            return $this->json($service->import($user, $requisitionId));
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 502);
        }
    }

    /** Configure the client with the user's credentials and report whether open banking is usable. */
    private function ready(GoCardlessClient $client, CredentialStore $credentials, User $user): bool
    {
        $c = $credentials->gocardless($user);
        $client->configure($c['id'], $c['key']);

        return $client->isEnabled();
    }

    private function disabled(): JsonResponse
    {
        return $this->json(['error' => 'Open banking is not configured', 'enabled' => false], 503);
    }
}
