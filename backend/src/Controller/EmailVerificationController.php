<?php

namespace App\Controller;

use App\Service\EmailVerificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public endpoints of the e-mail confirmation: confirm with the token from the e-mail, and ask for it again.
 * Both rate-limited (config/packages/rate_limiter.yaml). / Confirmar con el token y volver a pedir el correo.
 */
class EmailVerificationController extends AbstractController
{
    /** Body: {token}. 200 when confirmed. / 200 al confirmar. */
    #[Route('/api/email/verify', name: 'api_email_verify', methods: ['POST'])]
    public function verify(
        Request $request,
        EmailVerificationService $verification,
        #[Autowire(service: 'limiter.email_verify_attempt')] RateLimiterFactoryInterface $attempts,
    ): JsonResponse {
        if (!$attempts->create((string) $request->getClientIp())->consume()->isAccepted()) {
            return $this->json(['code' => 'too_many_requests'], 429);
        }
        $data = json_decode($request->getContent(), true);
        try {
            $verification->verify(is_array($data) ? (string) ($data['token'] ?? '') : '');
        } catch (\InvalidArgumentException $e) {
            return $this->json(['code' => $e->getMessage()], 400);
        }

        return $this->json(['code' => 'email_verified']);
    }

    /** Body: {email}. Always 202 with the same body, whether the account exists or not. / Siempre 202. */
    #[Route('/api/email/resend', name: 'api_email_resend', methods: ['POST'])]
    public function resend(
        Request $request,
        EmailVerificationService $verification,
        #[Autowire(service: 'limiter.email_verify_ip')] RateLimiterFactoryInterface $byIp,
        #[Autowire(service: 'limiter.email_verify_email')] RateLimiterFactoryInterface $byEmail,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email = is_array($data) ? trim((string) ($data['email'] ?? '')) : '';
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['code' => 'invalid_email'], 400);
        }
        $ip = $byIp->create((string) $request->getClientIp())->consume();
        $mail = $byEmail->create(mb_strtolower($email))->consume();
        if (!$ip->isAccepted() || !$mail->isAccepted()) {
            return $this->json(['code' => 'too_many_requests'], 429);
        }
        $verification->resend($email);

        return $this->json(['code' => 'verification_sent'], 202);
    }
}
