<?php

namespace App\Controller;

use App\Entity\Installation;
use App\Entity\User;
use App\Repository\InstallationRepository;
use App\Service\InstallationCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Electrical-installation designer (ITC-BT-25): a stateless /compute endpoint plus CRUD to save designs.
 * ES: Diseñador de instalación eléctrica (ITC-BT-25): un endpoint /compute sin estado más CRUD para
 * guardar diseños. Todo acotado al usuario.
 */
class InstallationController extends AbstractController
{
    /** Compute the installation from an input payload, without saving. */
    #[Route('/api/installations/compute', name: 'api_installations_compute', methods: ['POST'])]
    public function compute(Request $request, InstallationCalculator $calc): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        return $this->json($calc->compute(is_array($data) ? $data : []));
    }

    #[Route('/api/installations', name: 'api_installations_list', methods: ['GET'])]
    public function list(InstallationRepository $repo, #[CurrentUser] User $user): JsonResponse
    {
        $rows = array_map(static fn (Installation $i): array => [
            'id'    => $i->getId(),
            'name'  => $i->getName(),
            'grade' => $i->getGrade(),
        ], $repo->findForUser($user));

        return $this->json($rows);
    }

    #[Route('/api/installations', name: 'api_installations_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em, InstallationCalculator $calc, #[CurrentUser] User $user): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || trim((string) ($data['name'] ?? '')) === '') {
            return $this->json(['error' => 'name is required'], 400);
        }

        $installation = (new Installation())->setUser($user);
        $this->apply($installation, $data);
        $em->persist($installation);
        $em->flush();

        return $this->json($this->detail($installation, $calc), 201);
    }

    #[Route('/api/installations/{id}', name: 'api_installations_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function get(int $id, InstallationRepository $repo, InstallationCalculator $calc, #[CurrentUser] User $user): JsonResponse
    {
        $installation = $this->owned($id, $repo, $user);

        return $installation === null ? $this->json(['error' => 'Not found'], 404) : $this->json($this->detail($installation, $calc));
    }

    #[Route('/api/installations/{id}', name: 'api_installations_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request, InstallationRepository $repo, EntityManagerInterface $em, InstallationCalculator $calc, #[CurrentUser] User $user): JsonResponse
    {
        $installation = $this->owned($id, $repo, $user);
        if ($installation === null) {
            return $this->json(['error' => 'Not found'], 404);
        }
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || trim((string) ($data['name'] ?? '')) === '') {
            return $this->json(['error' => 'name is required'], 400);
        }
        $this->apply($installation, $data);
        $em->flush();

        return $this->json($this->detail($installation, $calc));
    }

    #[Route('/api/installations/{id}', name: 'api_installations_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id, InstallationRepository $repo, EntityManagerInterface $em, #[CurrentUser] User $user): JsonResponse
    {
        $installation = $this->owned($id, $repo, $user);
        if ($installation === null) {
            return $this->json(['error' => 'Not found'], 404);
        }
        $em->remove($installation);
        $em->flush();

        return $this->json(['deleted' => true]);
    }

    private function apply(Installation $i, array $data): void
    {
        $i->setName(trim((string) ($data['name'] ?? '')));
        $grade = (string) ($data['grade'] ?? 'auto');
        $i->setGrade(in_array($grade, ['auto', 'basico', 'elevado'], true) ? $grade : 'auto');
        $i->setSupplyType(($data['supplyType'] ?? 'monofasico') === 'trifasico' ? 'trifasico' : 'monofasico');
        $i->setLoads($this->cleanLoads($data['loads'] ?? []));
        $i->setRooms($this->cleanRooms($data['rooms'] ?? []));
    }

    /** @return array<string,bool> */
    private function cleanLoads(mixed $loads): array
    {
        if (!is_array($loads)) {
            return [];
        }
        $out = [];
        foreach (['cocina', 'lavadora', 'calefaccion', 'aire', 'secadora', 'domotica', 'vehiculo'] as $k) {
            if (array_key_exists($k, $loads)) {
                $out[$k] = (bool) $loads[$k];
            }
        }

        return $out;
    }

    /** @return array<int,array{type:string,area:float}> */
    private function cleanRooms(mixed $rooms): array
    {
        if (!is_array($rooms)) {
            return [];
        }
        $out = [];
        foreach ($rooms as $r) {
            if (is_array($r) && isset($r['type'])) {
                $out[] = ['type' => (string) $r['type'], 'area' => round((float) ($r['area'] ?? 0), 2)];
            }
        }

        return $out;
    }

    private function owned(int $id, InstallationRepository $repo, User $user): ?Installation
    {
        $installation = $repo->find($id);

        return ($installation !== null && $installation->getUser() === $user) ? $installation : null;
    }

    private function detail(Installation $i, InstallationCalculator $calc): array
    {
        return [
            'id'         => $i->getId(),
            'name'       => $i->getName(),
            'grade'      => $i->getGrade(),
            'supplyType' => $i->getSupplyType(),
            'loads'      => $i->getLoads(),
            'rooms'      => $i->getRooms(),
            'result'     => $calc->compute($i->toInput()),
        ];
    }
}
