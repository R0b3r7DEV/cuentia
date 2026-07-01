<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Health check endpoint.
 *
 * A "health check" is a tiny endpoint that tools (and humans) can call to verify
 * the API is alive. It touches nothing (no database, no auth), so if it fails,
 * the problem is the app itself. We use it as the very first "green" of the project.
 *
 * ES: Endpoint de "health check". Es un endpoint mínimo que sirve para comprobar
 * que la API está viva. No toca nada (ni base de datos ni auth), así que si falla,
 * el problema es la propia app. Lo usamos como el primer "verde" del proyecto.
 */
class HealthController extends AbstractController
{
    // #[Route] maps an HTTP request (GET /api/health) to this method.
    // ES: #[Route] asocia una petición HTTP (GET /api/health) a este método.
    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        // $this->json() serializes the array to JSON and sets the right headers.
        // ES: $this->json() serializa el array a JSON y pone las cabeceras correctas.
        return $this->json([
            'status'  => 'ok',
            'service' => 'cuentia-api',
        ]);
    }
}
