<?php

namespace App\Tests\Service;

use App\Service\InstallationCalculator;
use PHPUnit\Framework\TestCase;

class InstallationCalculatorTest extends TestCase
{
    private InstallationCalculator $calc;

    protected function setUp(): void
    {
        $this->calc = new InstallationCalculator();
    }

    /** @param array<int,array<string,mixed>> $circuits */
    private function codes(array $circuits): array
    {
        return array_column($circuits, 'code');
    }

    public function testBasicDwellingHasTheFiveMandatoryCircuits(): void
    {
        $r = $this->calc->compute([
            'rooms' => [
                ['type' => 'salon', 'area' => 20],
                ['type' => 'dormitorio', 'area' => 12],
                ['type' => 'dormitorio', 'area' => 12],
                ['type' => 'bano', 'area' => 4],
                ['type' => 'cocina', 'area' => 9],
                ['type' => 'pasillo', 'area' => 6],
            ],
        ]);

        self::assertSame('basico', $r['grade']);
        self::assertSame(5750, $r['contractedPower']);
        self::assertSame(['C1', 'C2', 'C3', 'C4', 'C5'], $this->codes($r['circuits']));
        self::assertSame(1, $r['totals']['differentials']);
        // points: 3 big rooms → 2 lights each; cocina/bano/pasillo → 1 each = 9 lights
        self::assertSame(9, $r['totals']['lights']);
        self::assertSame(13, $r['totals']['socketsGeneral']);
        self::assertSame(4, $r['totals']['socketsC5']);
    }

    public function testCircuitSpecsMatchTheRegulation(): void
    {
        $r = $this->calc->compute(['rooms' => [['type' => 'salon', 'area' => 15]]]);
        $byCode = [];
        foreach ($r['circuits'] as $c) {
            $byCode[$c['code']] = $c;
        }
        self::assertSame('1.5', $byCode['C1']['section']);
        self::assertSame(10, $byCode['C1']['breaker']);
        self::assertSame('2.5', $byCode['C2']['section']);
        self::assertSame(16, $byCode['C2']['breaker']);
        self::assertSame('2.5', $byCode['C5']['section']);
    }

    public function testElevatedGradeFromLoadsAddsSpecialCircuits(): void
    {
        $r = $this->calc->compute([
            'grade' => 'auto',
            'loads' => ['cocina' => true, 'lavadora' => true, 'aire' => true, 'calefaccion' => true, 'vehiculo' => true],
            'rooms' => [
                ['type' => 'salon', 'area' => 25],
                ['type' => 'dormitorio', 'area' => 14],
                ['type' => 'cocina', 'area' => 12],
                ['type' => 'bano', 'area' => 5],
            ],
        ]);

        self::assertSame('elevado', $r['grade']);
        self::assertSame(9200, $r['contractedPower']);
        self::assertGreaterThanOrEqual(2, $r['totals']['differentials']);
        $codes = $this->codes($r['circuits']);
        self::assertContains('C8', $codes);  // calefacción
        self::assertContains('C9', $codes);  // aire
        self::assertContains('C12', $codes); // vehículo
    }

    public function testLightingAndSocketsSplitIntoAdditionalCircuits(): void
    {
        // 16 big bedrooms → 32 lights (>30 → C1+C6) and 64 general sockets (>20 → C2+C7…)
        $rooms = array_fill(0, 16, ['type' => 'dormitorio', 'area' => 14]);
        $r = $this->calc->compute(['rooms' => $rooms]);

        $codes = $this->codes($r['circuits']);
        self::assertContains('C6', $codes, 'lighting over 30 points must spill into C6');
        self::assertContains('C7', $codes, 'general sockets over 20 points must spill into C7');
        self::assertSame('elevado', $r['grade']); // >5 circuits ⇒ elevado
    }

    public function testEmptyInputStillReturnsMandatoryCircuits(): void
    {
        $r = $this->calc->compute(['rooms' => []]);
        self::assertSame(['C1', 'C2', 'C5'], $this->codes($r['circuits']));
        self::assertSame('basico', $r['grade']);
    }

    public function testMaterialsAndCableAreProduced(): void
    {
        $r = $this->calc->compute(['rooms' => [
            ['type' => 'salon', 'area' => 20],
            ['type' => 'cocina', 'area' => 10],
            ['type' => 'bano', 'area' => 4],
        ]]);

        self::assertNotEmpty($r['materials']);
        self::assertGreaterThan(0, $r['cable']['totalM']);
        // a magnetotérmico line and the IGA must be present
        $items = array_column($r['materials'], 'item');
        self::assertTrue((bool) array_filter($items, static fn ($i) => str_contains($i, 'IGA')));
    }
}
