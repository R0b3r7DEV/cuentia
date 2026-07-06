<?php

namespace App\Service;

use App\Entity\Customer;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Entity\User;
use App\Repository\CustomerRepository;
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
            ->setCustomer($this->resolveCustomer($user, $data['customer'] ?? []))
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
        $this->em->flush();

        return $invoice;
    }

    /** @param array{name?:string,taxId?:string,address?:string,email?:string} $data */
    private function resolveCustomer(User $user, array $data): Customer
    {
        $taxId = trim((string) ($data['taxId'] ?? ''));
        $existing = $taxId !== '' ? $this->customers->findOneBy(['user' => $user, 'taxId' => $taxId]) : null;
        if ($existing !== null) {
            return $existing;
        }

        $customer = (new Customer())
            ->setUser($user)
            ->setName(trim((string) ($data['name'] ?? 'Cliente')))
            ->setTaxId($taxId !== '' ? $taxId : 'N/A')
            ->setAddress(isset($data['address']) ? trim((string) $data['address']) : null)
            ->setEmail(isset($data['email']) ? trim((string) $data['email']) : null);
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
