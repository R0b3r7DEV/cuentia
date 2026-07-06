<?php

namespace App\Service;

use App\Entity\InvoiceRecord;

/**
 * Verifies the integrity of a chain of invoice records.
 *
 * EN: Walks the records in issue order and checks two things per record: (1) its stored hash still
 * matches the hash recomputed from its own snapshot — catches a tampered field; (2) its `previousHash`
 * matches the actual hash of the record before it — catches a deleted or reordered record. Returns the
 * first record that fails (with the reason) or null if the whole chain is intact.
 *
 * ES: Recorre los registros en orden de emisión y comprueba dos cosas por registro: (1) su hash guardado
 * sigue coincidiendo con el hash recalculado desde su propia copia — detecta un campo manipulado; (2) su
 * `previousHash` coincide con el hash real del registro anterior — detecta un registro borrado o
 * reordenado. Devuelve el primer registro que falla (con el motivo) o null si la cadena está intacta.
 */
class VerifactuChain
{
    public function __construct(private VerifactuHasher $hasher) {}

    /**
     * @param InvoiceRecord[] $records in issue order (oldest first)
     * @return array{ok:bool, brokenAt?:string, reason?:string, count:int}
     */
    public function verify(array $records): array
    {
        $previousHash = null;
        foreach ($records as $r) {
            if ($r->getPreviousHash() !== $previousHash) {
                return $this->broken($r, 'previous_hash_mismatch', count($records));
            }
            if (!hash_equals($r->getHash(), $this->hasher->fingerprint($r))) {
                return $this->broken($r, 'record_tampered', count($records));
            }
            $previousHash = $r->getHash();
        }

        return ['ok' => true, 'count' => count($records)];
    }

    /** @return array{ok:bool, brokenAt:string, reason:string, count:int} */
    private function broken(InvoiceRecord $r, string $reason, int $count): array
    {
        return ['ok' => false, 'brokenAt' => $r->getFullNumber(), 'reason' => $reason, 'count' => $count];
    }
}
