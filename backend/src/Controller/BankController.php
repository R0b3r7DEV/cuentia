<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\CredentialStore;
use App\Service\GoCardlessClient;
use App\Service\OpenBankingService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
    public function connect(Request $request, GoCardlessClient $client, CredentialStore $credentials, OpenBankingService $service, LoggerInterface $logger,
        #[Autowire(env: 'FRONTEND_URL')] string $frontendUrl, #[CurrentUser] User $user): JsonResponse
    {
        if (!$this->ready($client, $credentials, $user)) {
            return $this->disabled();
        }
        $data = json_decode($request->getContent(), true);
        $institutionId = is_array($data) ? (string) ($data['institutionId'] ?? '') : '';
        if ($institutionId === '') {
            return $this->json(['error' => 'institutionId is required'], 400);
        }
        // The bank sends the user back here: only to our own frontend, never to a URL chosen by the request.
        // ES: El banco devuelve al usuario aquí: solo a nuestro frontend, nunca a una URL que diga la petición.
        $redirect = (string) (is_array($data) ? ($data['redirect'] ?? '') : '');
        if (!self::sameOrigin($redirect, $frontendUrl)) {
            $redirect = rtrim($frontendUrl, '/') . '/movements';
        }

        try {
            return $this->json($service->begin($user, $institutionId, $redirect));
        } catch (\Throwable $e) {
            $logger->error('Open banking: could not start the connection', ['exception' => $e]);

            return $this->json(['error' => 'bank_unavailable'], 502);
        }
    }

    #[Route('/api/bank/import', name: 'api_bank_import', methods: ['POST'])]
    public function import(Request $request, GoCardlessClient $client, CredentialStore $credentials, OpenBankingService $service, LoggerInterface $logger, #[CurrentUser] User $user): JsonResponse
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
            // The real error goes to the log, not to the browser. / El error real, al log; no al navegador.
            $logger->error('Open banking: import failed', ['exception' => $e]);

            return $this->json(['error' => 'bank_unavailable'], 502);
        }
    }

    /** Same scheme, host and port as our frontend. / Mismo esquema, host y puerto que nuestro frontend. */
    private static function sameOrigin(string $url, string $frontendUrl): bool
    {
        $a = parse_url($url);
        $b = parse_url($frontendUrl);
        if (!is_array($a) || !is_array($b) || !isset($a['scheme'], $a['host'], $b['scheme'], $b['host'])) {
            return false;
        }

        return strtolower($a['scheme']) === strtolower($b['scheme']) && strtolower($a['host']) === strtolower($b['host'])
            && ($a['port'] ?? null) === ($b['port'] ?? null);
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
