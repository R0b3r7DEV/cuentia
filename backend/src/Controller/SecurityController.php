<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class SecurityController extends AbstractController
{
    /**
     * The firewall's json_login authenticator handles the POST; this method runs on success
     * and returns the authenticated user.
     * ES: El autenticador json_login del firewall procesa el POST; este método corre al tener éxito
     * y devuelve el usuario autenticado.
     */
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(#[CurrentUser] ?User $user): JsonResponse
    {
        if ($user === null) {
            return $this->json(['error' => 'Invalid credentials'], 401);
        }
        return $this->json(['email' => $user->getUserIdentifier()]);
    }

    /** Returns the current user, or 401 if nobody is logged in. */
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(#[CurrentUser] ?User $user): JsonResponse
    {
        return $user !== null
            ? $this->json(['email' => $user->getUserIdentifier()])
            : $this->json(null, 401);
    }

    /** Intercepted by the firewall's logout key. / Interceptado por la clave logout del firewall. */
    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(): void
    {
        throw new \LogicException('This is intercepted by the logout key on the firewall.');
    }
}
