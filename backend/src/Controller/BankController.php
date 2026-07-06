<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\GoCardlessClient;
use App\Service\OpenBankingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Open-banking endpoints (GoCardless Bank Account Data). Every action but /status requires the feature
 * to be configured; otherwise it returns 503 so the frontend can show a disabled state.
 * ES: Endpoints de banca abierta. Todo salvo /status requiere la función configurada; si no, devuelve 503
 * para que el frontend muestre el estado deshabilitado.
 */
class BankController extends AbstractController
{
    #[Route('/api/bank/status', name: 'api_bank_status', methods: ['GET'])]
    public function status(GoCardlessClient $client): JsonResponse
    {
        return $this->json(['enabled' => $client->isEnabled()]);
    }

    #[Route('/api/bank/institutions', name: 'api_bank_institutions', methods: ['GET'])]
    public function institutions(GoCardlessClient $client): JsonResponse
    {
        if (!$client->isEnabled()) {
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
    public function connect(Request $request, GoCardlessClient $client, OpenBankingService $service, #[CurrentUser] User $user): JsonResponse
    {
        if (!$client->isEnabled()) {
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
    public function import(Request $request, GoCardlessClient $client, OpenBankingService $service, #[CurrentUser] User $user): JsonResponse
    {
        if (!$client->isEnabled()) {
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

    private function disabled(): JsonResponse
    {
        return $this->json(['error' => 'Open banking is not configured', 'enabled' => false], 503);
    }
}
