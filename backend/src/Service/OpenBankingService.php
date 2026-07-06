<?php

namespace App\Service;

use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Open-banking import: turns an authorized GoCardless requisition into the user's transactions.
 *
 * EN: `begin()` creates the requisition and returns the hosted link the user visits to authorize their
 * bank; after that, `import()` pulls the booked movements from every linked account and maps them to our
 * Transaction rows, skipping any already imported (dedup by external id).
 * ES: `begin()` crea la requisition y devuelve el enlace que el usuario visita para autorizar su banco;
 * después, `import()` trae los movimientos contabilizados de cada cuenta enlazada y los mapea a nuestras
 * filas Transaction, saltando los ya importados (dedup por id externo).
 */
class OpenBankingService
{
    public function __construct(
        private GoCardlessClient $client,
        private TransactionRepository $transactions,
        private EntityManagerInterface $em,
    ) {}

    /**
     * Start a bank connection. / Inicia una conexión bancaria.
     *
     * @return array{link:string, requisitionId:string}
     */
    public function begin(User $user, string $institutionId, string $redirect): array
    {
        $reference = 'cuentia-' . ($user->getId() ?? '0') . '-' . bin2hex(random_bytes(4));
        $req = $this->client->createRequisition($institutionId, $redirect, $reference);

        return ['link' => $req['link'], 'requisitionId' => $req['id']];
    }

    /**
     * Import the booked transactions of every account in an authorized requisition.
     * ES: Importa los movimientos contabilizados de cada cuenta de una requisition autorizada.
     *
     * @return array{imported:int, skipped:int}
     */
    public function import(User $user, string $requisitionId): array
    {
        $req = $this->client->getRequisition($requisitionId);
        $existing = $this->transactions->existingExternalIds($user);

        $imported = 0;
        $skipped = 0;
        foreach ($req['accounts'] ?? [] as $accountId) {
            $data = $this->client->getAccountTransactions($accountId);
            foreach ($data['transactions']['booked'] ?? [] as $raw) {
                $tx = $this->toTransaction($raw, $user, $accountId);
                if ($tx === null) {
                    continue; // unmappable row (missing amount/date)
                }
                $extId = $tx->getExternalId();
                if ($extId !== null && isset($existing[$extId])) {
                    $skipped++;
                    continue;
                }
                $this->em->persist($tx);
                if ($extId !== null) {
                    $existing[$extId] = true; // guard against duplicates within the same import too
                }
                $imported++;
            }
        }
        $this->em->flush();

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * Map one GoCardless booked transaction to a Transaction, or null if it lacks an amount/date.
     * Public so the mapping (the tricky part) can be unit-tested directly.
     * ES: Mapea un movimiento de GoCardless a Transaction, o null si le falta importe/fecha. Público
     * para poder testear el mapeo (lo delicado) de forma directa.
     *
     * @param array<string,mixed> $raw
     */
    public function toTransaction(array $raw, User $user, string $accountId): ?Transaction
    {
        $amount = $raw['transactionAmount']['amount'] ?? null;
        $date = $raw['bookingDate'] ?? $raw['valueDate'] ?? null;
        if ($amount === null || $date === null) {
            return null;
        }
        try {
            $booked = (new \DateTimeImmutable((string) $date))->setTime(0, 0);
        } catch (\Throwable) {
            return null;
        }

        return (new Transaction())
            ->setBookedAt($booked)
            ->setDescription($this->description($raw))
            ->setAmount($this->money((string) $amount))
            ->setCurrency((string) ($raw['transactionAmount']['currency'] ?? 'EUR'))
            ->setImportedFrom('openbanking')
            ->setExternalId($this->externalId($raw, $accountId))
            ->setUser($user);
    }

    /** @param array<string,mixed> $raw */
    private function description(array $raw): string
    {
        $d = $raw['remittanceInformationUnstructured'] ?? null;
        if (($d === null || $d === '') && !empty($raw['remittanceInformationUnstructuredArray'])) {
            $d = implode(' ', (array) $raw['remittanceInformationUnstructuredArray']);
        }
        if ($d === null || $d === '') {
            $d = $raw['creditorName'] ?? $raw['debtorName'] ?? '';
        }

        return trim((string) $d) ?: '(sin concepto)';
    }

    /** @param array<string,mixed> $raw */
    private function externalId(array $raw, string $accountId): ?string
    {
        $id = $raw['transactionId'] ?? $raw['internalTransactionId'] ?? null;

        return $id !== null ? substr($accountId . ':' . $id, 0, 128) : null;
    }

    /** Keep money as an exact 2-decimal string; bank amounts already come as decimal strings. */
    private function money(string $v): string
    {
        $v = trim($v);

        return is_numeric($v) ? number_format((float) $v, 2, '.', '') : '0.00';
    }
}
