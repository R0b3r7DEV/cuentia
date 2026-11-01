<?php

namespace App\Tests\Entity;

use App\Entity\WorkOrder;
use App\Entity\WorkOrderLine;
use PHPUnit\Framework\TestCase;

class WorkOrderTest extends TestCase
{
    private function labour(string $hours, string $rate, string $vat = '21.00'): WorkOrder
    {
        return (new WorkOrder())->setLaborHours($hours)->setLaborRate($rate)->setLaborVatRate($vat);
    }

    public function testLabourIsComputedInIntegerCents(): void
    {
        self::assertSame(7000, $this->labour('2.00', '35.00')->laborBaseCents());
        // 1.15 h × 33.33 €/h = 38.3295 € → 38.33 €
        self::assertSame(3833, $this->labour('1.15', '33.33')->laborBaseCents());
        // half a cent rounds up, once: 0.50 h × 0.01 €/h = 0.005 € → 0.01 €
        self::assertSame(1, $this->labour('0.50', '0.01')->laborBaseCents());
        self::assertSame(0, $this->labour('0.00', '35.00')->laborBaseCents());
    }

    public function testLabourVatIsRoundedPerLineLikeAnInvoiceLine(): void
    {
        // 70.00 € × 21% = 14.70 €
        self::assertSame(1470, $this->labour('2.00', '35.00')->laborVatCents());
        // 38.33 € × 21% = 8.0493 € → 8.05 €
        self::assertSame(805, $this->labour('1.15', '33.33')->laborVatCents());
        // 0.10 € × 21% = 0.021 € → 0.02 €; and 10% of 0.05 € = 0.005 € → 0.01 €
        self::assertSame(2, $this->labour('1.00', '0.10')->laborVatCents());
        self::assertSame(1, $this->labour('1.00', '0.05', '10.00')->laborVatCents());
    }

    public function testTotalsAddMaterialsAndLabour(): void
    {
        $wo = $this->labour('2.00', '35.00');
        $wo->addLine((new WorkOrderLine())->setDescription('Magnetotérmico')->setQuantity(2)->setUnitPrice('12.00')->setVatRate('21.00'));

        // materials 24.00 + 5.04 VAT; labour 70.00 + 14.70 VAT
        self::assertSame(['base' => 9400, 'vat' => 1974, 'total' => 11374], $wo->totalsCents());
    }

    public function testFacturadoCanNeverBeReachedByHand(): void
    {
        foreach (WorkOrder::STATUSES as $status) {
            $wo = (new WorkOrder())->setStatus($status);
            self::assertFalse($wo->canTransitionTo('facturado'), "$status → facturado must go through convert()");
        }
    }

    public function testLifecycleMoves(): void
    {
        $wo = new WorkOrder();
        self::assertTrue($wo->canTransitionTo('en_curso'));
        self::assertFalse($wo->canTransitionTo('terminado'), 'cannot finish a job that never started');

        $wo->setStatus('en_curso');
        self::assertTrue($wo->canTransitionTo('terminado'));
        self::assertTrue($wo->canTransitionTo('pendiente'));

        $wo->setStatus('terminado');
        self::assertTrue($wo->canTransitionTo('en_curso'), 'a finished job can be reopened');
        self::assertFalse($wo->isEditable());

        $wo->setStatus('facturado');
        self::assertSame([], WorkOrder::TRANSITIONS['facturado']);
        self::assertFalse($wo->isEditable());
    }

    public function testReopeningDropsTheSignatureAndTheWaiver(): void
    {
        $wo = (new WorkOrder())->sign('png-bytes', 'María');
        self::assertTrue($wo->hasSignature());

        $wo->clearSignature();
        self::assertFalse($wo->hasSignature());
        self::assertNull($wo->getSignaturePng());
        self::assertFalse($wo->waiveSignature()->clearSignature()->isSignatureWaived());
    }
}
