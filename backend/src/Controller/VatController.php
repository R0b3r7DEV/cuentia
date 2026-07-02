<?php

namespace App\Controller;

use App\Service\VatService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class VatController extends AbstractController
{
    #[Route('/api/vat', name: 'api_vat', methods: ['GET'])]
    public function vat(VatService $vat): JsonResponse
    {
        // Output VAT (repercutido) vs input VAT (soportado) and the net due.
        // ES: IVA repercutido vs IVA soportado y el neto a pagar/compensar.
        return $this->json($vat->summary());
    }
}
