<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * No login until the e-mail is confirmed. Checked AFTER the password (checkPostAuth): with a wrong password the
 * answer stays the generic "bad credentials", so this never reveals whether an address has an account.
 * ES: No se entra sin confirmar el email. Se comprueba DESPUÉS de la contraseña: con una contraseña mala la
 * respuesta sigue siendo la genérica, así no revela si una dirección tiene cuenta.
 */
final class VerifiedEmailChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void {}

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if ($user instanceof User && !$user->isEmailVerified()) {
            throw new CustomUserMessageAccountStatusException('email_not_verified');
        }
    }
}
