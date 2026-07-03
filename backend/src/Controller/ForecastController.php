<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\ForecastService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class ForecastController extends AbstractController
{
    #[Route('/api/forecast', name: 'api_forecast', methods: ['GET'])]
    public function forecast(ForecastService $forecast, #[CurrentUser] User $user): JsonResponse
    {
        // Projected balance at +30/+60/+90 days. / Saldo proyectado a +30/+60/+90 días.
        return $this->json($forecast->summary($user));
    }
}
