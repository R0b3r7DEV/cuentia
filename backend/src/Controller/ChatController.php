<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\ChatService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class ChatController extends AbstractController
{
    #[Route('/api/chat', name: 'api_chat', methods: ['POST'])]
    public function chat(Request $request, ChatService $chat, #[CurrentUser] User $user): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $question = is_array($payload) ? trim((string) ($payload['question'] ?? '')) : '';

        if ($question === '') {
            return $this->json(['error' => 'Empty question'], 400);
        }

        return $this->json($chat->answer($question, $user));
    }
}
