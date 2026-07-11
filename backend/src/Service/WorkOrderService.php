<?php

namespace App\Service;

use App\Entity\Customer;
use App\Entity\Invoice;
use App\Entity\User;
use App\Entity\WorkOrder;
use App\Entity\WorkOrderLine;
use App\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Creates work orders and converts a finished one into a real invoice — idempotently, reusing the exact
 * pattern of QuoteService::convert(): a `convertedInvoice` link means "convert twice ⇒ same invoice".
 *
 * ES: Crea partes de trabajo y convierte uno terminado en factura real — de forma idempotente, con el mismo
 * patrón de QuoteService::convert(): el enlace `convertedInvoice` garantiza que convertir dos veces no
 * duplica.
 */
class WorkOrderService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CustomerRepository $customers,
        private InvoiceService $invoices,
    ) {}

    /** @param array<string,mixed> $data */
    public function create(User $user, array $data): WorkOrder
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('A work order needs a title');
        }

        $wo = (new WorkOrder())
            ->setUser($user)
            ->setCustomer($this->resolveCustomer($user, $data))
            ->setTitle($title)
            ->setDescription(isset($data['description']) ? trim((string) $data['description']) : null)
            ->setScheduledAt(!empty($data['scheduledAt']) ? new \DateTimeImmutable((string) $data['scheduledAt']) : null)
            ->setLaborHours($this->decimal($data['laborHours'] ?? '0'))
            ->setLaborRate($this->decimal($data['laborRate'] ?? '0'))
            ->setLaborVatRate($this->decimal($data['laborVatRate'] ?? '21'))
            ->setStatus((string) ($data['status'] ?? 'pendiente'));

        $this->applyLines($wo, is_array($data['lines'] ?? null) ? $data['lines'] : []);

        $this->em->persist($wo);
        $this->em->flush();

        return $wo;
    }

    /** @param array<string,mixed> $data */
    public function update(WorkOrder $wo, array $data): WorkOrder
    {
        if (array_key_exists('title', $data) && trim((string) $data['title']) !== '') {
            $wo->setTitle(trim((string) $data['title']));
        }
        if (array_key_exists('description', $data)) {
            $wo->setDescription(trim((string) $data['description']) ?: null);
        }
        if (array_key_exists('status', $data)) {
            $wo->setStatus((string) $data['status']);
        }
        if (array_key_exists('scheduledAt', $data)) {
            $wo->setScheduledAt(!empty($data['scheduledAt']) ? new \DateTimeImmutable((string) $data['scheduledAt']) : null);
        }
        foreach (['laborHours', 'laborRate', 'laborVatRate'] as $k) {
            if (array_key_exists($k, $data)) {
                $wo->{'set' . ucfirst($k)}($this->decimal($data[$k]));
            }
        }
        if (array_key_exists('lines', $data)) {
            foreach ($wo->getLines()->toArray() as $line) {
                $wo->getLines()->removeElement($line);
                $this->em->remove($line);
            }
            $this->applyLines($wo, is_array($data['lines']) ? $data['lines'] : []);
        }
        $this->em->flush();

        return $wo;
    }

    /**
     * Convert a work order into a real invoice (idempotent). Materials become invoice lines; the labour
     * hours become one "Mano de obra" line (hours × rate). Marks the order 'facturado' and links it.
     * ES: Convierte un parte en factura real (idempotente). Los materiales pasan a líneas; la mano de obra a
     * una línea "Mano de obra" (horas × precio). Marca el parte 'facturado' y lo enlaza.
     */
    public function convert(User $user, WorkOrder $wo): Invoice
    {
        if ($wo->getConvertedInvoice() !== null) {
            return $wo->getConvertedInvoice();
        }

        $lines = [];
        foreach ($wo->getLines() as $l) {
            $lines[] = [
                'description' => $l->getDescription(),
                'quantity'    => $l->getQuantity(),
                'unitPrice'   => $l->getUnitPrice(),
                'vatRate'     => $l->getVatRate(),
            ];
        }

        $laborCents = $wo->laborBaseCents();
        if ($laborCents > 0) {
            $lines[] = [
                'description' => 'Mano de obra (' . rtrim(rtrim($wo->getLaborHours(), '0'), '.') . ' h)',
                'quantity'    => 1,
                'unitPrice'   => number_format($laborCents / 100, 2, '.', ''),
                'vatRate'     => $wo->getLaborVatRate(),
            ];
        }

        if ($lines === []) {
            throw new \InvalidArgumentException('A work order needs materials or labour to be invoiced');
        }

        $invoice = $this->invoices->create($user, [
            'customerId' => $wo->getCustomer()?->getId(),
            'lines'      => $lines,
        ]);

        $wo->setStatus('facturado')->setConvertedInvoice($invoice);
        $this->em->flush();

        return $invoice;
    }

    /** @param array<int,array<string,mixed>> $lines */
    private function applyLines(WorkOrder $wo, array $lines): void
    {
        foreach ($lines as $l) {
            if (!is_array($l)) {
                continue;
            }
            $wo->addLine((new WorkOrderLine())
                ->setDescription(trim((string) ($l['description'] ?? '')))
                ->setQuantity(max(1, (int) ($l['quantity'] ?? 1)))
                ->setUnitPrice($this->decimal($l['unitPrice'] ?? '0'))
                ->setVatRate($this->decimal($l['vatRate'] ?? '0')));
        }
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
}
