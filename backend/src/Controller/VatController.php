<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\VatService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class VatController extends AbstractController
{
    #[Route('/api/vat', name: 'api_vat', methods: ['GET'])]
    public function vat(VatService $vat, #[CurrentUser] User $user): JsonResponse
    {
        // Output VAT (repercutido) vs input VAT (soportado) and the net due.
        // ES: IVA repercutido vs IVA soportado y el neto a pagar/compensar.
        return $this->json($vat->summary($user));
    }
}
