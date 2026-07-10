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
        // 16 big bedrooms → 32 lights (>30 → C1+C6) and 48 general sockets (>20 → C2+C7…)
        $rooms = array_fill(0, 16, ['type' => 'dormitorio', 'area' => 14]);
        $r = $this->calc->compute(['rooms' => $rooms]);

        $codes = $this->codes($r['circuits']);
        self::assertContains('C6', $codes, 'lighting over 30 points must spill into C6');
        self::assertContains('C7', $codes, 'general sockets over 20 points must spill into C7');
        self::assertSame('elevado', $r['grade'], '>30 lights and >20 C2 sockets are two of the eight triggers');
    }

    /**
     * ITC-BT-25, 2.3.1: splitting a circuit "no supondrá el paso a electrificación elevada"; what it
     * requires is an extra differential. The old code promoted the dwelling to elevado instead.
     */
    public function testManyCircuitsAddADifferentialButNotAnElevatedGrade(): void
    {
        $r = $this->calc->compute([
            'grade' => 'auto',
            'loads' => ['cocina' => true, 'lavadora' => true, 'vehiculo' => true],
            'rooms' => [
                ['type' => 'salon', 'area' => 20],
                ['type' => 'cocina', 'area' => 9],
                ['type' => 'bano', 'area' => 4],
            ],
        ]);

        self::assertGreaterThan(5, count($r['circuits']));
        self::assertSame('basico', $r['grade']);
        self::assertSame(5750, $r['contractedPower']);
        self::assertSame(2, $r['totals']['differentials']);
    }

    /** Tabla 2, verbatim: a kitchen always needs C2×2, C3×1, C4×3 and C5×3, whatever its surface. */
    public function testKitchenRequirementsFollowTable2(): void
    {
        $r = $this->calc->roomRequirements('cocina', 9, 0, []);

        self::assertSame(1, $r['lights']);   // hasta 10 m²
        self::assertSame(1, $r['switches']); // uno por cada punto de luz
        self::assertSame(2, $r['c2']);       // extractor y frigorífico
        self::assertSame(1, $r['c3']);       // cocina/horno
        self::assertSame(3, $r['c4']);       // lavadora, lavavajillas y termo
        self::assertSame(3, $r['c5']);       // encima del plano de trabajo

        self::assertSame(2, $this->calc->roomRequirements('cocina', 12, 0, [])['lights']); // dos si S > 10 m²
    }

    /** "3 bases, una por cada 6 m² redondeado al entero superior" — the 3 is a floor, not a fixed number. */
    public function testLivingRoomAndBedroomSocketsAreThreeOrOnePerSixSquareMetres(): void
    {
        self::assertSame(3, $this->calc->roomRequirements('dormitorio', 8, 0, [])['c2']);   // ceil(8/6)=2 → floor of 3
        self::assertSame(3, $this->calc->roomRequirements('salon', 18, 0, [])['c2']);       // ceil(18/6)=3
        self::assertSame(4, $this->calc->roomRequirements('salon', 20, 0, [])['c2']);       // ceil(20/6)=4
        self::assertSame(5, $this->calc->roomRequirements('salon', 25, 0, [])['c2']);       // ceil(25/6)=5
    }

    /** Corridors are ruled by length, not surface: a light every 5 m, and a second socket beyond 5 m. */
    public function testCorridorsAreRuledByLength(): void
    {
        $short = $this->calc->roomRequirements('pasillo', 5, 4, []);
        self::assertSame(1, $short['lights']);
        self::assertSame(1, $short['c2']);

        $long = $this->calc->roomRequirements('pasillo', 9, 7.5, []);
        self::assertSame(2, $long['lights']);  // ceil(7.5/5)
        self::assertSame(2, $long['c2']);      // dos si L > 5 m
    }

    /** A terrace gets light and switch — and no socket. The old table handed it one. */
    public function testTerraceNeedsNoSocket(): void
    {
        $r = $this->calc->roomRequirements('terraza', 8, 0, []);
        self::assertSame(1, $r['lights']);
        self::assertSame(0, $r['c2']);
        self::assertSame(0, $r['c5']);
    }

    /** Heating and A/C tomas appear only when those loads are foreseen, and the salón scales by surface. */
    public function testHeatingAndAcTomasDependOnTheForeseenLoads(): void
    {
        self::assertSame(0, $this->calc->roomRequirements('salon', 20, 0, [])['c8']);
        self::assertSame(2, $this->calc->roomRequirements('salon', 20, 0, ['calefaccion' => true])['c8']);
        self::assertSame(1, $this->calc->roomRequirements('dormitorio', 20, 0, ['aire' => true])['c9']);
    }

    public function testEmptyInputStillReturnsMandatoryCircuits(): void
    {
        $r = $this->calc->compute(['rooms' => []]);
        self::assertSame(['C1', 'C2', 'C5'], $this->codes($r['circuits']));
        self::assertSame('basico', $r['grade']);
    }

    public function testLayoutCableMeasuresFromDevicePositions(): void
    {
        $cable = $this->calc->layoutCable([
            'panel' => ['x' => 0, 'y' => 0],
            'devices' => [
                ['type' => 'socket', 'x' => 3, 'y' => 0],
                ['type' => 'light', 'x' => 0, 'y' => 4],
                ['type' => 'panel', 'x' => 0, 'y' => 0], // ignored
            ],
        ]);

        self::assertNotNull($cable);
        self::assertSame(2, $cable['devices']);
        // (3+0+0.3)*1.1 + (0+4+0.3)*1.1 = 3.63 + 4.73 = 8.36 → 8.4
        self::assertEqualsWithDelta(8.4, $cable['totalM'], 0.05);
    }

    public function testLayoutCableIsNullWithoutDevices(): void
    {
        self::assertNull($this->calc->layoutCable(['panel' => ['x' => 0, 'y' => 0], 'devices' => []]));
        self::assertNull($this->calc->layoutCable([]));
    }

    /** A 4×3 m rectangle at the origin, as the editor stores it. */
    private function rect(string $type, float $x, float $y, float $w, float $h): array
    {
        return ['type' => $type, 'points' => [
            ['x' => $x, 'y' => $y], ['x' => $x + $w, 'y' => $y],
            ['x' => $x + $w, 'y' => $y + $h], ['x' => $x, 'y' => $y + $h],
        ]];
    }

    public function testKitchenWithTooFewSocketsIsNotCompliant(): void
    {
        // 12 m² kitchen (needs 2 lights, 2 switches, C2×2, C3×1, C4×3, C5×3) with just three plain sockets
        $v = $this->calc->validateLayout([
            'rooms' => [$this->rect('cocina', 0, 0, 4, 3)],
            'devices' => [
                ['type' => 'light', 'x' => 1, 'y' => 1],
                ['type' => 'switch', 'x' => 0.2, 'y' => 0.2],
                ['type' => 'socket', 'x' => 1, 'y' => 2],
                ['type' => 'socket', 'x' => 2, 'y' => 2],
                ['type' => 'socket', 'x' => 3, 'y' => 2],
            ],
        ]);

        self::assertTrue($v['checked']);
        self::assertFalse($v['compliant']);

        $room = $v['rooms'][0];
        self::assertSame(12.0, $room['area']);
        $byCircuit = array_column($room['missing'], 'short', 'circuit');
        self::assertSame(1, $byCircuit['C1'] ?? 0, 'a 12 m² kitchen needs two light points');
        self::assertArrayHasKey('C3', $byCircuit, 'the 25 A cocina/horno base is missing');
        self::assertArrayHasKey('C4', $byCircuit);
        self::assertGreaterThan(0, $v['missingTotal']);
    }

    /** Sockets that declare no circuit are credited against whatever the room still lacks. */
    public function testUndeclaredSocketsAreGivenTheBenefitOfTheDoubt(): void
    {
        $bathroom = ['rooms' => [$this->rect('bano', 0, 0, 2, 2)], 'devices' => [
            ['type' => 'light', 'x' => 1, 'y' => 1],
            ['type' => 'switch', 'x' => 0.2, 'y' => 0.2],
            ['type' => 'socket', 'x' => 1.5, 'y' => 1.5],   // no circuit declared
        ]];

        $v = $this->calc->validateLayout($bathroom);
        self::assertTrue($v['compliant'], 'the lone socket must be credited to the C5 the bathroom needs');
    }

    /** A socket explicitly on the wrong circuit does not satisfy the requirement. */
    public function testASocketOnTheWrongCircuitDoesNotCount(): void
    {
        $v = $this->calc->validateLayout(['rooms' => [$this->rect('bano', 0, 0, 2, 2)], 'devices' => [
            ['type' => 'light', 'x' => 1, 'y' => 1],
            ['type' => 'switch', 'x' => 0.2, 'y' => 0.2],
            ['type' => 'socket', 'x' => 1.5, 'y' => 1.5, 'circuit' => 'C2'],
        ]]);

        self::assertFalse($v['compliant']);
        self::assertSame('C5', $v['rooms'][0]['missing'][0]['circuit']);
    }

    /** Devices only count for the room that actually contains them. */
    public function testDevicesAreAttributedToTheRoomWhosePolygonContainsThem(): void
    {
        $v = $this->calc->validateLayout([
            'rooms' => [$this->rect('bano', 0, 0, 2, 2), $this->rect('terraza', 5, 0, 3, 3)],
            'devices' => [
                ['type' => 'light', 'x' => 6, 'y' => 1],    // in the terrace
                ['type' => 'switch', 'x' => 6, 'y' => 2],   // in the terrace
                ['type' => 'socket', 'x' => 6, 'y' => 2.5], // in the terrace: does not help the bathroom
            ],
        ]);

        self::assertFalse($v['compliant']);
        $bathroom = $v['rooms'][0];
        self::assertSame('bano', $bathroom['type']);
        self::assertCount(3, $bathroom['missing']); // light, switch, C5
        self::assertSame([], $v['rooms'][1]['missing'], 'the terrace is satisfied with a light and a switch');
    }

    public function testAFullyEquippedBathroomIsCompliant(): void
    {
        $v = $this->calc->validateLayout(['rooms' => [$this->rect('bano', 0, 0, 2, 2)], 'devices' => [
            ['type' => 'light', 'x' => 1, 'y' => 1],
            ['type' => 'switch', 'x' => 0.2, 'y' => 0.2],
            ['type' => 'socket', 'x' => 1.5, 'y' => 1.5, 'circuit' => 'C5'],
        ]]);

        self::assertTrue($v['compliant']);
        self::assertSame(0, $v['missingTotal']);
    }

    /** With nothing drawn there is nothing to judge: the plan is not "compliant", it is unchecked. */
    public function testAnEmptyPlanIsReportedAsUnchecked(): void
    {
        $v = $this->calc->validateLayout(['rooms' => [], 'devices' => []]);
        self::assertFalse($v['checked']);
        self::assertSame(0, $v['missingTotal']);
    }

    /** An L-shaped room: the shoelace area, not the bounding box, decides how many sockets it needs. */
    public function testLShapedRoomUsesItsRealSurface(): void
    {
        // 6×6 square minus a 3×3 corner = 27 m² → ceil(27/6) = 5 sockets
        $l = ['type' => 'salon', 'points' => [
            ['x' => 0, 'y' => 0], ['x' => 6, 'y' => 0], ['x' => 6, 'y' => 3],
            ['x' => 3, 'y' => 3], ['x' => 3, 'y' => 6], ['x' => 0, 'y' => 6],
        ]];
        $v = $this->calc->validateLayout(['rooms' => [$l], 'devices' => []]);

        self::assertSame(27.0, $v['rooms'][0]['area']);
        self::assertSame(5, $v['rooms'][0]['required']['c2']);
    }

    public function testPanelIsDerivedFromTheDevicesActuallyDrawn(): void
    {
        // 7 C5 sockets: one more than the 6 that fit in a C5 circuit, so it must split into two
        $devices = [['type' => 'light', 'x' => 1, 'y' => 1], ['type' => 'switch', 'x' => 0.2, 'y' => 0.2]];
        for ($i = 0; $i < 7; ++$i) {
            $devices[] = ['type' => 'socket', 'x' => 0.2 + $i * 0.2, 'y' => 1.5, 'circuit' => 'C5'];
        }

        $board = $this->calc->panelSchedule(['rooms' => [$this->rect('cocina', 0, 0, 4, 3)], 'devices' => $devices], [], 5750);

        self::assertNotNull($board);
        self::assertSame(25, $board['iga']['current']);          // 5 750 W → IGA de 25 A
        self::assertSame(7, $board['connected']['c5']);
        $c5 = array_values(array_filter($board['circuits'], static fn ($c) => $c['code'] === 'C5'));
        self::assertCount(2, $c5, 'seven C5 points do not fit in one circuit of six');
        self::assertSame(6, $c5[0]['points']);
        self::assertSame(1, $c5[1]['points']);
    }

    public function testPanelHangsCircuitsFromOneDifferentialPerFive(): void
    {
        $devices = [['type' => 'light', 'x' => 1, 'y' => 1]];
        for ($i = 0; $i < 3; ++$i) {
            $devices[] = ['type' => 'socket', 'x' => 1 + $i * 0.2, 'y' => 2, 'circuit' => 'C2'];
        }
        $board = $this->calc->panelSchedule(
            ['rooms' => [$this->rect('salon', 0, 0, 4, 3)], 'devices' => $devices],
            ['calefaccion' => true, 'aire' => true, 'domotica' => true, 'vehiculo' => true],
            9200,
        );

        self::assertSame(40, $board['iga']['current']);          // 9 200 W → IGA de 40 A
        self::assertGreaterThan(5, count($board['circuits']));
        self::assertCount(2, $board['differentials']);
        self::assertSame(1, $board['circuits'][0]['differential']);
        self::assertSame(2, $board['circuits'][5]['differential'], 'the sixth circuit moves to the second differential');
    }

    public function testPanelCountsDinModulesSoTheEnclosureCanBeOrdered(): void
    {
        $board = $this->calc->panelSchedule(['rooms' => [$this->rect('bano', 0, 0, 2, 2)], 'devices' => [
            ['type' => 'light', 'x' => 1, 'y' => 1],
            ['type' => 'socket', 'x' => 1.5, 'y' => 1.5, 'circuit' => 'C5'],
        ]], [], 5750);

        // IGA + 1 differential + 3 circuits (C1, C2, C5) = 5 devices × 2 modules
        self::assertSame(3, count($board['circuits']));
        self::assertSame(10, $board['modules']['total']);
        self::assertSame(1, $board['modules']['rows']);
        self::assertSame(12, $board['modules']['capacity']);
    }

    /** A socket drawn outside every room still has to hang from a circuit — it lands on C2. */
    public function testSocketsOutsideEveryRoomStillReachThePanel(): void
    {
        $board = $this->calc->panelSchedule(['rooms' => [$this->rect('bano', 0, 0, 2, 2)], 'devices' => [
            ['type' => 'light', 'x' => 1, 'y' => 1],
            ['type' => 'socket', 'x' => 9, 'y' => 9],   // nowhere near a room
        ]], [], 5750);

        self::assertSame(1, $board['orphanSockets']);
        self::assertSame(1, $board['connected']['c2']);
    }

    public function testPanelIsNullWithNothingDrawn(): void
    {
        self::assertNull($this->calc->panelSchedule(['rooms' => [], 'devices' => []]));
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
