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
    private const BACKGROUND_MESSAGES = [
        'background_not_inline_image' => 'The floor plan must be an uploaded image.',
        'background_too_large'        => 'The floor plan image is too large. Use a smaller photo or scan.',
    ];

    /** Compute the installation from an input payload, without saving. */
    #[Route('/api/installations/compute', name: 'api_installations_compute', methods: ['POST'])]
    public function compute(Request $request, InstallationCalculator $calc): JsonResponse
    {
        $data = is_array($d = json_decode($request->getContent(), true)) ? $d : [];
        $result = $calc->compute($data);
        if (is_array($data['layout'] ?? null)) {
            $layoutCable = $calc->layoutCable($data['layout']);
            if ($layoutCable !== null) {
                $result['layoutCable'] = $layoutCable;
            }
            $loads = is_array($data['loads'] ?? null) ? $data['loads'] : [];
            $result['validation'] = $calc->validateLayout($data['layout'], $loads);
            $result['board'] = $calc->panelSchedule($data['layout'], $loads, $result['contractedPower']);
        }

        return $this->json($result);
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
        if (($error = $this->backgroundError($data['background'] ?? null)) !== null) {
            return $this->json(['error' => self::BACKGROUND_MESSAGES[$error], 'code' => $error], 400);
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
        if (($error = $this->backgroundError($data['background'] ?? null)) !== null) {
            return $this->json(['error' => self::BACKGROUND_MESSAGES[$error], 'code' => $error], 400);
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
        $i->setLayout($this->cleanLayout($data['layout'] ?? []));
        $i->setBackground($this->cleanBackground($data['background'] ?? null));
    }

    /**
     * 3 MB of base64 image. The client already downscales to 1800 px, which lands well under; the ceiling
     * leaves headroom under the edge proxy's request-body limit, which would reject the save with an
     * opaque 413 before it ever reached PHP.
     * ES: 3 MB de imagen en base64. El cliente ya reduce a 1800 px y se queda muy por debajo; el techo
     * deja margen bajo el límite de cuerpo del proxy, que rechazaría el guardado con un 413 opaco.
     */
    private const MAX_BACKGROUND_BYTES = 3_000_000;

    /**
     * Why a background can't be accepted, or null when it's fine (including when none was sent).
     * Rejecting it silently would store `background: null` behind a 200 and the user would watch their
     * traced plan vanish on the next load.
     * ES: Por qué no se puede aceptar un fondo, o null si está bien (incluido «no se envió ninguno»).
     * Rechazarlo en silencio guardaría `background: null` tras un 200 y el usuario vería desaparecer su
     * plano al recargar.
     */
    private function backgroundError(mixed $bg): ?string
    {
        if ($bg === null || $bg === []) {
            return null;
        }
        if (!is_array($bg) || !is_string($bg['src'] ?? null) || !str_starts_with($bg['src'], 'data:image/')) {
            return 'background_not_inline_image';
        }

        return strlen($bg['src']) > self::MAX_BACKGROUND_BYTES ? 'background_too_large' : null;
    }

    /**
     * Sanitise the optional scanned floor plan: it must be an inline image, within a size budget, and its
     * placement is stored in metres. Callers validate with backgroundError() first.
     * ES: Sanea el plano escaneado: debe ser una imagen embebida, dentro de un límite de tamaño, y su
     * colocación se guarda en metros. Quien llama valida antes con backgroundError().
     *
     * @return array<string,mixed>|null
     */
    private function cleanBackground(mixed $bg): ?array
    {
        if (!is_array($bg) || !is_string($bg['src'] ?? null)) {
            return null;
        }
        $src = $bg['src'];
        if (!str_starts_with($src, 'data:image/') || strlen($src) > self::MAX_BACKGROUND_BYTES) {
            return null;
        }
        $num = static fn ($v, float $d): float => round(is_numeric($v) ? (float) $v : $d, 3);

        return [
            'src'     => $src,
            'x'       => $num($bg['x'] ?? 0, 0),
            'y'       => $num($bg['y'] ?? 0, 0),
            'w'       => max(0.5, $num($bg['w'] ?? 12, 12)),
            'h'       => max(0.5, $num($bg['h'] ?? 9, 9)),
            'opacity' => min(1, max(0.05, $num($bg['opacity'] ?? 0.6, 0.6))),
        ];
    }

    /** @return array<string,mixed> */
    private function cleanLayout(mixed $layout): array
    {
        if (!is_array($layout)) {
            return [];
        }
        $num = static fn ($v): float => round((float) $v, 2);
        $out = [];
        if (is_array($layout['panel'] ?? null)) {
            $out['panel'] = ['x' => $num($layout['panel']['x'] ?? 0), 'y' => $num($layout['panel']['y'] ?? 0)];
        }
        // A room is a polygon (so an L-shaped living room is possible). Legacy rectangles are still
        // accepted and converted by the client. / Una estancia es un polígono; se aceptan los
        // rectángulos antiguos y el cliente los convierte.
        $out['rooms'] = [];
        foreach ((is_array($layout['rooms'] ?? null) ? $layout['rooms'] : []) as $r) {
            if (!is_array($r)) {
                continue;
            }
            $type = (string) ($r['type'] ?? 'otros');
            if (is_array($r['points'] ?? null) && count($r['points']) >= 3) {
                $points = [];
                foreach (array_slice($r['points'], 0, 64) as $p) {
                    if (is_array($p)) {
                        $points[] = ['x' => $num($p['x'] ?? 0), 'y' => $num($p['y'] ?? 0)];
                    }
                }
                if (count($points) >= 3) {
                    $out['rooms'][] = ['type' => $type, 'points' => $points];
                }
            } elseif (isset($r['w'], $r['h'])) {
                $out['rooms'][] = [
                    'type' => $type,
                    'x' => $num($r['x'] ?? 0), 'y' => $num($r['y'] ?? 0),
                    'w' => $num($r['w'] ?? 2), 'h' => $num($r['h'] ?? 2),
                ];
            }
        }
        // A socket may declare the circuit it hangs from (ITC-BT-25); anything else is dropped, and a socket
        // without one is still valid — the validator credits it against whatever the room lacks.
        $out['devices'] = [];
        foreach ((is_array($layout['devices'] ?? null) ? $layout['devices'] : []) as $d) {
            if (!is_array($d) || !isset($d['type'])) {
                continue;
            }
            $device = ['type' => (string) $d['type'], 'x' => $num($d['x'] ?? 0), 'y' => $num($d['y'] ?? 0)];
            $circuit = strtoupper((string) ($d['circuit'] ?? ''));
            if (in_array($circuit, ['C2', 'C3', 'C4', 'C5', 'C10'], true)) {
                $device['circuit'] = $circuit;
            }
            $out['devices'][] = $device;
        }

        return $out;
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
        $result = $calc->compute($i->toInput());
        $layoutCable = $calc->layoutCable($i->getLayout());
        if ($layoutCable !== null) {
            $result['layoutCable'] = $layoutCable;
        }
        $result['validation'] = $calc->validateLayout($i->getLayout(), $i->getLoads());
        $result['board'] = $calc->panelSchedule($i->getLayout(), $i->getLoads(), $result['contractedPower']);

        return [
            'id'         => $i->getId(),
            'name'       => $i->getName(),
            'grade'      => $i->getGrade(),
            'supplyType' => $i->getSupplyType(),
            'loads'      => $i->getLoads(),
            'rooms'      => $i->getRooms(),
            'layout'     => $i->getLayout(),
            'background' => $i->getBackground(),
            'result'     => $result,
        ];
    }
}
