<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\TransactionRepository;
use App\Service\CategorizerService;
use App\Service\ImportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Loads a set of realistic sample movements for the current user, so a fresh account
 * (or a recruiter trying the live demo) never sees an empty app.
 * ES: Carga movimientos de ejemplo realistas para el usuario actual, para que una cuenta
 * nueva (o un reclutador probando la demo) nunca vea la app vacía.
 */
class DemoController extends AbstractController
{
    #[Route('/api/demo/load', name: 'api_demo_load', methods: ['POST'])]
    public function load(
        TransactionRepository $repo,
        ImportService $import,
        CategorizerService $categorizer,
        #[CurrentUser] User $user,
    ): JsonResponse {
        // Don't duplicate: only load when the account is empty.
        // ES: No duplicar: solo cargar cuando la cuenta está vacía.
        if ($repo->findForUser($user) !== []) {
            return $this->json(['loaded' => 0, 'message' => 'Account already has data']);
        }

        // Reuse the tested import + categorization pipeline. / Reutiliza el pipeline probado.
        $import->import($this->sampleCsv(), $user);
        $categorizer->categorizeUncategorized($user);

        return $this->json(['loaded' => count($repo->findForUser($user))]);
    }

    /** Two months of realistic Spanish freelancer movements (semicolon + comma decimals). */
    private function sampleCsv(): string
    {
        return implode("\n", [
            'fecha;concepto;importe',
            '2026-05-02;Cliente ACME - factura 010;1815,00',
            '2026-05-03;Alquiler oficina;-450,00',
            '2026-05-05;Compra Mercadona;-64,20',
            '2026-05-09;Gasolina Cepsa;-55,00',
            '2026-05-14;Suscripción Adobe Creative Cloud;-24,99',
            '2026-05-18;Cuota autónomo Seguridad Social;-294,00',
            '2026-05-20;Factura Iberdrola luz;-72,30',
            '2026-05-25;Restaurante La Tagliatella;-41,50',
            '2026-06-02;Cliente Beta SL - factura 011;1210,00',
            '2026-06-04;Compra Carrefour;-88,90',
            '2026-06-08;Gasolina Repsol;-60,00',
            '2026-06-12;Suscripción Adobe Creative Cloud;-24,99',
            '2026-06-18;Cuota autónomo Seguridad Social;-294,00',
            '2026-06-22;Cliente Gamma - factura 012;968,00',
            '2026-06-28;Restaurante Vips;-38,50',
        ]) . "\n";
    }
}
