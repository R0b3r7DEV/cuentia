<?php

namespace App\Controller;

use App\Assistant\AssistantException;
use App\Assistant\CieAssistant;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * The CIE assistant's Claude calls. Each one spends the user's own Anthropic credit, so they are rate-limited
 * per user. Errors come back as fixed codes.
 * ES: Las llamadas a Claude del asistente del CIE. Cada una gasta el crédito de la clave del propio usuario,
 * así que tienen límite por usuario. Los errores salen como códigos fijos.
 */
class CieAssistantController extends AbstractController
{
    public function __construct(
        private CieAssistant $assistant,
        #[Autowire(service: 'limiter.cie_assistant')] private RateLimiterFactoryInterface $limiter,
    ) {}

    /** Whether the Claude buttons can work (the user has an API key). */
    #[Route('/api/certificates/assistant/status', name: 'api_cie_assistant_status', methods: ['GET'])]
    public function status(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json(['enabled' => $this->assistant->enabled($user), 'steps' => CieAssistant::STEPS]);
    }

    /** Multipart, field "bill": a photo (JPEG/PNG) or PDF of the client's electricity bill. Not stored. */
    #[Route('/api/certificates/assistant/bill', name: 'api_cie_assistant_bill', methods: ['POST'])]
    public function bill(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $file = $request->files->get('bill');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return $this->json(['error' => 'invalid_image'], 400);
        }

        return $this->call($user, fn () => $this->assistant->readBill($user, (string) file_get_contents($file->getPathname())));
    }

    /** Body: {step, question, form}. */
    #[Route('/api/certificates/assistant/help', name: 'api_cie_assistant_help', methods: ['POST'])]
    public function help(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'invalid_json'], 400);
        }

        return $this->call($user, fn () => ['answer' => $this->assistant->help(
            $user,
            (string) ($data['step'] ?? ''),
            (string) ($data['question'] ?? ''),
            is_array($data['form'] ?? null) ? $data['form'] : [],
        )]);
    }

    /** Body: {form}. Required fields still empty are decided here, by rule, and passed to Claude. */
    #[Route('/api/certificates/assistant/review', name: 'api_cie_assistant_review', methods: ['POST'])]
    public function review(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $form = is_array($data) && is_array($data['form'] ?? null) ? $data['form'] : null;
        if ($form === null) {
            return $this->json(['error' => 'invalid_json'], 400);
        }
        $missing = array_values(array_filter(
            ['address', 'titularName', 'companyName', 'companyRegNumber', 'maxPower', 'supplyType', 'igaCurrent', 'differentialSensitivity'],
            static fn (string $k): bool => trim((string) ($form[$k] ?? '')) === '',
        ));

        return $this->call($user, fn () => ['missing' => $missing] + $this->assistant->review($user, $form, $missing));
    }

    private function call(User $user, callable $action): JsonResponse
    {
        if (!$this->assistant->enabled($user)) {
            return $this->json(['error' => 'no_api_key'], 409);
        }
        $limit = $this->limiter->create('user-' . $user->getId())->consume();
        if (!$limit->isAccepted()) {
            return $this->json(['error' => 'too_many_requests'], 429, [
                'Retry-After' => (string) max(1, $limit->getRetryAfter()->getTimestamp() - time()),
            ]);
        }

        try {
            return $this->json($action());
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (AssistantException $e) {
            $status = match ($e->getMessage()) {
                'no_api_key', 'invalid_api_key' => 409,
                'declined' => 422,
                default => 502,
            };

            return $this->json(['error' => $e->getMessage()], $status);
        }
    }
}
