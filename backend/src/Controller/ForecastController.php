<?php

namespace App\Controller;

use App\Service\ForecastService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class ForecastController extends AbstractController
{
    #[Route('/api/forecast', name: 'api_forecast', methods: ['GET'])]
    public function forecast(ForecastService $forecast): JsonResponse
    {
        // Projected balance at +30/+60/+90 days. / Saldo proyectado a +30/+60/+90 días.
        return $this->json($forecast->summary());
    }
}
