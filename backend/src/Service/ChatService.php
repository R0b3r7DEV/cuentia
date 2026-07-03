<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\TransactionRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Answers natural-language questions about the user's finances.
 * ES: Responde preguntas en lenguaje natural sobre las finanzas del usuario.
 *
 * AI is optional (the project's rule): with a Claude key we answer in natural language grounded on the
 * user's data; without a key we return a deterministic summary so the feature is never dead.
 * ES: La IA es opcional (regla del proyecto): con clave de Claude respondemos en lenguaje natural sobre
 * los datos del usuario; sin clave devolvemos un resumen determinista para que la función nunca esté muerta.
 */
class ChatService
{
    public function __construct(
        private TransactionRepository $transactions,
        private VatService $vat,
        private IrpfService $irpf,
        private HttpClientInterface $http,
        #[Autowire('%env(ANTHROPIC_API_KEY)%')] private string $apiKey = '',
    ) {}

    /**
     * @return array{answer:string, source:string}
     */
    public function answer(string $question, User $user): array
    {
        $context = $this->buildContext($user);

        if ($this->apiKey !== '') {
            try {
                return ['answer' => $this->askClaude($question, $context), 'source' => 'ai'];
            } catch (\Throwable) {
                // fall through to the deterministic summary
            }
        }

        return ['answer' => $context, 'source' => 'fallback'];
    }

    /** A compact, factual snapshot of the finances used to ground the answer. */
    private function buildContext(User $user): string
    {
        $incomeC = 0;
        $expenseC = 0;
        $byCategory = []; // name => expense cents (positive)

        foreach ($this->transactions->findForUser($user) as $tx) {
            $cents = (int) round((float) $tx->getAmount() * 100);
            if ($cents >= 0) {
                $incomeC += $cents;
            } else {
                $expenseC += -$cents;
                $name = $tx->getCategory()?->getName() ?? 'Sin categoría';
                $byCategory[$name] = ($byCategory[$name] ?? 0) + (-$cents);
            }
        }
        arsort($byCategory);

        $eur = static fn (int $c) => number_format($c / 100, 2, '.', '') . ' EUR';

        $lines = [];
        $lines[] = 'Current balance: ' . $eur($incomeC - $expenseC);
        $lines[] = 'Total income: ' . $eur($incomeC) . '; total expenses: ' . $eur($expenseC);

        $cats = [];
        foreach (array_slice($byCategory, 0, 6, true) as $name => $c) {
            $cats[] = "$name " . $eur($c);
        }
        if ($cats !== []) {
            $lines[] = 'Expenses by category: ' . implode(', ', $cats);
        }

        $vat = $this->vat->summary($user);
        $lines[] = "VAT: output {$vat['outputVat']}, input {$vat['inputVat']}, net {$vat['net']} EUR";

        $irpf = $this->irpf->summary($user);
        if ($irpf['nextDeadline'] !== null) {
            $q = $irpf['nextDeadline']['quarter'];
            $payment = $irpf['quarters'][$q - 1]['payment'] ?? '0.00';
            $lines[] = "IRPF modelo 130: next payment {$payment} EUR (Q{$q}), due {$irpf['nextDeadline']['date']} in {$irpf['nextDeadline']['daysLeft']} days";
        }

        return implode("\n", $lines);
    }

    private function askClaude(string $question, string $context): string
    {
        $response = $this->http->request('POST', 'https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ],
            'json' => [
                'model' => 'claude-haiku-4-5',
                'max_tokens' => 400,
                'system' => "You are Cuentia, a concise financial assistant for a Spanish freelancer. "
                    . "Answer the question using ONLY the data below. Reply in the same language as the question. "
                    . "If the data doesn't contain the answer, say so briefly.\n\nDATA:\n" . $context,
                'messages' => [['role' => 'user', 'content' => $question]],
            ],
            'timeout' => 20,
        ]);

        $data = $response->toArray(false);
        $text = $data['content'][0]['text'] ?? '';
        if ($text === '') {
            throw new \RuntimeException('empty AI response');
        }

        return $text;
    }
}
