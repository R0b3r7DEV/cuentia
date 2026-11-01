<?php

namespace App\Service;

use App\Entity\Customer;
use App\Entity\Invoice;
use App\Entity\User;
use App\Entity\WorkOrder;
use App\Entity\WorkOrderLine;
use App\Entity\WorkOrderPhoto;
use App\Repository\CustomerRepository;
use App\Time\BusinessClock;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Creates work orders, moves them through their lifecycle and converts a finished one into a real invoice —
 * idempotently, reusing the exact pattern of QuoteService::convert(): a `convertedInvoice` link means
 * "convert twice ⇒ same invoice".
 *
 * ES: Crea partes de trabajo, los mueve por su ciclo de vida y convierte uno terminado en factura real — de
 * forma idempotente, con el mismo patrón de QuoteService::convert(): el enlace `convertedInvoice` garantiza
 * que convertir dos veces no duplica.
 *
 * Errors carry a fixed code as the message, safe to return to the client:
 * InvalidArgumentException → bad input (400); DomainException → not allowed in the current state (409).
 * ES: Los errores llevan un código fijo como mensaje, seguro para devolverlo al cliente:
 * InvalidArgumentException → datos no válidos (400); DomainException → no permitido en el estado actual (409).
 */
class WorkOrderService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CustomerRepository $customers,
        private InvoiceService $invoices,
        private ?ImageSanitizer $images = null,
        private ?BusinessClock $clock = null,
    ) {
        $this->images ??= new ImageSanitizer();
        $this->clock ??= new BusinessClock();
    }

    /** @param array<string,mixed> $data */
    public function create(User $user, array $data): WorkOrder
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title_required');
        }

        // A new order starts pending, or straight away "in progress" when it is created at the site.
        // ES: Un parte nuevo empieza pendiente, o directamente "en curso" si se crea a pie de obra.
        $status = ($data['status'] ?? null) === 'en_curso' ? 'en_curso' : 'pendiente';

        $wo = (new WorkOrder())
            ->setUser($user)
            ->setCustomer($this->resolveCustomer($user, $data))
            ->setTitle($title)
            ->setDescription(isset($data['description']) ? trim((string) $data['description']) : null)
            ->setScheduledAt($this->date($data['scheduledAt'] ?? null))
            ->setLaborHours($this->amount($data['laborHours'] ?? '0'))
            ->setLaborRate($this->amount($data['laborRate'] ?? $user->getDefaultLaborRate() ?? '0'))
            ->setLaborVatRate($this->amount($data['laborVatRate'] ?? '21'))
            ->setStatus($status);

        $this->applyLines($wo, is_array($data['lines'] ?? null) ? $data['lines'] : []);

        $this->em->persist($wo);
        $this->em->flush();

        return $wo;
    }

    /**
     * Edit the content of an order. The status is NOT changed here: that goes through transition().
     * ES: Edita el contenido de un parte. El estado NO se cambia aquí: para eso está transition().
     *
     * @param array<string,mixed> $data
     */
    public function update(WorkOrder $wo, array $data): WorkOrder
    {
        $this->assertEditable($wo);

        if (array_key_exists('title', $data) && trim((string) $data['title']) !== '') {
            $wo->setTitle(trim((string) $data['title']));
        }
        if (array_key_exists('description', $data)) {
            $wo->setDescription(trim((string) $data['description']) ?: null);
        }
        if (array_key_exists('scheduledAt', $data)) {
            $wo->setScheduledAt($this->date($data['scheduledAt']));
        }
        if (array_key_exists('customerId', $data) || array_key_exists('customer', $data)) {
            $wo->setCustomer($this->resolveCustomer($wo->getUser(), $data));
        }
        foreach (['laborHours', 'laborRate', 'laborVatRate'] as $k) {
            if (array_key_exists($k, $data)) {
                $wo->{'set' . ucfirst($k)}($this->amount($data[$k]));
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
     * Move an order to another state, following WorkOrder::TRANSITIONS. Finishing needs the client's
     * signature, unless the electrician says the client was not there ($withoutSignature). Reopening a
     * finished order drops its signature: it covered work that is about to change.
     *
     * ES: Mueve un parte a otro estado según WorkOrder::TRANSITIONS. Terminar exige la firma del cliente,
     * salvo que el electricista indique que el cliente no estaba ($withoutSignature). Reabrir un parte
     * terminado quita su firma: cubría un trabajo que va a cambiar.
     */
    public function transition(WorkOrder $wo, string $status, bool $withoutSignature = false): WorkOrder
    {
        if (!$wo->canTransitionTo($status)) {
            throw new \DomainException('invalid_transition');
        }
        if ($status === 'terminado') {
            if (!$wo->hasSignature() && !$withoutSignature) {
                throw new \DomainException('signature_required');
            }
            if (!$wo->hasSignature()) {
                $wo->waiveSignature();
            }
        }
        if ($wo->getStatus() === 'terminado' && $status === 'en_curso') {
            $wo->clearSignature();
        }

        $wo->setStatus($status);
        $this->em->flush();

        return $wo;
    }

    /** Store the client's signature (a PNG from the canvas) and who signed. */
    public function sign(WorkOrder $wo, string $png, string $signedBy): WorkOrder
    {
        $this->assertEditable($wo);
        $signedBy = trim($signedBy);
        if ($signedBy === '' || mb_strlen($signedBy) > 120) {
            throw new \InvalidArgumentException('signer_required');
        }

        $wo->sign($this->images->signature($png), $signedBy);
        $this->em->flush();

        return $wo;
    }

    public function clearSignature(WorkOrder $wo): WorkOrder
    {
        $this->assertEditable($wo);
        $wo->clearSignature();
        $this->em->flush();

        return $wo;
    }

    /** Attach a site photo: sanitised and re-encoded before it is stored. */
    public function addPhoto(WorkOrder $wo, string $bytes): WorkOrderPhoto
    {
        $this->assertEditable($wo);
        if ($wo->getPhotos()->count() >= WorkOrder::MAX_PHOTOS) {
            throw new \DomainException('photo_limit');
        }

        $clean = $this->images->photo($bytes);
        $photo = (new WorkOrderPhoto())->setImage($clean['jpeg'], $clean['width'], $clean['height']);
        $wo->addPhoto($photo);
        $this->em->persist($photo);
        $this->em->flush();

        return $photo;
    }

    public function removePhoto(WorkOrder $wo, WorkOrderPhoto $photo): void
    {
        $this->assertEditable($wo);
        $wo->removePhoto($photo);
        $this->em->remove($photo);
        $this->em->flush();
    }

    /**
     * Convert a finished work order into a real invoice (idempotent). Materials become invoice lines; the
     * labour hours become one "Mano de obra" line (hours × rate). Marks the order 'facturado' and links it.
     * ES: Convierte un parte terminado en factura real (idempotente). Los materiales pasan a líneas; la mano
     * de obra a una línea "Mano de obra" (horas × precio). Marca el parte 'facturado' y lo enlaza.
     */
    public function convert(User $user, WorkOrder $wo): Invoice
    {
        // Checked first: converting an already-invoiced order returns its invoice, whatever its state.
        if ($wo->getConvertedInvoice() !== null) {
            return $wo->getConvertedInvoice();
        }
        if ($wo->getStatus() !== 'terminado') {
            throw new \DomainException('not_finished');
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
                'unitPrice'   => sprintf('%d.%02d', intdiv($laborCents, 100), $laborCents % 100),
                'vatRate'     => $wo->getLaborVatRate(),
            ];
        }

        if ($lines === []) {
            throw new \InvalidArgumentException('nothing_to_invoice');
        }

        $invoice = $this->invoices->create($user, [
            'customerId' => $wo->getCustomer()?->getId(),
            'lines'      => $lines,
        ]);

        $wo->setStatus('facturado')->setConvertedInvoice($invoice);
        $this->em->flush();

        return $invoice;
    }

    private function assertEditable(WorkOrder $wo): void
    {
        if (!$wo->isEditable()) {
            throw new \DomainException('not_editable');
        }
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
                ->setUnitPrice($this->amount($l['unitPrice'] ?? '0'))
                ->setVatRate($this->amount($l['vatRate'] ?? '0')));
        }
    }

    /** @param array<string,mixed> $data */
    private function resolveCustomer(?User $user, array $data): Customer
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

    private function date(mixed $v): ?\DateTimeImmutable
    {
        if (empty($v)) {
            return null;
        }
        try {
            // "2026-11-01T10:30" from the form is 10:30 in Spain; stored as the UTC instant.
            return $this->clock->parseLocal((string) $v);
        } catch (\Exception) {
            throw new \InvalidArgumentException('invalid_date');
        }
    }

    /** A money/hours amount as a 2-decimal string; never negative. Accepts "1,5" as well as "1.5". */
    private function amount(mixed $v): string
    {
        $s = str_replace(',', '.', trim((string) $v));
        if ($s === '') {
            $s = '0';
        }
        if (!is_numeric($s) || (float) $s < 0) {
            throw new \InvalidArgumentException('invalid_amount');
        }

        return number_format((float) $s, 2, '.', '');
    }
}
