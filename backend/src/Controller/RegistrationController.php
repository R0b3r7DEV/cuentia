<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    private const MIN_PASSWORD_LENGTH = 8;

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
        UserRepository $users,
        \App\Service\EmailVerificationService $verification,
        #[Autowire(service: 'limiter.registration_ip')] RateLimiterFactoryInterface $registrationLimiter,
    ): JsonResponse {
        // No mass account creation from one place. / Sin crear cuentas en masa desde un mismo sitio.
        if (!$registrationLimiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
            return $this->json(['error' => 'Too many sign-ups, please try again later', 'code' => 'too_many_requests'], 429);
        }
        $data = json_decode($request->getContent(), true);
        $email = is_array($data) ? trim((string) ($data['email'] ?? '')) : '';
        $password = is_array($data) ? (string) ($data['password'] ?? '') : '';

        // Each failure carries a stable `code` the frontend translates into a friendly message.
        // ES: Cada fallo lleva un `code` estable que el frontend traduce a un mensaje claro.
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'A valid email address is required', 'code' => 'invalid_email'], 400);
        }
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            return $this->json([
                'error' => 'The password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters long',
                'code' => 'weak_password',
            ], 400);
        }
        if ($users->findOneBy(['email' => $email]) !== null) {
            return $this->json(['error' => 'Email already registered', 'code' => 'email_taken'], 409);
        }

        $user = (new User())->setEmail($email);
        $user->setPassword($hasher->hashPassword($user, $password)); // never store plain passwords

        $em->persist($user);
        $em->flush();
        // The account opens once the e-mail is confirmed. / La cuenta se abre al confirmar el email.
        $verification->send($user);

        return $this->json(['email' => $user->getUserIdentifier(), 'verification' => 'sent'], 201);
    }
}
