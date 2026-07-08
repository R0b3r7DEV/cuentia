<?php

namespace App\Service;

use App\Entity\Customer;
use App\Entity\Invoice;
use App\Entity\Quote;
use App\Entity\QuoteLine;
use App\Entity\User;
use App\Repository\CustomerRepository;
use App\Repository\QuoteRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Creates quotes and converts an accepted quote into a real Verifactu invoice.
 * ES: Crea presupuestos y convierte un presupuesto aceptado en una factura Verifactu real.
 */
class QuoteService
{
    public function __construct(
        private EntityManagerInterface $em,
        private QuoteRepository $quotes,
        private CustomerRepository $customers,
        private InvoiceService $invoices,
    ) {}

    /**
     * @param array<string,mixed> $data
     */
    public function create(User $user, array $data): Quote
    {
        if (empty($data['lines'])) {
            throw new \InvalidArgumentException('A quote needs at least one line');
        }

        $issuedAt = isset($data['issuedAt']) ? new \DateTimeImmutable((string) $data['issuedAt']) : new \DateTimeImmutable('today');
        $series = trim((string) ($data['series'] ?? ('P' . $issuedAt->format('Y'))));
        $validUntil = !empty($data['validUntil']) ? new \DateTimeImmutable((string) $data['validUntil']) : null;
        $requested = (string) ($data['status'] ?? 'draft');
        $status = in_array($requested, ['draft', 'sent'], true) ? $requested : 'draft';

        $quote = (new Quote())
            ->setUser($user)
            ->setCustomer($this->resolveCustomer($user, $data))
            ->setSeries($series)
            ->setNumber($this->quotes->nextNumber($user, $series))
            ->setIssuedAt($issuedAt)
            ->setValidUntil($validUntil)
            ->setStatus($status);

        $baseCents = 0;
        $vatCents = 0;
        foreach ($data['lines'] as $l) {
            $line = (new QuoteLine())
                ->setDescription(trim((string) ($l['description'] ?? '')))
                ->setQuantity(max(1, (int) ($l['quantity'] ?? 1)))
                ->setUnitPrice($this->decimal($l['unitPrice'] ?? '0'))
                ->setVatRate($this->decimal($l['vatRate'] ?? '0'));
            $quote->addLine($line);
            $baseCents += $line->baseCents();
            $vatCents += $line->vatCents();
        }

        $quote
            ->setBaseTotal($this->euros($baseCents))
            ->setVatTotal($this->euros($vatCents))
            ->setTotal($this->euros($baseCents + $vatCents));

        $this->em->persist($quote);
        $this->em->flush();

        return $quote;
    }

    /**
     * Convert an accepted quote into a real Verifactu invoice (idempotent — a quote converts once).
     * ES: Convierte un presupuesto aceptado en una factura Verifactu real (idempotente).
     */
    public function convert(User $user, Quote $quote): Invoice
    {
        if ($quote->getConvertedInvoice() !== null) {
            return $quote->getConvertedInvoice();
        }

        $lines = [];
        foreach ($quote->getLines() as $l) {
            $lines[] = [
                'description' => $l->getDescription(),
                'quantity'    => $l->getQuantity(),
                'unitPrice'   => $l->getUnitPrice(),
                'vatRate'     => $l->getVatRate(),
            ];
        }

        $invoice = $this->invoices->create($user, [
            'customerId' => $quote->getCustomer()?->getId(),
            'lines'      => $lines,
        ]);

        $quote->setStatus('converted')->setConvertedInvoice($invoice);
        $this->em->flush();

        return $invoice;
    }

    /** @param array<string,mixed> $data */
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
