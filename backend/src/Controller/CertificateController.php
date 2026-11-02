<?php

namespace App\Controller;

use App\Cie\CertinsModel;
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
        $data = $this->withProfileDefaults($data, $user);
        if ($this->missingRequired($data)) {
            return $this->json(['error' => 'address, titularName and companyName are required'], 400);
        }

        $certificate = (new Certificate())->setUser($user);
        $refused = $this->applyOrRefuse($certificate, $data, $em);
        if ($refused !== null) {
            return $refused;
        }
        $em->persist($certificate);
        $em->flush();

        return $this->json($this->toArray($certificate), 201);
    }

    /**
     * The CERTINS E lists (uses, risks, supply sources, design basis) with the model's own labels, the required
     * fields and the column lengths — so the wizard asks exactly what the backend checks and stores.
     */
    #[Route('/api/certificates/model', name: 'api_certificates_model', methods: ['GET'])]
    public function model(EntityManagerInterface $em): JsonResponse
    {
        return $this->json([
            'version'      => CertinsModel::VERSION,
            'uses'         => CertinsModel::USES,
            'risks'        => CertinsModel::RISKS,
            'sources'      => CertinsModel::SOURCES,
            'designBasis'  => CertinsModel::DESIGN_BASIS,
            'required'     => CertinsModel::REQUIRED,
            'gridRequired' => CertinsModel::GRID_REQUIRED,
            'maxLength'    => $this->maxLengths($em),
        ]);
    }

    /** The certificate as the CERTINS E sections A–G (+ MTD data), and what is still missing. */
    #[Route('/api/certificates/{id}/gva', name: 'api_certificates_gva', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function gva(int $id, CertificateRepository $repo, #[CurrentUser] User $user): JsonResponse
    {
        $certificate = $this->owned($id, $repo, $user);
        if ($certificate === null) {
            return $this->json(['error' => 'Not found'], 404);
        }

        return $this->json([
            'id'       => $certificate->getId(),
            'version'  => CertinsModel::VERSION,
            'sections' => \App\Cie\CertinsSheet::sections($certificate),
            'missing'  => \App\Cie\CertinsSheet::missing($certificate),
        ]);
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
        $refused = $this->applyOrRefuse($certificate, $data, $em);
        if ($refused !== null) {
            return $refused;
        }
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

    /**
     * A self-employed electrician is their own installation company: whatever is left blank on a new CIE is
     * filled from "Mis datos" (name, NIF, register number). The installer (holder of the CCI) is the same person
     * only when that NIF is a person's: a company's CIF is never offered as the installer's NIF.
     * ES: Un autónomo es su propia empresa instaladora: lo que se deje vacío en un CIE nuevo se completa con
     * «Mis datos». El instalador solo se rellena si ese NIF es de una persona, nunca con el CIF de una sociedad.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function withProfileDefaults(array $data, User $user): array
    {
        $defaults = [
            'companyName'      => $user->getBusinessName(),
            'companyNif'       => $user->getTaxId(),
            'companyRegNumber' => $user->getInstallerRegNumber(),
        ];
        if (\App\Util\Nif::isPerson((string) $user->getTaxId())) {
            $defaults['installerName'] = $user->getBusinessName();
            $defaults['installerNif'] = $user->getTaxId();
        }
        foreach ($defaults as $field => $value) {
            if (trim((string) ($data[$field] ?? '')) === '' && $value) {
                $data[$field] = $value;
            }
        }

        return $data;
    }

    private function missingRequired(array $data): bool
    {
        return (trim((string) ($data['address'] ?? '')) === '' && trim((string) ($data['streetName'] ?? '')) === '')
            || trim((string) ($data['titularName'] ?? '')) === ''
            || trim((string) ($data['companyName'] ?? '')) === '';
    }

    /**
     * apply() and then check every text against its column: a refused field is a 400 naming it, never a
     * database error. Nothing is flushed when a field is refused.
     */
    private function applyOrRefuse(Certificate $c, array $data, EntityManagerInterface $em): ?JsonResponse
    {
        try {
            $this->apply($c, $data);
            foreach ($this->maxLengths($em) as $field => $length) {
                if (mb_strlen((string) $c->{'get' . ucfirst($field)}()) > $length) {
                    throw new \InvalidArgumentException($field);
                }
            }
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => 'invalid_field', 'field' => $e->getMessage()], 400);
        }

        return null;
    }

    /** @return array<string,int> text field → its column length */
    private function maxLengths(EntityManagerInterface $em): array
    {
        $meta = $em->getClassMetadata(Certificate::class);
        $lengths = [];
        foreach ($meta->getFieldNames() as $field) {
            $length = $meta->getFieldMapping($field)->length;
            if ($length !== null) {
                $lengths[$field] = $length;
            }
        }

        return $lengths;
    }

    /** A date as the form sends it (YYYY-MM-DD); anything else ("31/12/2026", "next monday") is refused. */
    private function date(array $data, string $key): ?\DateTimeImmutable
    {
        $v = trim((string) ($data[$key] ?? ''));
        if ($v === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
        if ($date === false || $date->format('Y-m-d') !== $v) {
            throw new \InvalidArgumentException($key);
        }

        return $date;
    }

    private function apply(Certificate $c, array $data): void
    {
        $issuedAt = $this->date($data, 'issuedAt');
        if ($issuedAt !== null) {
            $c->setIssuedAt($issuedAt);
        }
        $installationType = (string) ($data['installationType'] ?? 'nueva');
        $c->setInstallationType(in_array($installationType, ['nueva', 'ampliacion', 'reforma'], true) ? $installationType : 'nueva');
        $use = (string) ($data['useType'] ?? '');
        $c->setUseType(isset(CertinsModel::USES[$use]) ? $use : null);
        $c->setSecondaryUses(array_values(array_diff(CertinsModel::pick($data['secondaryUses'] ?? [], CertinsModel::USES), [$use])));
        $c->setUseSpecifications($this->str($data, 'useSpecifications'));
        foreach (['streetType', 'streetName', 'portal', 'bis', 'stair', 'floor', 'door', 'population', 'distributor'] as $k) {
            $c->{'set' . ucfirst($k)}($this->str($data, $k));
        }
        $source = (string) ($data['supplySource'] ?? 'red');
        $c->setSupplySource(isset(CertinsModel::SOURCES[$source]) ? $source : 'red');
        $fromGrid = $c->getSupplySource() === 'red';
        // An isolated generating installation has no distributor nor CUPS: drop what a bill may have left.
        if (!$fromGrid) {
            $c->setDistributor(null);
        }
        $c->setSpecificRisks(CertinsModel::pick($data['specificRisks'] ?? [], CertinsModel::RISKS));
        // The one-line address: as typed, or composed from the CERTINS parts.
        $address = trim((string) ($data['address'] ?? ''));
        $c->setAddress($address !== '' ? $address : $c->composedAddress());
        $c->setLocality($this->str($data, 'locality'));
        $c->setProvince($this->str($data, 'province'));
        $c->setPostalCode($this->str($data, 'postalCode'));

        $c->setTitularName(trim((string) ($data['titularName'] ?? '')));
        $c->setTitularNif($this->str($data, 'titularNif'));
        $c->setTitularAddress($this->str($data, 'titularAddress'));
        $cups = \App\Util\Cups::normalize((string) ($data['cups'] ?? ''));
        $c->setCups($fromGrid && $cups !== '' ? $cups : null);

        $c->setCompanyName(trim((string) ($data['companyName'] ?? '')));
        $c->setCompanyRegNumber($this->str($data, 'companyRegNumber'));
        $c->setCompanyNif($this->str($data, 'companyNif'));
        $c->setInstallerName($this->str($data, 'installerName'));
        $c->setInstallerLicense($this->str($data, 'installerLicense'));
        $nif = \App\Util\Nif::normalize((string) ($data['installerNif'] ?? ''));
        $c->setInstallerNif($nif !== '' ? $nif : null);
        $basis = (string) ($data['designBasis'] ?? 'mtd');
        $c->setDesignBasis(isset(CertinsModel::DESIGN_BASIS[$basis]) ? $basis : 'mtd');
        $c->setDesignDate($this->date($data, 'designDate'));
        $c->setInsulationResistance($this->str($data, 'insulationResistance'));

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
        // "5,75" is how a power is written in Spain: with a comma, dots are thousands ("1.500,5" → 1500.5).
        // Without one, the dot is the decimal point, as the API itself returns it ("5.750").
        $v = trim((string) ($data[$key] ?? ''));
        if (str_contains($v, ',')) {
            $v = str_replace(',', '.', str_replace('.', '', $v));
        }

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
            'cups'                    => $c->getCups(),
            'streetType'              => $c->getStreetType(),
            'streetName'              => $c->getStreetName(),
            'portal'                  => $c->getPortal(),
            'bis'                     => $c->getBis(),
            'stair'                   => $c->getStair(),
            'floor'                   => $c->getFloor(),
            'door'                    => $c->getDoor(),
            'population'              => $c->getPopulation(),
            'supplySource'            => $c->getSupplySource(),
            'distributor'             => $c->getDistributor(),
            'specificRisks'           => $c->getSpecificRisks(),
            'secondaryUses'           => $c->getSecondaryUses(),
            'useSpecifications'       => $c->getUseSpecifications(),
            'designBasis'             => $c->getDesignBasis(),
            'designDate'              => $c->getDesignDate()?->format('Y-m-d'),
            'insulationResistance'    => $c->getInsulationResistance(),
            'installerNif'            => $c->getInstallerNif(),
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
