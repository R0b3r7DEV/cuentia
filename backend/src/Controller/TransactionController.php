<?php

namespace App\Controller;

use App\Entity\Transaction;
use App\Repository\TransactionRepository;
use App\Service\CategorizerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class TransactionController extends AbstractController
{
    #[Route('/api/transactions', name: 'api_transactions_list', methods: ['GET'])]
    public function list(TransactionRepository $repo): JsonResponse
    {
        // We map each entity to a plain array (a small DTO shape) instead of returning
        // the entity directly. This gives us full control over the API contract and
        // avoids leaking internal fields or triggering lazy-loading surprises.
        // ES: Convertimos cada entidad en un array plano (una especie de DTO) en vez de
        // devolver la entidad tal cual. Así controlamos el contrato de la API y evitamos
        // exponer campos internos o sorpresas de carga perezosa (lazy-loading).
        $rows = array_map(
            static fn (Transaction $t): array => [
                'id'             => $t->getId(),
                'bookedAt'       => $t->getBookedAt()->format('Y-m-d'),
                'description'    => $t->getDescription(),
                'amount'         => $t->getAmount(),      // decimal as string / decimal como string
                'currency'       => $t->getCurrency(),
                'category'       => $t->getCategory()?->getName(),
                'categorySource' => $t->getCategorySource(),
            ],
            $repo->findBy([], ['bookedAt' => 'DESC', 'id' => 'DESC']),
        );

        return $this->json($rows);
    }

    #[Route('/api/transactions/categorize', name: 'api_transactions_categorize', methods: ['POST'])]
    public function categorize(CategorizerService $categorizer): JsonResponse
    {
        // Categorize every uncategorized transaction (AI if configured, rules otherwise).
        // ES: Categoriza cada movimiento sin categoría (IA si está configurada, reglas si no).
        return $this->json($categorizer->categorizeUncategorized());
    }
}
