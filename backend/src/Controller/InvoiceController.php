<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\InvoiceRecord;
use App\Entity\User;
use App\Repository\InvoiceRecordRepository;
use App\Repository\InvoiceRepository;
use App\Service\InvoicePdf;
use App\Service\InvoiceService;
use App\Service\VerifactuChain;
use App\Service\VerifactuQr;
use App\Service\VerifactuXml;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class InvoiceController extends AbstractController
{
    #[Route('/api/invoices', name: 'api_invoices_list', methods: ['GET'])]
    public function list(InvoiceRepository $repo, #[CurrentUser] User $user): JsonResponse
    {
        $rows = array_map(
            static fn (Invoice $i): array => [
                'id'       => $i->getId(),
                'number'   => $i->getFullNumber(),
                'customer' => $i->getCustomer()?->getName(),
                'issuedAt' => $i->getIssuedAt()->format('Y-m-d'),
                'total'    => $i->getTotal(),
                'status'   => $i->getStatus(),
            ],
            $repo->findForUser($user),
        );

        return $this->json($rows);
    }

    #[Route('/api/invoices', name: 'api_invoices_create', methods: ['POST'])]
    public function create(Request $request, InvoiceService $service, InvoiceRecordRepository $records, #[CurrentUser] User $user): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON'], 400);
        }
        try {
            $invoice = $service->create($user, $data);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        return $this->json($this->detail($invoice, $records), 201);
    }

    /** Verify the integrity of the user's whole invoice chain (Verifactu). */
    #[Route('/api/invoices/verify', name: 'api_invoices_verify', methods: ['GET'])]
    public function verify(InvoiceRecordRepository $records, VerifactuChain $chain, #[CurrentUser] User $user): JsonResponse
    {
        return $this->json($chain->verify($records->chainForUser($user)));
    }

    #[Route('/api/invoices/{id}', name: 'api_invoices_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function get(int $id, InvoiceRepository $repo, InvoiceRecordRepository $records, #[CurrentUser] User $user): JsonResponse
    {
        $invoice = $repo->find($id);
        if ($invoice === null || $invoice->getUser() !== $user) {
            return $this->json(['error' => 'Not found'], 404);
        }

        return $this->json($this->detail($invoice, $records));
    }

    /** The Verifactu QR (SVG). Only exposed in Verifactu demo mode — a standard invoice carries no QR. */
    #[Route('/api/invoices/{id}/qr', name: 'api_invoices_qr', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function qr(int $id, InvoiceRepository $repo, InvoiceRecordRepository $records, VerifactuQr $qr, #[CurrentUser] User $user): Response
    {
        if ($user->getBillingMode() !== 'verifactu') {
            return $this->json(['error' => 'verifactu_mode_only', 'message' => 'El QR solo existe en modo Verifactu (demostración).'], 403);
        }
        $record = $this->ownedRecord($id, $repo, $records, $user);
        if ($record === null) {
            return $this->json(['error' => 'Not found'], 404);
        }

        return new Response($qr->svg($record), 200, ['Content-Type' => 'image/svg+xml']);
    }

    /** The Verifactu RegistroAlta XML. Only in Verifactu demo mode — a standard invoice has no XML record. */
    #[Route('/api/invoices/{id}/xml', name: 'api_invoices_xml', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function xml(int $id, InvoiceRepository $repo, InvoiceRecordRepository $records, VerifactuXml $xml, #[CurrentUser] User $user): Response
    {
        if ($user->getBillingMode() !== 'verifactu') {
            return $this->json(['error' => 'verifactu_mode_only', 'message' => 'El XML solo existe en modo Verifactu (demostración).'], 403);
        }
        $record = $this->ownedRecord($id, $repo, $records, $user);
        if ($record === null) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $filename = 'factura-' . str_replace('/', '-', $record->getFullNumber()) . '.xml';

        return new Response($xml->build($record), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /** The invoice as a downloadable PDF (with the Verifactu fingerprint + QR when present). */
    #[Route('/api/invoices/{id}/pdf', name: 'api_invoices_pdf', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function pdf(int $id, InvoiceRepository $repo, InvoiceRecordRepository $records, InvoicePdf $pdf, #[CurrentUser] User $user): Response
    {
        $invoice = $repo->find($id);
        if ($invoice === null || $invoice->getUser() !== $user) {
            return $this->json(['error' => 'Not found'], 404);
        }
        $record = $records->findOneBy(['invoice' => $invoice]);
        $filename = 'factura-' . str_replace('/', '-', $invoice->getFullNumber()) . '.pdf';
        $showVerifactu = $user->getBillingMode() === 'verifactu';

        return new Response($pdf->build($invoice, $record, $showVerifactu), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function ownedRecord(int $id, InvoiceRepository $repo, InvoiceRecordRepository $records, User $user): ?InvoiceRecord
    {
        $invoice = $repo->find($id);
        if ($invoice === null || $invoice->getUser() !== $user) {
            return null;
        }

        return $records->findOneBy(['invoice' => $invoice]);
    }

    private function detail(Invoice $i, InvoiceRecordRepository $records): array
    {
        $record = $records->findOneBy(['invoice' => $i]);
        // The fingerprint/QR are demonstration artefacts — only surface them in Verifactu mode.
        $demoMode = $i->getUser()?->getBillingMode() === 'verifactu';

        return [
            'id'       => $i->getId(),
            'number'   => $i->getFullNumber(),
            'series'   => $i->getSeries(),
            'issuedAt' => $i->getIssuedAt()->format('Y-m-d'),
            'status'   => $i->getStatus(),
            'customer' => [
                'name'  => $i->getCustomer()?->getName(),
                'taxId' => $i->getCustomer()?->getTaxId(),
            ],
            'lines' => array_map(static fn ($l) => [
                'description' => $l->getDescription(),
                'quantity'    => $l->getQuantity(),
                'unitPrice'   => $l->getUnitPrice(),
                'vatRate'     => $l->getVatRate(),
            ], $i->getLines()->toArray()),
            'baseTotal' => $i->getBaseTotal(),
            'vatTotal'  => $i->getVatTotal(),
            'total'     => $i->getTotal(),
            'demoMode'  => $demoMode,
            'verifactu' => ($record === null || !$demoMode) ? null : [
                'hash'         => $record->getHash(),
                'previousHash' => $record->getPreviousHash(),
                'generatedAt'  => $record->getGeneratedAt(),
            ],
        ];
    }
}
