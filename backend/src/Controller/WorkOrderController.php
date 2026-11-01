<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\WorkOrder;
use App\Entity\WorkOrderPhoto;
use App\Repository\WorkOrderRepository;
use App\Service\WorkOrderPdf;
use App\Service\WorkOrderService;
use App\Time\BusinessClock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Work orders (partes de trabajo): CRUD, lifecycle, signature, photos, PDF and convert-to-invoice.
 * Everything is scoped to the current user; a photo is only ever looked up inside its own (owned) order.
 * Errors are returned as fixed codes, never as raw exception messages.
 *
 * ES: Partes de trabajo: CRUD, ciclo de vida, firma, fotos, PDF y conversión a factura. Todo acotado al
 * usuario actual; una foto solo se busca dentro de su propio parte (del usuario). Los errores se devuelven
 * como códigos fijos, nunca con el mensaje de la excepción tal cual.
 */
class WorkOrderController extends AbstractController
{
    /** Times go out as Spanish wall-clock time, the same the person typed. / Horas en hora de España. */
    public function __construct(private BusinessClock $clock) {}

    #[Route('/api/work-orders', name: 'api_work_orders_list', methods: ['GET'])]
    public function list(WorkOrderRepository $repo, #[CurrentUser] User $user): JsonResponse
    {
        $rows = array_map(fn (WorkOrder $w): array => [
            'id'          => $w->getId(),
            'title'       => $w->getTitle(),
            'customer'    => $w->getCustomer()?->getName(),
            'status'      => $w->getStatus(),
            'scheduledAt' => $this->clock->format($w->getScheduledAt(), 'Y-m-d H:i'),
            'createdAt'   => $this->clock->format($w->getCreatedAt(), 'Y-m-d H:i'),
            'totalCents'  => $w->totalsCents()['total'],
            'invoiced'    => $w->getConvertedInvoice() !== null,
        ], $repo->findForUser($user));

        return $this->json($rows);
    }

    #[Route('/api/work-orders', name: 'api_work_orders_create', methods: ['POST'])]
    public function create(Request $request, WorkOrderService $service, #[CurrentUser] User $user): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'invalid_json'], 400);
        }

        return $this->attempt(fn () => $this->json($this->detail($service->create($user, $data)), 201));
    }

    #[Route('/api/work-orders/{id}', name: 'api_work_orders_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function get(int $id, WorkOrderRepository $repo, #[CurrentUser] User $user): JsonResponse
    {
        $wo = $repo->findOwned($id, $user);

        return $wo === null ? $this->notFound() : $this->json($this->detail($wo));
    }

