<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
        UserRepository $users,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email = is_array($data) ? trim((string) ($data['email'] ?? '')) : '';
        $password = is_array($data) ? (string) ($data['password'] ?? '') : '';

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
            return $this->json(['error' => 'A valid email and a password of at least 6 characters are required'], 400);
        }
        if ($users->findOneBy(['email' => $email]) !== null) {
            return $this->json(['error' => 'Email already registered'], 409);
        }

        $user = (new User())->setEmail($email);
        $user->setPassword($hasher->hashPassword($user, $password)); // never store plain passwords

        $em->persist($user);
        $em->flush();

        return $this->json(['email' => $user->getUserIdentifier()], 201);
    }
}
