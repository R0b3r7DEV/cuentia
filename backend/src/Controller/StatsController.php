<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Aggregated stats for the dashboard.
 * ES: Estadísticas agregadas para el panel.
 *
 * We use raw SQL (via the DBAL connection) because grouping by month and doing
 * conditional sums is clearest and fastest in SQL. The database does the maths.
 * ES: Usamos SQL directo (vía la conexión DBAL) porque agrupar por mes y hacer
 * sumas condicionales es más claro y rápido en SQL. La base de datos hace las cuentas.
 */
class StatsController extends AbstractController
{
    #[Route('/api/stats', name: 'api_stats', methods: ['GET'])]
    public function stats(Connection $db, #[CurrentUser] User $user): JsonResponse
    {
        $p = ['uid' => $user->getId()];

        // Totals / Totales
        $totals = $db->fetchAssociative(
            "SELECT
                COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) AS income,
                COALESCE(SUM(CASE WHEN amount < 0 THEN amount ELSE 0 END), 0) AS expenses
             FROM transaction WHERE user_id = :uid",
            $p
        ) ?: ['income' => '0', 'expenses' => '0'];

        // Spending by category / Gasto por categoría
        $byCategory = $db->fetchAllAssociative(
            "SELECT COALESCE(c.name, 'Sin categoría') AS category,
                    c.kind AS kind,
                    SUM(t.amount) AS total,
                    COUNT(*) AS count
             FROM transaction t
             LEFT JOIN category c ON c.id = t.category_id
             WHERE t.user_id = :uid
             GROUP BY c.name, c.kind
             ORDER BY total ASC",
            $p
        );

        // Income vs expenses by month / Ingresos vs gastos por mes
        $byMonth = $db->fetchAllAssociative(
            "SELECT to_char(booked_at, 'YYYY-MM') AS month,
                    COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) AS income,
                    COALESCE(SUM(CASE WHEN amount < 0 THEN -amount ELSE 0 END), 0) AS expenses
             FROM transaction WHERE user_id = :uid
             GROUP BY month
             ORDER BY month ASC",
            $p
        );

        return $this->json([
            'totals'     => $totals,
            'byCategory' => $byCategory,
            'byMonth'    => $byMonth,
        ]);
    }
}
