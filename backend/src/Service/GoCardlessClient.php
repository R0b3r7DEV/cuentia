<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A thin client for the GoCardless Bank Account Data API (formerly Nordigen), which brokers real
 * open-banking connections to European banks.
 *
 * EN: The app needs a single pair of API credentials (secret id + key) to broker connections for all
 * its users; each end user then authorizes their own bank through a hosted link. The feature is
 * **disabled unless those credentials are set** (`isEnabled()`), so the app runs fine without them.
 * ES: La app necesita un único par de credenciales de API (secret id + key) para intermediar las
 * conexiones de todos sus usuarios; cada usuario final autoriza su propio banco por un enlace alojado.
 * La función está **deshabilitada salvo que estén esas credenciales** (`isEnabled()`).
 *
 * NOTE: exercised in tests against mocked HTTP responses matching the documented API shape; it has not
 * been run against the live GoCardless service (no credentials during development). / Probado contra
 * respuestas HTTP simuladas; no se ha ejecutado contra el servicio real (sin credenciales).
 */
class GoCardlessClient
{
    private const BASE = 'https://bankaccountdata.gocardless.com/api/v2';

    private ?string $token = null;

    public function __construct(
        private HttpClientInterface $http,
        private string $secretId = '',
        private string $secretKey = '',
    ) {}

    /**
     * Set the credentials to use for the following calls (per-user BYOK). Resets any cached token.
     * ES: Fija las credenciales a usar en las siguientes llamadas (BYOK por usuario). Resetea el token.
     */
    public function configure(string $secretId, string $secretKey): void
    {
        $this->secretId = $secretId;
        $this->secretKey = $secretKey;
        $this->token = null;
    }

    /** True only when both credentials are configured. / Verdadero solo si ambas credenciales están. */
    public function isEnabled(): bool
    {
        return $this->secretId !== '' && $this->secretKey !== '';
    }

    /** @return array<int, array{id:string, name:string, logo?:string}> */
    public function listInstitutions(string $country = 'es'): array
    {
        return $this->get('/institutions/', ['country' => $country]);
    }

    /**
     * Create a requisition: the object that yields a hosted link where the end user authorizes their bank.
     * ES: Crea una requisition: el objeto que da un enlace donde el usuario final autoriza su banco.
     *
     * @return array{id:string, link:string}
     */
    public function createRequisition(string $institutionId, string $redirect, string $reference): array
    {
        return $this->post('/requisitions/', [
            'redirect' => $redirect,
            'institution_id' => $institutionId,
            'reference' => $reference,
        ]);
    }

    /** @return array{status:string, accounts:array<int,string>} */
    public function getRequisition(string $id): array
    {
        return $this->get("/requisitions/$id/");
    }

    /** @return array{transactions:array{booked?:array<int,array<string,mixed>>, pending?:array<int,array<string,mixed>>}} */
    public function getAccountTransactions(string $accountId): array
    {
        return $this->get("/accounts/$accountId/transactions/");
    }

    // --- internals / internos -------------------------------------------------

    private function accessToken(): string
    {
        if ($this->token !== null) {
            return $this->token;
        }
        if (!$this->isEnabled()) {
            throw new \RuntimeException('GoCardless is not configured');
        }

        $data = $this->http->request('POST', self::BASE . '/token/new/', [
            'json' => ['secret_id' => $this->secretId, 'secret_key' => $this->secretKey],
        ])->toArray();

        return $this->token = $data['access'];
    }

    /** @param array<string,mixed> $query @return array<mixed> */
    private function get(string $path, array $query = []): array
    {
        return $this->http->request('GET', self::BASE . $path, [
            'query' => $query,
            'auth_bearer' => $this->accessToken(),
        ])->toArray();
    }

    /** @param array<string,mixed> $body @return array<mixed> */
    private function post(string $path, array $body): array
    {
        return $this->http->request('POST', self::BASE . $path, [
            'json' => $body,
            'auth_bearer' => $this->accessToken(),
        ])->toArray();
    }
}
