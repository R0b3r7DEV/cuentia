<?php

namespace App\Service;

use App\Entity\Category;
use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Assigns a category to each transaction.
 * ES: Asigna una categoría a cada movimiento.
 *
 * Design principle: AI is OPTIONAL. If an Anthropic API key is configured we ask Claude;
 * otherwise (or if the call fails) we fall back to a deterministic rule engine. The app is
 * never useless without the AI.
 * ES: Principio de diseño: la IA es OPCIONAL. Si hay clave de Anthropic preguntamos a Claude;
 * si no (o si falla) usamos un motor de reglas determinista. La app nunca es inútil sin la IA.
 */
class CategorizerService
{
    /** The fixed set of categories (name => kind). / El conjunto fijo de categorías (nombre => tipo). */
    private const CATEGORIES = [
        // expenses / gastos
        'Supermercado'             => 'expense',
        'Combustible'              => 'expense',
        'Restauración'             => 'expense',
        'Software y suscripciones' => 'expense',
        'Cuota autónomo'           => 'expense',
        'Alquiler'                 => 'expense',
        'Suministros'              => 'expense',
        'Otros gastos'             => 'expense',
        // income / ingresos
        'Nómina'                   => 'income',
        'Ingresos de cliente'      => 'income',
        'Otros ingresos'           => 'income',
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private TransactionRepository $transactions,
        private CategoryRepository $categories,
        private HttpClientInterface $http,
        #[Autowire('%env(ANTHROPIC_API_KEY)%')] private string $apiKey = '',
    ) {}

    /**
     * Categorize every transaction that has no category yet.
     * ES: Categoriza cada movimiento que aún no tiene categoría.
     *
     * @return array{categorized:int, byAi:int, byRule:int}
     */
    public function categorizeUncategorized(User $user): array
    {
        $pending = $this->transactions->findBy(['user' => $user, 'category' => null]);
        $byAi = 0;
        $byRule = 0;

        foreach ($pending as $tx) {
            [$name, $source] = $this->decide($tx->getDescription(), $tx->getAmount());
            $tx->setCategory($this->getOrCreateCategory($name));
            $tx->setCategorySource($source);
            $source === 'ai' ? $byAi++ : $byRule++;
        }

        $this->em->flush();

        return ['categorized' => count($pending), 'byAi' => $byAi, 'byRule' => $byRule];
    }

    /**
     * Decide the category name for a description/amount.
     * @return array{0:string,1:string} [categoryName, source ('ai'|'rule')]
     */
    private function decide(string $description, string $amount): array
    {
        if ($this->apiKey !== '') {
            try {
                return [$this->aiCategorize($description, $amount), 'ai'];
            } catch (\Throwable) {
                // fall through to rules / caemos a las reglas
            }
        }

        return [$this->ruleCategorize($description, $amount), 'rule'];
    }

    /** Deterministic keyword rules (Spanish vocabulary). / Reglas por palabras clave (vocabulario español). */
    private function ruleCategorize(string $description, string $amount): string
    {
        $d = mb_strtolower($description);
        $isExpense = str_starts_with(trim($amount), '-');

        $expenseRules = [
            'Supermercado'             => ['mercadona', 'carrefour', 'lidl', 'aldi', 'dia', 'consum', 'eroski', 'alcampo', 'supermercado'],
            'Combustible'              => ['gasolina', 'repsol', 'cepsa', 'galp', 'shell', 'carburante', 'gasolinera'],
            'Restauración'             => ['restaurante', 'bar ', 'cafe', 'cafetería', 'tagliatella', 'mcdonald', 'burger', 'telepizza', 'glovo', 'ubereats'],
            'Software y suscripciones' => ['adobe', 'netflix', 'spotify', 'suscrip', 'microsoft', 'openai', 'github', 'aws', 'hosting', 'dominio', 'google'],
            'Cuota autónomo'           => ['seguridad social', 'autónomo', 'autonomo', 'tgss', 'reta', 'cuota'],
            'Alquiler'                 => ['alquiler', 'arrendamiento'],
            'Suministros'              => ['iberdrola', 'endesa', 'naturgy', 'movistar', 'vodafone', 'orange', 'agua', 'luz', 'gas', 'internet', 'telefón', 'telefon'],
        ];
        $incomeRules = [
            'Nómina'              => ['nómina', 'nomina', 'salario', 'payroll'],
            'Ingresos de cliente' => ['factura', 'cliente', 'invoice'],
        ];

        $rules = $isExpense ? $expenseRules : $incomeRules;
        foreach ($rules as $category => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($d, $kw)) {
                    return $category;
                }
            }
        }

        return $isExpense ? 'Otros gastos' : 'Otros ingresos';
    }

    /** Ask Claude to pick one category from the allowed list. Throws on any problem. */
    private function aiCategorize(string $description, string $amount): string
    {
        $allowed = implode(', ', array_keys(self::CATEGORIES));
        $sign = str_starts_with(trim($amount), '-') ? 'expense' : 'income';

        $response = $this->http->request('POST', 'https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ],
            'json' => [
                'model' => 'claude-haiku-4-5',
                'max_tokens' => 40,
                'system' => "You classify Spanish bank movements. Reply with ONLY a JSON object "
                    . '{"category":"<one of the allowed categories>"}. Allowed categories: ' . $allowed . '.',
                'messages' => [[
                    'role' => 'user',
                    'content' => "Movement ($sign): \"$description\". Amount: $amount.",
                ]],
            ],
            'timeout' => 15,
        ]);

        $data = $response->toArray(false);
        $text = $data['content'][0]['text'] ?? '';
        if (!preg_match('/\{.*\}/s', $text, $m)) {
            throw new \RuntimeException('no JSON in AI response');
        }
        $name = json_decode($m[0], true)['category'] ?? '';

        if (!isset(self::CATEGORIES[$name])) {
            throw new \RuntimeException("AI returned an unknown category: '$name'");
        }

        return $name;
    }

    /** Find the Category by name, creating it (with the right kind) if needed. */
    private function getOrCreateCategory(string $name): Category
    {
        $category = $this->categories->findOneBy(['name' => $name]);
        if ($category === null) {
            $category = (new Category())
                ->setName($name)
                ->setKind(self::CATEGORIES[$name] ?? 'expense');
            $this->em->persist($category);
        }

        return $category;
    }
}
