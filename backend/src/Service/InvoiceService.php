<?php

namespace App\Service;

use App\Entity\Customer;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Entity\InvoiceRecord;
use App\Entity\User;
use App\Repository\CustomerRepository;
use App\Repository\InvoiceRecordRepository;
use App\Repository\InvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Creates and issues invoices: resolves the customer, builds the lines, computes exact
 * totals (integer cents) and assigns the next correlative number in the series.
 * ES: Crea y emite facturas: resuelve el cliente, construye las líneas, calcula totales
 * exactos (céntimos enteros) y asigna el siguiente número correlativo de la serie.
 */
class InvoiceService
{
    public function __construct(
        private EntityManagerInterface $em,
        private InvoiceRepository $invoices,
        private CustomerRepository $customers,
        private InvoiceRecordRepository $records,
        private VerifactuHasher $hasher,
    ) {}

    /**
     * @param array{customer:array{name:string,taxId:string,address?:string,email?:string},
     *              series?:string, issuedAt?:string,
     *              lines:array<array{description:string,quantity?:int,unitPrice:string,vatRate:string}>} $data
     */
    public function create(User $user, array $data): Invoice
    {
        if (empty($data['lines'])) {
            throw new \InvalidArgumentException('An invoice needs at least one line');
        }

        $issuedAt = isset($data['issuedAt'])
            ? new \DateTimeImmutable($data['issuedAt'])
            : new \DateTimeImmutable('today');
        $series = trim((string) ($data['series'] ?? $issuedAt->format('Y')));

        $invoice = (new Invoice())
            ->setUser($user)
            ->setCustomer($this->resolveCustomer($user, $data))
            ->setSeries($series)
            ->setNumber($this->invoices->nextNumber($user, $series))
            ->setIssuedAt($issuedAt);

        $baseCents = 0;
        $vatCents = 0;
        foreach ($data['lines'] as $l) {
            $line = (new InvoiceLine())
                ->setDescription(trim((string) ($l['description'] ?? '')))
                ->setQuantity(max(1, (int) ($l['quantity'] ?? 1)))
                ->setUnitPrice($this->decimal($l['unitPrice'] ?? '0'))
                ->setVatRate($this->decimal($l['vatRate'] ?? '0'));
            $invoice->addLine($line);
            $baseCents += $line->baseCents();
            $vatCents += $line->vatCents();
        }

        $invoice
            ->setBaseTotal($this->euros($baseCents))
            ->setVatTotal($this->euros($vatCents))
            ->setTotal($this->euros($baseCents + $vatCents));

        $this->em->persist($invoice); // lines cascade-persist

        $record = $this->buildRecord($user, $invoice);
        $this->em->persist($record);

        $this->em->flush();

        return $invoice;
    }

    /**
     * Builds the Verifactu "registro de alta" for a freshly issued invoice: snapshots the fingerprint
     * fields, links to the previous record's hash and computes this record's hash — forming the chain.
     * ES: Construye el "registro de alta" Verifactu de una factura recién emitida: copia los campos de la
     * huella, enlaza con el hash del registro anterior y calcula el hash de este registro — la cadena.
     */
    private function buildRecord(User $user, Invoice $invoice): InvoiceRecord
    {
        $record = (new InvoiceRecord())
            ->setInvoice($invoice)
            ->setUser($user)
            ->setIssuerNif($user->getTaxId() ?: 'N/A')
            ->setFullNumber($invoice->getFullNumber())
            ->setIssueDate($invoice->getIssuedAt()->format('d-m-Y'))
            ->setVatTotal($invoice->getVatTotal())
            ->setTotal($invoice->getTotal())
            ->setGeneratedAt((new \DateTimeImmutable())->format('c'))
            ->setPreviousHash($this->records->lastForUser($user)?->getHash());

        return $record->setHash($this->hasher->fingerprint($record));
    }

    /**
     * Resolve the invoice's customer: prefer an explicit existing `customerId`, else get-or-create by
     * taxId from the inline `customer` payload.
     * ES: Resuelve el cliente: prioriza un `customerId` existente, si no, busca-o-crea por NIF desde el
     * `customer` del payload.
     *
     * @param array<string,mixed> $data
     */
    private function resolveCustomer(User $user, array $data): Customer
    {
        $customerId = $data['customerId'] ?? ($data['customer']['id'] ?? null);
        if ($customerId !== null) {
            $existing = $this->customers->find((int) $customerId);
            if ($existing !== null && $existing->getUser() === $user) {
                return $existing;
            }
        }

        $c = is_array($data['customer'] ?? null) ? $data['customer'] : [];
        $taxId = trim((string) ($c['taxId'] ?? ''));
        $existing = $taxId !== '' ? $this->customers->findOneBy(['user' => $user, 'taxId' => $taxId]) : null;
        if ($existing !== null) {
            return $existing;
        }

        $customer = (new Customer())
            ->setUser($user)
            ->setName(trim((string) ($c['name'] ?? 'Cliente')))
            ->setTaxId($taxId !== '' ? $taxId : 'N/A')
            ->setAddress(isset($c['address']) ? trim((string) $c['address']) : null)
            ->setEmail(isset($c['email']) ? trim((string) $c['email']) : null);
        $this->em->persist($customer);

        return $customer;
    }

    private function decimal(string|int|float $v): string
    {
        return number_format((float) $v, 2, '.', '');
    }

    private function euros(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
