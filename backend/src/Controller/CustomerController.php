<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\User;
use App\Repository\CustomerRepository;
use App\Repository\InvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * CRUD for the freelancer's customers. Every action is scoped to the current user.
 * ES: CRUD de los clientes del autónomo. Cada acción está acotada al usuario actual.
 */
class CustomerController extends AbstractController
{
    #[Route('/api/customers', name: 'api_customers_list', methods: ['GET'])]
    public function list(CustomerRepository $repo, #[CurrentUser] User $user): JsonResponse
    {
        return $this->json(array_map($this->toArray(...), $repo->findForUser($user)));
    }

    #[Route('/api/customers', name: 'api_customers_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em, #[CurrentUser] User $user): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $name = trim((string) ($data['name'] ?? ''));
        $taxId = trim((string) ($data['taxId'] ?? ''));
        if ($name === '' || $taxId === '') {
            return $this->json(['error' => 'name and taxId are required'], 400);
        }

        $customer = (new Customer())->setUser($user);
        $this->apply($customer, $data);
        $em->persist($customer);
        $em->flush();

        return $this->json($this->toArray($customer), 201);
    }

    #[Route('/api/customers/{id}', name: 'api_customers_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request, CustomerRepository $repo, EntityManagerInterface $em, #[CurrentUser] User $user): JsonResponse
    {
        $customer = $this->owned($id, $repo, $user);
        if ($customer === null) {
            return $this->json(['error' => 'Not found'], 404);
        }
        $data = json_decode($request->getContent(), true);
        if (trim((string) ($data['name'] ?? '')) === '' || trim((string) ($data['taxId'] ?? '')) === '') {
            return $this->json(['error' => 'name and taxId are required'], 400);
        }
        $this->apply($customer, $data);
        $em->flush();

        return $this->json($this->toArray($customer));
    }

    #[Route('/api/customers/{id}', name: 'api_customers_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id, CustomerRepository $repo, InvoiceRepository $invoices, EntityManagerInterface $em, #[CurrentUser] User $user): JsonResponse
    {
        $customer = $this->owned($id, $repo, $user);
        if ($customer === null) {
            return $this->json(['error' => 'Not found'], 404);
        }
        // A customer with invoices can't be deleted — invoices must keep their issuer/customer intact.
        if ($invoices->findOneBy(['customer' => $customer]) !== null) {
            return $this->json(['error' => 'Customer has invoices and cannot be deleted'], 409);
        }
        $em->remove($customer);
        $em->flush();

        return $this->json(['deleted' => true]);
    }

    private function apply(Customer $c, array $data): void
    {
        $c->setName(trim((string) ($data['name'] ?? '')))
          ->setTaxId(trim((string) ($data['taxId'] ?? '')))
          ->setAddress(($a = trim((string) ($data['address'] ?? ''))) !== '' ? $a : null)
          ->setEmail(($e = trim((string) ($data['email'] ?? ''))) !== '' ? $e : null);
    }

    private function owned(int $id, CustomerRepository $repo, User $user): ?Customer
    {
        $customer = $repo->find($id);

        return ($customer !== null && $customer->getUser() === $user) ? $customer : null;
    }

    private function toArray(Customer $c): array
    {
        return [
            'id'      => $c->getId(),
            'name'    => $c->getName(),
            'taxId'   => $c->getTaxId(),
            'address' => $c->getAddress(),
            'email'   => $c->getEmail(),
        ];
    }
}
