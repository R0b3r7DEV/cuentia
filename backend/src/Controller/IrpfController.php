<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\IrpfService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class IrpfController extends AbstractController
{
    #[Route('/api/irpf', name: 'api_irpf', methods: ['GET'])]
    public function irpf(IrpfService $irpf, #[CurrentUser] User $user): JsonResponse
    {
        // IRPF (modelo 130) quarterly prepayment estimate + next deadline.
        // ES: Estimación del pago fraccionado del IRPF (modelo 130) + próximo vencimiento.
        return $this->json($irpf->summary($user));
    }
}
