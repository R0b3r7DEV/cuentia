<?php

namespace App\Controller;

use App\Service\PasswordResetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public endpoints to recover a forgotten password. Both are rate-limited (config/packages/rate_limiter.yaml).
 * ES: Endpoints públicos para recuperar una contraseña olvidada. Los dos tienen límite de peticiones.
 */
class PasswordResetController extends AbstractController
{
    /**
     * Body: {email}. Always 202 with the same body, whether the account exists or not.
     * ES: Siempre 202 con la misma respuesta, exista o no la cuenta.
     */
    #[Route('/api/password/forgot', name: 'api_password_forgot', methods: ['POST'])]
    public function forgot(
        Request $request,
        PasswordResetService $resets,
        #[Autowire(service: 'limiter.password_reset_ip')] RateLimiterFactoryInterface $byIp,
        #[Autowire(service: 'limiter.password_reset_email')] RateLimiterFactoryInterface $byEmail,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email = is_array($data) ? trim((string) ($data['email'] ?? '')) : '';
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['code' => 'invalid_email'], 400);
        }

        $ip = $byIp->create((string) $request->getClientIp())->consume();
        $mail = $byEmail->create(mb_strtolower($email))->consume();
        if (!$ip->isAccepted() || !$mail->isAccepted()) {
            return $this->tooMany(max($ip->getRetryAfter()->getTimestamp(), $mail->getRetryAfter()->getTimestamp()));
        }

        $resets->request($email);

        return $this->json(['code' => 'reset_requested'], 202);
    }

    /** Body: {token, password}. 200 when the password changed. */
    #[Route('/api/password/reset', name: 'api_password_reset', methods: ['POST'])]
    public function reset(
        Request $request,
        PasswordResetService $resets,
        #[Autowire(service: 'limiter.password_reset_attempt')] RateLimiterFactoryInterface $attempts,
    ): JsonResponse {
        $limit = $attempts->create((string) $request->getClientIp())->consume();
        if (!$limit->isAccepted()) {
            return $this->tooMany($limit->getRetryAfter()->getTimestamp());
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['code' => 'invalid_json'], 400);
        }
        try {
            $resets->reset((string) ($data['token'] ?? ''), (string) ($data['password'] ?? ''));
        } catch (\InvalidArgumentException $e) {
            return $this->json(['code' => $e->getMessage()], 400);
        }

        return $this->json(['code' => 'password_changed']);
    }

    private function tooMany(int $retryAt): JsonResponse
    {
        return $this->json(['code' => 'too_many_requests'], 429, ['Retry-After' => (string) max(1, $retryAt - time())]);
    }
}
