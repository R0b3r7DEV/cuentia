<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\User;
use App\Repository\InvoiceRecordRepository;
use App\Repository\InvoiceRepository;
use App\Service\InvoiceService;
use App\Service\VerifactuChain;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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

    private function detail(Invoice $i, InvoiceRecordRepository $records): array
    {
        $record = $records->findOneBy(['invoice' => $i]);

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
            'verifactu' => $record === null ? null : [
                'hash'         => $record->getHash(),
                'previousHash' => $record->getPreviousHash(),
                'generatedAt'  => $record->getGeneratedAt(),
            ],
        ];
    }
}
