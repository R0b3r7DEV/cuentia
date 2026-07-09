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
use Symfony\Contracts\HttpClient\HttpClientInterface;

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
        $http = $this->createStub(HttpClientInterface::class);

        // No key configured → deterministic fallback (no HTTP call).
        $credentials = $this->createStub(CredentialStore::class);
        $credentials->method('anthropicKey')->willReturn('');
        $chat = new ChatService($this->repo($txs), $vat, $irpf, $http, $credentials);

        $result = $chat->answer('How much did I spend on fuel?', new User());

        self::assertSame('fallback', $result['source']);
        self::assertStringContainsString('Current balance', $result['answer']);
        self::assertStringContainsString('VAT', $result['answer']);
        self::assertStringContainsString('Combustible', $result['answer']);
    }
}
