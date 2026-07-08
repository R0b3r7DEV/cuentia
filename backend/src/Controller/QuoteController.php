<?php

namespace App\Controller;

use App\Entity\Quote;
use App\Entity\User;
use App\Repository\QuoteRepository;
use App\Service\QuotePdf;
use App\Service\QuoteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Quotes (presupuestos): non-fiscal offers that can be converted into a real Verifactu invoice.
 * Every action is scoped to the current user.
 * ES: Presupuestos: ofertas no fiscales que pueden convertirse en factura Verifactu real. Cada acción
 * está acotada al usuario actual.
 */
class QuoteController extends AbstractController
{
    private const STATUSES = ['draft', 'sent', 'accepted', 'rejected'];

    #[Route('/api/quotes', name: 'api_quotes_list', methods: ['GET'])]
    public function list(QuoteRepository $repo, #[CurrentUser] User $user): JsonResponse
    {
        $rows = array_map(static fn (Quote $q): array => [
            'id'       => $q->getId(),
            'number'   => $q->getFullNumber(),
            'customer' => $q->getCustomer()?->getName(),
            'issuedAt' => $q->getIssuedAt()->format('Y-m-d'),
            'total'    => $q->getTotal(),
            'status'   => $q->getStatus(),
        ], $repo->findForUser($user));

        return $this->json($rows);
    }

    #[Route('/api/quotes', name: 'api_quotes_create', methods: ['POST'])]
    public function create(Request $request, QuoteService $service, #[CurrentUser] User $user): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON'], 400);
        }
        try {
            return $this->json($this->detail($service->create($user, $data)), 201);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/api/quotes/{id}', name: 'api_quotes_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function get(int $id, QuoteRepository $repo, #[CurrentUser] User $user): JsonResponse
    {
        $quote = $this->owned($id, $repo, $user);

        return $quote === null ? $this->json(['error' => 'Not found'], 404) : $this->json($this->detail($quote));
    }

    #[Route('/api/quotes/{id}/status', name: 'api_quotes_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function setStatus(int $id, Request $request, QuoteRepository $repo, \Doctrine\ORM\EntityManagerInterface $em, #[CurrentUser] User $user): JsonResponse
    {
        $quote = $this->owned($id, $repo, $user);
        if ($quote === null) {
            return $this->json(['error' => 'Not found'], 404);
        }
        $status = (string) (json_decode($request->getContent(), true)['status'] ?? '');
        if (!in_array($status, self::STATUSES, true)) {
            return $this->json(['error' => 'Invalid status'], 400);
        }
        if ($quote->getStatus() === 'converted') {
            return $this->json(['error' => 'A converted quote cannot change status'], 409);
        }
        $quote->setStatus($status);
        $em->flush();

        return $this->json($this->detail($quote));
    }

    #[Route('/api/quotes/{id}/convert', name: 'api_quotes_convert', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function convert(int $id, QuoteRepository $repo, QuoteService $service, #[CurrentUser] User $user): JsonResponse
    {
        $quote = $this->owned($id, $repo, $user);
        if ($quote === null) {
            return $this->json(['error' => 'Not found'], 404);
        }
        $invoice = $service->convert($user, $quote);

        return $this->json([
            'invoiceId'     => $invoice->getId(),
            'invoiceNumber' => $invoice->getFullNumber(),
            'status'        => $quote->getStatus(),
        ], 201);
    }

    #[Route('/api/quotes/{id}/pdf', name: 'api_quotes_pdf', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function pdf(int $id, QuoteRepository $repo, QuotePdf $pdf, #[CurrentUser] User $user): Response
    {
        $quote = $this->owned($id, $repo, $user);
        if ($quote === null) {
            return $this->json(['error' => 'Not found'], 404);
        }
        $filename = 'presupuesto-' . str_replace('/', '-', $quote->getFullNumber()) . '.pdf';

        return new Response($pdf->build($quote), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function owned(int $id, QuoteRepository $repo, User $user): ?Quote
    {
        $quote = $repo->find($id);

        return ($quote !== null && $quote->getUser() === $user) ? $quote : null;
    }

    private function detail(Quote $q): array
    {
        return [
            'id'         => $q->getId(),
            'number'     => $q->getFullNumber(),
            'series'     => $q->getSeries(),
            'issuedAt'   => $q->getIssuedAt()->format('Y-m-d'),
            'validUntil' => $q->getValidUntil()?->format('Y-m-d'),
            'status'     => $q->getStatus(),
            'customer'   => [
                'name'  => $q->getCustomer()?->getName(),
                'taxId' => $q->getCustomer()?->getTaxId(),
            ],
            'lines' => array_map(static fn ($l) => [
                'description' => $l->getDescription(),
                'quantity'    => $l->getQuantity(),
                'unitPrice'   => $l->getUnitPrice(),
                'vatRate'     => $l->getVatRate(),
            ], $q->getLines()->toArray()),
            'baseTotal'         => $q->getBaseTotal(),
            'vatTotal'          => $q->getVatTotal(),
            'total'             => $q->getTotal(),
            'convertedInvoice'  => $q->getConvertedInvoice() === null ? null : [
                'id'     => $q->getConvertedInvoice()->getId(),
                'number' => $q->getConvertedInvoice()->getFullNumber(),
            ],
        ];
    }
}
