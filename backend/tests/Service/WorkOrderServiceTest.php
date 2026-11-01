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

    private function order(string $status): WorkOrder
    {
        return (new WorkOrder())->setTitle('Avería')->setStatus($status);
    }

    private function expectCode(string $class, string $code): void
    {
        $this->expectExceptionObject(new $class($code));
    }

    public function testCanBeCreatedStraightAwayInProgressButNeverFinishedOrInvoiced(): void
    {
        $base = ['title' => 'x', 'customer' => ['name' => 'X', 'taxId' => 'Y']];
        self::assertSame('en_curso', $this->service()->create(new User(), $base + ['status' => 'en_curso'])->getStatus());
        self::assertSame('pendiente', $this->service()->create(new User(), $base + ['status' => 'facturado'])->getStatus());
        self::assertSame('pendiente', $this->service()->create(new User(), $base + ['status' => 'terminado'])->getStatus());
    }

    public function testNewOrderTakesTheUsersDefaultLabourRate(): void
    {
        $user = (new User())->setDefaultLaborRate('42.50');
        $wo = $this->service()->create($user, ['title' => 'x', 'customer' => ['name' => 'X', 'taxId' => 'Y']]);
        self::assertSame('42.50', $wo->getLaborRate());

        $own = $this->service()->create($user, ['title' => 'x', 'customer' => ['name' => 'X', 'taxId' => 'Y'], 'laborRate' => '30']);
        self::assertSame('30.00', $own->getLaborRate(), 'an explicit rate wins over the default');
    }

    public function testNegativeHoursAreRejected(): void
    {
        $this->expectCode(\InvalidArgumentException::class, 'invalid_amount');
        $this->service()->create(new User(), ['title' => 'x', 'customer' => ['name' => 'X', 'taxId' => 'Y'], 'laborHours' => '-2']);
    }

    public function testDecimalCommaIsAccepted(): void
    {
        $wo = $this->service()->create(new User(), ['title' => 'x', 'customer' => ['name' => 'X', 'taxId' => 'Y'], 'laborHours' => '1,5']);
        self::assertSame('1.50', $wo->getLaborHours());
    }

    public function testCannotJumpFromPendingToFinished(): void
    {
        $this->expectCode(\DomainException::class, 'invalid_transition');
        $this->service()->transition($this->order('pendiente'), 'terminado');
    }

    public function testFacturadoIsRejectedAsAManualTransition(): void
    {
        $this->expectCode(\DomainException::class, 'invalid_transition');
        $this->service()->transition($this->order('terminado'), 'facturado');
    }

    public function testFinishingNeedsTheSignature(): void
    {
        $this->expectCode(\DomainException::class, 'signature_required');
        $this->service()->transition($this->order('en_curso'), 'terminado');
    }

    public function testFinishingWithoutSignatureRecordsThatTheClientWasAbsent(): void
    {
        $wo = $this->service()->transition($this->order('en_curso'), 'terminado', withoutSignature: true);
        self::assertSame('terminado', $wo->getStatus());
        self::assertTrue($wo->isSignatureWaived());
    }

    public function testFinishingWithASignatureNeedsNoWaiver(): void
    {
        $wo = $this->order('en_curso')->sign('png', 'María');
        $wo = $this->service()->transition($wo, 'terminado');
        self::assertSame('terminado', $wo->getStatus());
        self::assertFalse($wo->isSignatureWaived());
    }

    public function testReopeningAFinishedOrderDropsTheSignature(): void
    {
        $wo = $this->order('en_curso')->sign('png', 'María');
        $service = $this->service();
        $service->transition($wo, 'terminado');
        $service->transition($wo, 'en_curso');

        self::assertSame('en_curso', $wo->getStatus());
        self::assertFalse($wo->hasSignature());
    }

    public function testAFinishedOrderCannotBeEdited(): void
    {
        $this->expectCode(\DomainException::class, 'not_editable');
        $this->service()->update($this->order('terminado'), ['title' => 'otro']);
    }

    public function testUpdateNeverChangesTheStatus(): void
    {
        $wo = $this->service()->update($this->order('en_curso'), ['status' => 'facturado', 'title' => 'Nuevo']);
        self::assertSame('en_curso', $wo->getStatus());
        self::assertSame('Nuevo', $wo->getTitle());
    }

    public function testOnlyAFinishedOrderIsConverted(): void
    {
        $invoices = $this->createMock(InvoiceService::class);
        $invoices->expects(self::never())->method('create');

        $this->expectCode(\DomainException::class, 'not_finished');
        $this->service($invoices)->convert(new User(), $this->order('en_curso')->setLaborHours('1.00')->setLaborRate('30.00'));
    }

    public function testLabourLineIsSentToTheInvoiceAsExactCents(): void
    {
        $invoices = $this->createMock(InvoiceService::class);
        $invoices->expects(self::once())->method('create')
            ->with(self::anything(), self::callback(static function (array $data): bool {
                // 1.15 h × 33.33 €/h = 38.33 €, as a string built from integer cents
                return $data['lines'][0]['unitPrice'] === '38.33'
                    && $data['lines'][0]['description'] === 'Mano de obra (1.15 h)';
            }))
            ->willReturn(new Invoice());

        $wo = $this->order('terminado')->setLaborHours('1.15')->setLaborRate('33.33');
        $this->service($invoices)->convert(new User(), $wo);
        self::assertSame('facturado', $wo->getStatus());
    }

    public function testPhotoLimitPerOrder(): void
    {
        $service = $this->service();
        $wo = $this->order('en_curso');
        for ($i = 0; $i < WorkOrder::MAX_PHOTOS; $i++) {
            $service->addPhoto($wo, ImageSanitizerTest::jpeg());
        }
        self::assertCount(WorkOrder::MAX_PHOTOS, $wo->getPhotos());

        $this->expectCode(\DomainException::class, 'photo_limit');
        $service->addPhoto($wo, ImageSanitizerTest::jpeg());
    }

    public function testSigningNeedsTheNameOfWhoSigns(): void
    {
        $this->expectCode(\InvalidArgumentException::class, 'signer_required');
        $this->service()->sign($this->order('en_curso'), base64_encode(ImageSanitizerTest::png()), '   ');
    }
}
