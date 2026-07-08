<?php

namespace App\Controller;

use App\Entity\Certificate;
use App\Entity\User;
use App\Repository\CertificateRepository;
use App\Service\CiePdf;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * CRUD + PDF for low-voltage Electrical Installation Certificates (CIE / CERTINS E). User-scoped.
 * ES: CRUD + PDF de Certificados de Instalación Eléctrica de baja tensión (CIE / CERTINS E). Por usuario.
 */
class CertificateController extends AbstractController
{
    #[Route('/api/certificates', name: 'api_certificates_list', methods: ['GET'])]
    public function list(CertificateRepository $repo, #[CurrentUser] User $user): JsonResponse
    {
        return $this->json(array_map($this->toArray(...), $repo->findForUser($user)));
    }

    #[Route('/api/certificates', name: 'api_certificates_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em, #[CurrentUser] User $user): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON'], 400);
        }
        if ($this->missingRequired($data)) {
            return $this->json(['error' => 'address, titularName and companyName are required'], 400);
        }

        $certificate = (new Certificate())->setUser($user);
        $this->apply($certificate, $data);
        $em->persist($certificate);
        $em->flush();

        return $this->json($this->toArray($certificate), 201);
    }

    #[Route('/api/certificates/{id}', name: 'api_certificates_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function get(int $id, CertificateRepository $repo, #[CurrentUser] User $user): JsonResponse
    {
        $certificate = $this->owned($id, $repo, $user);

        return $certificate === null ? $this->json(['error' => 'Not found'], 404) : $this->json($this->toArray($certificate));
    }

    #[Route('/api/certificates/{id}', name: 'api_certificates_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request, CertificateRepository $repo, EntityManagerInterface $em, #[CurrentUser] User $user): JsonResponse
    {
        $certificate = $this->owned($id, $repo, $user);
        if ($certificate === null) {
            return $this->json(['error' => 'Not found'], 404);
        }
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || $this->missingRequired($data)) {
            return $this->json(['error' => 'address, titularName and companyName are required'], 400);
        }
        $this->apply($certificate, $data);
        $em->flush();

        return $this->json($this->toArray($certificate));
    }

    #[Route('/api/certificates/{id}', name: 'api_certificates_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id, CertificateRepository $repo, EntityManagerInterface $em, #[CurrentUser] User $user): JsonResponse
    {
        $certificate = $this->owned($id, $repo, $user);
        if ($certificate === null) {
            return $this->json(['error' => 'Not found'], 404);
        }
        $em->remove($certificate);
        $em->flush();

        return $this->json(['deleted' => true]);
    }

    #[Route('/api/certificates/{id}/pdf', name: 'api_certificates_pdf', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function pdf(int $id, CertificateRepository $repo, CiePdf $pdf, #[CurrentUser] User $user): Response
    {
        $certificate = $this->owned($id, $repo, $user);
        if ($certificate === null) {
            return $this->json(['error' => 'Not found'], 404);
        }

        return new Response($pdf->build($certificate), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="cie-' . $certificate->getId() . '.pdf"',
        ]);
    }

    private function missingRequired(array $data): bool
    {
        return trim((string) ($data['address'] ?? '')) === ''
            || trim((string) ($data['titularName'] ?? '')) === ''
            || trim((string) ($data['companyName'] ?? '')) === '';
    }

    private function apply(Certificate $c, array $data): void
    {
        if (!empty($data['issuedAt'])) {
            $c->setIssuedAt(new \DateTimeImmutable((string) $data['issuedAt']));
        }
        $installationType = (string) ($data['installationType'] ?? 'nueva');
        $c->setInstallationType(in_array($installationType, ['nueva', 'ampliacion', 'reforma'], true) ? $installationType : 'nueva');
        $c->setUseType($this->str($data, 'useType'));
        $c->setAddress(trim((string) ($data['address'] ?? '')));
        $c->setLocality($this->str($data, 'locality'));
        $c->setProvince($this->str($data, 'province'));
        $c->setPostalCode($this->str($data, 'postalCode'));

        $c->setTitularName(trim((string) ($data['titularName'] ?? '')));
        $c->setTitularNif($this->str($data, 'titularNif'));
        $c->setTitularAddress($this->str($data, 'titularAddress'));

        $c->setCompanyName(trim((string) ($data['companyName'] ?? '')));
        $c->setCompanyRegNumber($this->str($data, 'companyRegNumber'));
        $c->setCompanyNif($this->str($data, 'companyNif'));
        $c->setInstallerName($this->str($data, 'installerName'));
        $c->setInstallerLicense($this->str($data, 'installerLicense'));

        $c->setMaxPower($this->decimal($data, 'maxPower'));
        $c->setInstalledPower($this->decimal($data, 'installedPower'));
        $c->setVoltage($this->int($data, 'voltage'));
        $c->setSupplyType(in_array($data['supplyType'] ?? null, ['monofasico', 'trifasico'], true) ? (string) $data['supplyType'] : null);
        $c->setEarthingScheme($this->str($data, 'earthingScheme'));
        $c->setCircuits($this->int($data, 'circuits'));
        $c->setDerivationSection($this->str($data, 'derivationSection'));
        $c->setIgaCurrent($this->str($data, 'igaCurrent'));
        $c->setDifferentialSensitivity($this->str($data, 'differentialSensitivity'));
        $c->setEarthResistance($this->str($data, 'earthResistance'));
        $c->setEarthConductorSection($this->str($data, 'earthConductorSection'));
        $c->setObservations($this->str($data, 'observations'));
    }

    private function str(array $data, string $key): ?string
    {
        $v = trim((string) ($data[$key] ?? ''));

        return $v !== '' ? $v : null;
    }

    private function decimal(array $data, string $key): ?string
    {
        $v = trim((string) ($data[$key] ?? ''));

        return is_numeric($v) ? number_format((float) $v, 3, '.', '') : null;
    }

    private function int(array $data, string $key): ?int
    {
        $v = trim((string) ($data[$key] ?? ''));

        return $v !== '' && is_numeric($v) ? (int) $v : null;
    }

    private function owned(int $id, CertificateRepository $repo, User $user): ?Certificate
    {
        $certificate = $repo->find($id);

        return ($certificate !== null && $certificate->getUser() === $user) ? $certificate : null;
    }

    private function toArray(Certificate $c): array
    {
        return [
            'id'                      => $c->getId(),
            'issuedAt'                => $c->getIssuedAt()->format('Y-m-d'),
            'installationType'        => $c->getInstallationType(),
            'useType'                 => $c->getUseType(),
            'address'                 => $c->getAddress(),
            'locality'                => $c->getLocality(),
            'province'                => $c->getProvince(),
            'postalCode'              => $c->getPostalCode(),
            'titularName'             => $c->getTitularName(),
            'titularNif'              => $c->getTitularNif(),
            'titularAddress'          => $c->getTitularAddress(),
            'companyName'             => $c->getCompanyName(),
            'companyRegNumber'        => $c->getCompanyRegNumber(),
            'companyNif'              => $c->getCompanyNif(),
            'installerName'           => $c->getInstallerName(),
            'installerLicense'        => $c->getInstallerLicense(),
            'maxPower'                => $c->getMaxPower(),
            'installedPower'          => $c->getInstalledPower(),
            'voltage'                 => $c->getVoltage(),
            'supplyType'              => $c->getSupplyType(),
            'earthingScheme'          => $c->getEarthingScheme(),
            'circuits'                => $c->getCircuits(),
            'derivationSection'       => $c->getDerivationSection(),
            'igaCurrent'              => $c->getIgaCurrent(),
            'differentialSensitivity' => $c->getDifferentialSensitivity(),
            'earthResistance'         => $c->getEarthResistance(),
            'earthConductorSection'   => $c->getEarthConductorSection(),
            'observations'            => $c->getObservations(),
        ];
    }
}
