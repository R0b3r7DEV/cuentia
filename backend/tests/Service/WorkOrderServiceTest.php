<?php

namespace App\Tests\Service;

use App\Entity\Invoice;
use App\Entity\User;
use App\Entity\WorkOrder;
use App\Repository\CustomerRepository;
use App\Service\InvoiceService;
use App\Service\WorkOrderService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class WorkOrderServiceTest extends TestCase
{
    private function service(?InvoiceService $invoices = null): WorkOrderService
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $customers = $this->createStub(CustomerRepository::class);
        $customers->method('findOneBy')->willReturn(null);

        return new WorkOrderService($em, $customers, $invoices ?? $this->createStub(InvoiceService::class));
    }

    public function testCreateStoresTitleLinesAndLabourAndDefaultsToPendiente(): void
    {
        $wo = $this->service()->create(new User(), [
            'title' => 'Cambiar diferencial',
            'customer' => ['name' => 'Vecino', 'taxId' => 'B1'],
            'laborHours' => '1.5', 'laborRate' => '30',
            'lines' => [['description' => 'Diferencial 40A', 'quantity' => 1, 'unitPrice' => '45.00', 'vatRate' => '21.00']],
        ]);

        self::assertSame('Cambiar diferencial', $wo->getTitle());
        self::assertSame('pendiente', $wo->getStatus());
        self::assertCount(1, $wo->getLines());
        self::assertSame('1.50', $wo->getLaborHours());
        self::assertSame('30.00', $wo->getLaborRate());
        // labour amount in cents: 1.5 h × 30 €/h = 45.00 €
        self::assertSame(4500, $wo->laborBaseCents());
    }

    public function testRejectsWorkOrderWithoutTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service()->create(new User(), ['customer' => ['name' => 'X', 'taxId' => 'Y'], 'lines' => []]);
    }

    public function testInvalidStatusFallsBackToPendiente(): void
    {
        $wo = $this->service()->create(new User(), ['title' => 'x', 'customer' => ['name' => 'X', 'taxId' => 'Y'], 'status' => 'nonsense']);
        self::assertSame('pendiente', $wo->getStatus());
    }

    /** Idempotency guard: an already-converted order returns its invoice without invoicing again. */
    public function testConvertIsIdempotentAndNeverInvoicesTwice(): void
    {
        $invoices = $this->createMock(InvoiceService::class);
        $invoices->expects(self::never())->method('create'); // must NOT create a second invoice

        $existing = new Invoice();
        $wo = (new WorkOrder())->setConvertedInvoice($existing);

        $result = $this->service($invoices)->convert(new User(), $wo);
        self::assertSame($existing, $result);
    }
}
