<?php

namespace App\Controller;

use App\Entity\Service;
use App\Entity\User;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * CRUD for the reusable services/products catalog. Every action is scoped to the current user.
 * Deleting a catalog item is always safe: invoice/quote lines keep their own copy of the data.
 * ES: CRUD del catálogo de servicios/productos reutilizables, acotado al usuario. Borrar un elemento es
 * siempre seguro: las líneas de facturas/presupuestos guardan su propia copia de los datos.
 */
class ServiceController extends AbstractController
{
    #[Route('/api/services', name: 'api_services_list', methods: ['GET'])]
    public function list(ServiceRepository $repo, #[CurrentUser] User $user): JsonResponse
    {
        return $this->json(array_map($this->toArray(...), $repo->findForUser($user)));
    }

    #[Route('/api/services', name: 'api_services_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em, #[CurrentUser] User $user): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (trim((string) ($data['name'] ?? '')) === '') {
            return $this->json(['error' => 'name is required'], 400);
        }

        $service = (new Service())->setUser($user);
        $this->apply($service, $data);
        $em->persist($service);
        $em->flush();

        return $this->json($this->toArray($service), 201);
    }

    #[Route('/api/services/{id}', name: 'api_services_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request, ServiceRepository $repo, EntityManagerInterface $em, #[CurrentUser] User $user): JsonResponse
    {
        $service = $this->owned($id, $repo, $user);
        if ($service === null) {
            return $this->json(['error' => 'Not found'], 404);
        }
        $data = json_decode($request->getContent(), true);
        if (trim((string) ($data['name'] ?? '')) === '') {
            return $this->json(['error' => 'name is required'], 400);
        }
        $this->apply($service, $data);
        $em->flush();

        return $this->json($this->toArray($service));
    }

    #[Route('/api/services/{id}', name: 'api_services_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id, ServiceRepository $repo, EntityManagerInterface $em, #[CurrentUser] User $user): JsonResponse
    {
        $service = $this->owned($id, $repo, $user);
        if ($service === null) {
            return $this->json(['error' => 'Not found'], 404);
        }
        $em->remove($service);
        $em->flush();

        return $this->json(['deleted' => true]);
    }

    private function apply(Service $s, array $data): void
    {
        $s->setName(trim((string) ($data['name'] ?? '')))
          ->setUnitPrice($this->decimal($data['unitPrice'] ?? '0'))
          ->setVatRate($this->decimal($data['vatRate'] ?? '21'));
    }

    private function owned(int $id, ServiceRepository $repo, User $user): ?Service
    {
        $service = $repo->find($id);

        return ($service !== null && $service->getUser() === $user) ? $service : null;
    }

    private function decimal(string|int|float $v): string
    {
        return number_format((float) $v, 2, '.', '');
    }

    private function toArray(Service $s): array
    {
        return [
            'id'        => $s->getId(),
            'name'      => $s->getName(),
            'unitPrice' => $s->getUnitPrice(),
            'vatRate'   => $s->getVatRate(),
        ];
    }
}
