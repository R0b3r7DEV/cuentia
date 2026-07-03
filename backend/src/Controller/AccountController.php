<?php

namespace App\Controller;

use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Account management: clear a user's data, or delete the account entirely (GDPR art. 17).
 * ES: Gestión de cuenta: limpiar los datos del usuario, o borrar la cuenta por completo (RGPD art. 17).
 */
class AccountController extends AbstractController
{
    /** Delete all of the current user's transactions (keeps the account). */
    #[Route('/api/account/clear', name: 'api_account_clear', methods: ['POST'])]
    public function clear(EntityManagerInterface $em, #[CurrentUser] User $user): JsonResponse
    {
        $cleared = $this->deleteUserTransactions($em, $user);
        return $this->json(['cleared' => $cleared]);
    }

    /** Delete the account and all its data, then invalidate the session (right to erasure). */
    #[Route('/api/account', name: 'api_account_delete', methods: ['DELETE'])]
    public function delete(EntityManagerInterface $em, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $this->deleteUserTransactions($em, $user);
        $em->remove($user);
        $em->flush();

        // End the session so the (now deleted) user is fully logged out.
        // ES: Cierra la sesión para que el usuario (ya borrado) quede completamente deslogueado.
        $request->getSession()->invalidate();

        return $this->json(['deleted' => true]);
    }

    private function deleteUserTransactions(EntityManagerInterface $em, User $user): int
    {
        return $em->createQuery('DELETE FROM ' . Transaction::class . ' t WHERE t.user = :u')
            ->setParameter('u', $user)
            ->execute();
    }
}
