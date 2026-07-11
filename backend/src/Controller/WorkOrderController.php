<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\WorkOrder;
use App\Repository\WorkOrderRepository;
use App\Service\WorkOrderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Work orders (partes de trabajo): CRUD + convert-to-invoice. Everything is scoped to the current user.
 * ES: Partes de trabajo: CRUD + conversión a factura. Todo acotado al usuario actual.
 */
class WorkOrderController extends AbstractController
{
    #[Route('/api/work-orders', name: 'api_work_orders_list', methods: ['GET'])]
    public function list(WorkOrderRepository $repo, #[CurrentUser] User $user): JsonResponse
    {
        $rows = array_map(static fn (WorkOrder $w): array => [
            'id'          => $w->getId(),
            'title'       => $w->getTitle(),
            'customer'    => $w->getCustomer()?->getName(),
            'status'      => $w->getStatus(),
            'scheduledAt' => $w->getScheduledAt()?->format('Y-m-d H:i'),
            'invoiced'    => $w->getConvertedInvoice() !== null,
        ], $repo->findForUser($user));

        return $this->json($rows);
    }

    #[Route('/api/work-orders', name: 'api_work_orders_create', methods: ['POST'])]
    public function create(Request $request, WorkOrderService $service, #[CurrentUser] User $user): JsonResponse
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

    #[Route('/api/work-orders/{id}', name: 'api_work_orders_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function get(int $id, WorkOrderRepository $repo, #[CurrentUser] User $user): JsonResponse
    {
        $wo = $repo->findOwned($id, $user);

        return $wo === null ? $this->json(['error' => 'Not found'], 404) : $this->json($this->detail($wo));
    }

    #[Route('/api/work-orders/{id}', name: 'api_work_orders_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request, WorkOrderRepository $repo, WorkOrderService $service, #[CurrentUser] User $user): JsonResponse
    {
        $wo = $repo->findOwned($id, $user);
        if ($wo === null) {
            return $this->json(['error' => 'Not found'], 404);
        }
        if ($wo->getConvertedInvoice() !== null) {
            return $this->json(['error' => 'invoiced_immutable', 'message' => 'Un parte ya facturado no se puede modificar.'], 409);
        }
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON'], 400);
        }

        return $this->json($this->detail($service->update($wo, $data)));
    }

    #[Route('/api/work-orders/{id}', name: 'api_work_orders_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id, WorkOrderRepository $repo, EntityManagerInterface $em, #[CurrentUser] User $user): JsonResponse
    {
        $wo = $repo->findOwned($id, $user);
        if ($wo === null) {
            return $this->json(['error' => 'Not found'], 404);
        }
        if ($wo->getConvertedInvoice() !== null) {
            return $this->json(['error' => 'invoiced_immutable', 'message' => 'Un parte ya facturado no se puede borrar.'], 409);
        }
        $em->remove($wo);
        $em->flush();

        return $this->json(['deleted' => true]);
    }

    #[Route('/api/work-orders/{id}/convert', name: 'api_work_orders_convert', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function convert(int $id, WorkOrderRepository $repo, WorkOrderService $service, #[CurrentUser] User $user): JsonResponse
    {
        $wo = $repo->findOwned($id, $user);
        if ($wo === null) {
            return $this->json(['error' => 'Not found'], 404);
        }
        try {
            $invoice = $service->convert($user, $wo);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        return $this->json([
            'invoiceId'     => $invoice->getId(),
            'invoiceNumber' => $invoice->getFullNumber(),
            'status'        => $wo->getStatus(),
        ], 201);
    }

    /** @return array<string,mixed> */
    private function detail(WorkOrder $w): array
    {
        return [
            'id'          => $w->getId(),
            'title'       => $w->getTitle(),
            'description' => $w->getDescription(),
            'status'      => $w->getStatus(),
            'scheduledAt' => $w->getScheduledAt()?->format('Y-m-d H:i'),
            'customer'    => [
                'id'    => $w->getCustomer()?->getId(),
                'name'  => $w->getCustomer()?->getName(),
                'taxId' => $w->getCustomer()?->getTaxId(),
            ],
            'laborHours'   => $w->getLaborHours(),
            'laborRate'    => $w->getLaborRate(),
            'laborVatRate' => $w->getLaborVatRate(),
            'lines' => array_map(static fn ($l) => [
                'description' => $l->getDescription(),
                'quantity'    => $l->getQuantity(),
                'unitPrice'   => $l->getUnitPrice(),
                'vatRate'     => $l->getVatRate(),
            ], $w->getLines()->toArray()),
            'convertedInvoice' => $w->getConvertedInvoice() === null ? null : [
                'id'     => $w->getConvertedInvoice()->getId(),
                'number' => $w->getConvertedInvoice()->getFullNumber(),
            ],
        ];
    }
}