    #[Route('/api/work-orders/{id}', name: 'api_work_orders_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request, WorkOrderRepository $repo, WorkOrderService $service, #[CurrentUser] User $user): JsonResponse
    {
        $wo = $repo->findOwned($id, $user);
        if ($wo === null) {
            return $this->notFound();
        }
        if ($wo->getConvertedInvoice() !== null) {
            return $this->json(['error' => 'invoiced_immutable'], 409);
        }
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'invalid_json'], 400);
        }

        return $this->attempt(fn () => $this->json($this->detail($service->update($wo, $data))));
    }

    #[Route('/api/work-orders/{id}', name: 'api_work_orders_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id, WorkOrderRepository $repo, EntityManagerInterface $em, #[CurrentUser] User $user): JsonResponse
    {
        $wo = $repo->findOwned($id, $user);
        if ($wo === null) {
            return $this->notFound();
        }
        if ($wo->getConvertedInvoice() !== null) {
            return $this->json(['error' => 'invoiced_immutable'], 409);
        }
        if (!$wo->isEditable()) {
            return $this->json(['error' => 'not_editable'], 409);
        }
        $em->remove($wo);
        $em->flush();

        return $this->json(['deleted' => true]);
    }

    /** Body: {status, withoutSignature?}. / Cuerpo: {status, withoutSignature?}. */
    #[Route('/api/work-orders/{id}/status', name: 'api_work_orders_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function status(int $id, Request $request, WorkOrderRepository $repo, WorkOrderService $service, #[CurrentUser] User $user): JsonResponse
    {
        $wo = $repo->findOwned($id, $user);
        if ($wo === null) {
            return $this->notFound();
        }
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !is_string($data['status'] ?? null)) {
            return $this->json(['error' => 'invalid_json'], 400);
        }

        return $this->attempt(fn () => $this->json($this->detail(
            $service->transition($wo, $data['status'], ($data['withoutSignature'] ?? false) === true)
        )));
    }

    #[Route('/api/work-orders/{id}/convert', name: 'api_work_orders_convert', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function convert(int $id, WorkOrderRepository $repo, WorkOrderService $service, #[CurrentUser] User $user): JsonResponse
    {
        $wo = $repo->findOwned($id, $user);
        if ($wo === null) {
            return $this->notFound();
        }

        return $this->attempt(function () use ($service, $user, $wo) {
            $invoice = $service->convert($user, $wo);

            return $this->json([
                'invoiceId'     => $invoice->getId(),
                'invoiceNumber' => $invoice->getFullNumber(),
                'status'        => $wo->getStatus(),
            ], 201);
        });
    }

    /** Body: {png: "data:image/png;base64,...", signedBy}. */
    #[Route('/api/work-orders/{id}/signature', name: 'api_work_orders_sign', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function sign(int $id, Request $request, WorkOrderRepository $repo, WorkOrderService $service, #[CurrentUser] User $user): JsonResponse
    {
        $wo = $repo->findOwned($id, $user);
        if ($wo === null) {
            return $this->notFound();
        }
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !is_string($data['png'] ?? null)) {
            return $this->json(['error' => 'invalid_json'], 400);
        }

        return $this->attempt(fn () => $this->json($this->detail(
            $service->sign($wo, $data['png'], (string) ($data['signedBy'] ?? ''))
        )));
    }

    #[Route('/api/work-orders/{id}/signature', name: 'api_work_orders_unsign', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function unsign(int $id, WorkOrderRepository $repo, WorkOrderService $service, #[CurrentUser] User $user): JsonResponse
    {
        $wo = $repo->findOwned($id, $user);
        if ($wo === null) {
            return $this->notFound();
        }

        return $this->attempt(fn () => $this->json($this->detail($service->clearSignature($wo))));
    }

    #[Route('/api/work-orders/{id}/signature', name: 'api_work_orders_signature', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function signature(int $id, WorkOrderRepository $repo, #[CurrentUser] User $user): Response
    {
        $wo = $repo->findOwned($id, $user);
        if ($wo === null || !$wo->hasSignature()) {
            return $this->notFound();
        }

        return $this->image((string) $wo->getSignaturePng(), 'image/png');
    }

    /** Multipart upload, field "photo". / Subida multipart, campo "photo". */
    #[Route('/api/work-orders/{id}/photos', name: 'api_work_orders_photo_add', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addPhoto(int $id, Request $request, WorkOrderRepository $repo, WorkOrderService $service, #[CurrentUser] User $user): JsonResponse
    {
        $wo = $repo->findOwned($id, $user);
        if ($wo === null) {
            return $this->notFound();
        }
        $file = $request->files->get('photo');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return $this->json(['error' => 'invalid_image'], 400);
        }

        return $this->attempt(fn () => $this->json(
            $this->photoPayload($wo, $service->addPhoto($wo, (string) file_get_contents($file->getPathname()))),
            201,
        ));
    }

    #[Route('/api/work-orders/{id}/photos/{photoId}', name: 'api_work_orders_photo_get', methods: ['GET'], requirements: ['id' => '\d+', 'photoId' => '\d+'])]
    public function photo(int $id, int $photoId, WorkOrderRepository $repo, #[CurrentUser] User $user): Response
    {
        $photo = $this->ownedPhoto($repo->findOwned($id, $user), $photoId);

        return $photo === null ? $this->notFound() : $this->image($photo->getData(), 'image/jpeg');
    }

    #[Route('/api/work-orders/{id}/photos/{photoId}', name: 'api_work_orders_photo_delete', methods: ['DELETE'], requirements: ['id' => '\d+', 'photoId' => '\d+'])]
    public function deletePhoto(int $id, int $photoId, WorkOrderRepository $repo, WorkOrderService $service, #[CurrentUser] User $user): JsonResponse
    {
        $wo = $repo->findOwned($id, $user);
        $photo = $this->ownedPhoto($wo, $photoId);
        if ($photo === null) {
            return $this->notFound();
        }

        return $this->attempt(function () use ($service, $wo, $photo) {
            $service->removePhoto($wo, $photo);

            return $this->json(['deleted' => true]);
        });
    }

    #[Route('/api/work-orders/{id}/pdf', name: 'api_work_orders_pdf', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function pdf(int $id, WorkOrderRepository $repo, WorkOrderPdf $pdf, #[CurrentUser] User $user): Response
    {
        $wo = $repo->findOwned($id, $user);
        if ($wo === null) {
            return $this->notFound();
        }

        return new Response($pdf->build($wo), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $pdf->filename($wo) . '"',
        ]);
    }

    /**
     * Run a service call and turn its coded exceptions into responses: bad input → 400, not allowed in the
     * current state → 409. Anything else bubbles up to Symfony, which never shows details in production.
     */
    private function attempt(callable $action): JsonResponse
    {
        try {
            return $action();
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], 409);
        }
    }

    /** The photo, only if it hangs from this (already owned) order: never looked up by id alone. */
    private function ownedPhoto(?WorkOrder $wo, int $photoId): ?WorkOrderPhoto
    {
        foreach ($wo?->getPhotos() ?? [] as $photo) {
            if ($photo->getId() === $photoId) {
                return $photo;
            }
        }

        return null;
    }

    private function image(string $bytes, string $type): Response
    {
        return new Response($bytes, 200, [
            'Content-Type' => $type,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function notFound(): JsonResponse
    {
        return $this->json(['error' => 'not_found'], 404);
    }

    /** @return array<string,mixed> */
    private function photoPayload(WorkOrder $wo, WorkOrderPhoto $p): array
    {
        return [
            'id'     => $p->getId(),
            'width'  => $p->getWidth(),
            'height' => $p->getHeight(),
            'url'    => sprintf('/api/work-orders/%d/photos/%d', $wo->getId(), $p->getId()),
        ];
    }

    /** @return array<string,mixed> */
    private function detail(WorkOrder $w): array
    {
        $totals = $w->totalsCents();

        return [
            'id'          => $w->getId(),
            'title'       => $w->getTitle(),
            'description' => $w->getDescription(),
            'status'      => $w->getStatus(),
            'editable'    => $w->isEditable(),
            'transitions' => WorkOrder::TRANSITIONS[$w->getStatus()],
            'scheduledAt' => $this->clock->format($w->getScheduledAt(), 'Y-m-d H:i'),
            'createdAt'   => $this->clock->format($w->getCreatedAt(), 'Y-m-d H:i'),
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
            ], array_values($w->getLines()->toArray())),
            'totals' => ['baseCents' => $totals['base'], 'vatCents' => $totals['vat'], 'totalCents' => $totals['total']],
            'photos' => array_map(fn (WorkOrderPhoto $p) => $this->photoPayload($w, $p), array_values($w->getPhotos()->toArray())),
            'maxPhotos' => WorkOrder::MAX_PHOTOS,
            'signature' => [
                'signed'   => $w->hasSignature(),
                'signedBy' => $w->getSignedBy(),
                'signedAt' => $this->clock->format($w->getSignedAt(), 'Y-m-d H:i'),
                'waived'   => $w->isSignatureWaived(),
                'url'      => $w->hasSignature() ? sprintf('/api/work-orders/%d/signature', $w->getId()) : null,
            ],
            'convertedInvoice' => $w->getConvertedInvoice() === null ? null : [
                'id'     => $w->getConvertedInvoice()->getId(),
                'number' => $w->getConvertedInvoice()->getFullNumber(),
            ],
        ];
    }
}
