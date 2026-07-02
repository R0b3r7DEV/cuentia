<?php

namespace App\Controller;

use App\Service\IrpfService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class IrpfController extends AbstractController
{
    #[Route('/api/irpf', name: 'api_irpf', methods: ['GET'])]
    public function irpf(IrpfService $irpf): JsonResponse
    {
        // IRPF (modelo 130) quarterly prepayment estimate + next deadline.
        // ES: Estimación del pago fraccionado del IRPF (modelo 130) + próximo vencimiento.
        return $this->json($irpf->summary());
    }
}
