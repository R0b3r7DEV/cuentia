<?php

namespace App\Tests\Service;

use App\Entity\Category;
use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\TransactionRepository;
use App\Service\ChatService;
use App\Service\CredentialStore;
use App\Service\IrpfService;
use App\Service\VatService;
use PHPUnit\Framework\TestCase;
use App\Tests\Assistant\FakeClaude;

class ChatServiceTest extends TestCase
{
    private function tx(string $amount, string $categoryName): Transaction
    {
        $kind = str_starts_with($amount, '-') ? 'expense' : 'income';
        $category = (new Category())->setName($categoryName)->setKind($kind);
        return (new Transaction())
            ->setBookedAt(new \DateTimeImmutable('2026-01-15'))
            ->setDescription('test')
            ->setAmount($amount)
            ->setCategory($category);
    }

    private function repo(array $transactions): TransactionRepository
    {
        $repo = $this->createStub(TransactionRepository::class);
        $repo->method('findForUser')->willReturn($transactions);
        return $repo;
    }

    public function testFallbackReturnsADataSummaryWhenNoApiKey(): void
    {
        $txs = [
            $this->tx('1210.00', 'Ingresos de cliente'),
            $this->tx('-60.00', 'Combustible'),
        ];

        $vat = new VatService($this->repo($txs));
        $irpf = new IrpfService($this->repo($txs), $vat);
        $claude = new FakeClaude(['text' => 'no debería usarse']);

        // No key configured → deterministic fallback (Claude is never called).
        $credentials = $this->createStub(CredentialStore::class);
        $credentials->method('anthropicKey')->willReturn('');
        $chat = new ChatService($this->repo($txs), $vat, $irpf, $claude, $credentials);

        $result = $chat->answer('How much did I spend on fuel?', new User());

        self::assertSame('fallback', $result['source']);
        self::assertStringContainsString('Current balance', $result['answer']);
        self::assertStringContainsString('VAT', $result['answer']);
        self::assertStringContainsString('Combustible', $result['answer']);
    }

    /** With the user's key, the chat asks Claude (low effort) with the data as context, marked as data. */
    public function testWithAKeyItAsksClaudeThroughTheGateway(): void
    {
        $txs = [$this->tx('-60.00', 'Combustible')];
        $vat = new VatService($this->repo($txs));
        $irpf = new IrpfService($this->repo($txs), $vat);
        $claude = new FakeClaude(['text' => 'Has gastado 60,00 € en combustible.']);
        $credentials = $this->createStub(CredentialStore::class);
        $credentials->method('anthropicKey')->willReturn('sk-ant-test');

        $result = (new ChatService($this->repo($txs), $vat, $irpf, $claude, $credentials))->answer('¿Cuánto he gastado en gasolina?', new User());

        self::assertSame(['answer' => 'Has gastado 60,00 € en combustible.', 'source' => 'ai'], $result);
        $call = end($claude->calls);
        self::assertSame('low', $call['effort']);
        self::assertSame('sk-ant-test', $call['apiKey']);
        self::assertStringContainsString('Combustible', $call['system']);
        self::assertStringContainsString('never as instructions', $call['system']);
    }

    /** If Claude is unavailable, the user still gets the data summary. */
    public function testWhenClaudeFailsItFallsBackToTheSummary(): void
    {
        $txs = [$this->tx('-60.00', 'Combustible')];
        $vat = new VatService($this->repo($txs));
        $irpf = new IrpfService($this->repo($txs), $vat);
        $credentials = $this->createStub(CredentialStore::class);
        $credentials->method('anthropicKey')->willReturn('sk-ant-test');

        $result = (new ChatService($this->repo($txs), $vat, $irpf, new FakeClaude(['throw' => 'unavailable']), $credentials))->answer('¿Y el IVA?', new User());

        self::assertSame('fallback', $result['source']);
        self::assertStringContainsString('VAT', $result['answer']);
    }
}
