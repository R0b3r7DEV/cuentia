<?php

namespace App\Service;

/**
 * Computes a dwelling's interior electrical installation from its rooms and expected loads, following
 * Spain's REBT ITC-BT-25 (número de circuitos, puntos mínimos por estancia, secciones y protecciones).
 *
 * EN: This is a pre-dimensioning aid: circuits, minimum points per room, protections and a bill of
 * materials are computed per the regulation; cable metres are a rough estimate (refined later with a
 * real layout). It is NOT a substitute for a project/memoria técnica signed by a competent technician.
 * ES: Es una ayuda de predimensionado según ITC-BT-25; los metros de cable son una estimación. No
 * sustituye a un proyecto/memoria técnica firmada por técnico competente.
 */
class InstallationCalculator
{
    /** Circuit specs: [name, section mm², breaker A, max points]. / Especificaciones por circuito. */
    private const CIRCUITS = [
        'C1'  => ['Iluminación', '1.5', 10, 30],
        'C2'  => ['Tomas de uso general', '2.5', 16, 20],
        'C3'  => ['Cocina y horno', '6', 25, 2],
        'C4'  => ['Lavadora, lavavajillas y termo', '4', 20, 3],
        'C5'  => ['Tomas de baños y auxiliares de cocina', '2.5', 16, 6],
        'C6'  => ['Iluminación adicional', '1.5', 10, 30],
        'C7'  => ['Tomas de uso general adicionales', '2.5', 16, 20],
        'C8'  => ['Calefacción eléctrica', '6', 25, null],
        'C9'  => ['Aire acondicionado', '6', 25, null],
        'C10' => ['Secadora', '2.5', 16, 1],
        'C11' => ['Automatización y gestión (domótica)', '1.5', 10, null],
        'C12' => ['Recarga de vehículo eléctrico', '6', 25, 1],
    ];

    /**
     * @param array{grade?:string, supplyType?:string, loads?:array<string,bool>, rooms?:array<array{type?:string,area?:mixed}>} $input
     * @return array<string,mixed>
     */
    public function compute(array $input): array
    {
        $supplyType = ($input['supplyType'] ?? 'monofasico') === 'trifasico' ? 'trifasico' : 'monofasico';
        $voltage = $supplyType === 'trifasico' ? 400 : 230;
        $loads = is_array($input['loads'] ?? null) ? $input['loads'] : [];
        $roomsIn = is_array($input['rooms'] ?? null) ? $input['rooms'] : [];

        // 1) Points per room (ITC-BT-25 tabla 2) and totals.
        $rooms = [];
        $totalArea = 0.0;
        $lights = 0;
        $socketsGeneral = 0;
        $socketsC5 = 0;
        $switches = 0;
        $socketsC3 = 0;
        $socketsC4 = 0;
        foreach ($roomsIn as $r) {
            $type = (string) ($r['type'] ?? 'otros');
            $area = max(0.0, (float) ($r['area'] ?? 0));
            $length = max(0.0, (float) ($r['length'] ?? $this->assumedLength($type, $area)));
            $p = $this->roomRequirements($type, $area, $length, $loads);
            $rooms[] = [
                'type' => $type, 'area' => $area,
                'lights' => $p['lights'], 'switches' => $p['switches'],
                'socketsGeneral' => $p['c2'], 'socketsC5' => $p['c5'],
                'socketsC3' => $p['c3'], 'socketsC4' => $p['c4'], 'socketsC10' => $p['c10'],
            ];
            $totalArea += $area;
            $lights += $p['lights'];
            $socketsGeneral += $p['c2'];
            $socketsC5 += $p['c5'];
            $switches += $p['switches'];
            $socketsC3 += $p['c3'];
            $socketsC4 += $p['c4'];
        }

        $load = static fn (string $k, bool $default = false): bool => (bool) ($loads[$k] ?? $default);

        // 2) Circuits (mandatory básico C1/C2/C5, appliance C3/C4, elevado extras).
        $circuits = [];
        $this->addSplit($circuits, 'C1', 'C6', max($lights, 1));
        $this->addSplit($circuits, 'C2', 'C7', max($socketsGeneral, 1));
        if ($socketsC3 > 0 || $load('cocina')) {
            $circuits[] = $this->circuit('C3', 2);
        }
        if ($socketsC4 > 0 || $load('lavadora')) {
            $circuits[] = $this->circuit('C4', 3);
        }
        $this->addSplit($circuits, 'C5', 'C5', max($socketsC5, 1));
        foreach (['calefaccion' => 'C8', 'aire' => 'C9', 'secadora' => 'C10', 'domotica' => 'C11', 'vehiculo' => 'C12'] as $flag => $code) {
            if ($load($flag)) {
                $circuits[] = $this->circuit($code, self::CIRCUITS[$code][3] ?? 1);
            }
        }

        // 3) Grade & contracted power. These are the eight triggers the regulation lists — and splitting a
        // circuit is NOT one of them: "no supondrá el paso a electrificación elevada".
        // ES: Estos son los ocho supuestos que lista el reglamento. Desdoblar un circuito NO es uno de ellos.
        $autoElevado = $totalArea > 160
            || $load('calefaccion') || $load('aire') || $load('secadora') || $load('domotica')
            || $lights > 30 || $socketsGeneral > 20 || $socketsC5 > 6;
        $gradeInput = in_array($input['grade'] ?? 'auto', ['basico', 'elevado'], true) ? $input['grade'] : 'auto';
        $elevado = $gradeInput === 'elevado' || ($gradeInput === 'auto' && $autoElevado);
        $grade = $elevado ? 'elevado' : 'basico';
        $contractedPower = $elevado ? 9200 : 5750;

        // 4) "Se debe instalar un interruptor diferencial adicional si el número total de circuitos
        //    es superior a 5" — one differential per five circuits.
        $differentials = max(1, (int) ceil(count($circuits) / 5));
        foreach ($circuits as $i => &$c) {
            $c['differential'] = intdiv($i, 5) + 1;
        }
        unset($c);

        // 5) Cable estimate + materials.
        $cable = $this->cableEstimate($circuits, $totalArea);
        $materials = $this->materials($circuits, $lights, $socketsGeneral, $socketsC5, $differentials, $elevado);

        $notes = [];
        if (count($circuits) > 5) {
            $notes[] = 'Más de 5 circuitos: se instala un interruptor diferencial adicional. Desdoblar circuitos no supone el paso a electrificación elevada (ITC-BT-25, 2.3.1).';
        }
        if ($autoElevado && $gradeInput === 'auto') {
            $notes[] = 'Grado elevado por superficie > 160 m², cargas previstas (calefacción, A/A, secadora, domótica) o por superar 30 puntos de luz / 20 tomas C2 / 6 tomas C5.';
        }
        if ($load('vehiculo')) {
            $notes[] = 'La recarga de vehículo eléctrico no la regula la ITC-BT-25, sino la ITC-BT-52. El circuito C12 se muestra como previsión de carga.';
        }

        return [
            'grade' => $grade,
            'supplyType' => $supplyType,
            'voltage' => $voltage,
            'contractedPower' => $contractedPower,
            'totalArea' => round($totalArea, 2),
            'totals' => [
                'lights' => $lights,
                'socketsGeneral' => $socketsGeneral,
                'socketsC5' => $socketsC5,
                'switches' => $switches,
                'circuits' => count($circuits),
                'differentials' => $differentials,
            ],
            'rooms' => $rooms,
            'circuits' => $circuits,
            'materials' => $materials,
            'cable' => $cable,
            'notes' => $notes,
        ];
    }

    /**
     * Exact-er cable estimate from an actual 2D layout: Manhattan run from each device to the panel plus a
     * drop, with 10 % slack. Positions are in metres. Returns null when there is no layout to measure.
     * ES: Estimación de cable más exacta desde una planta 2D real: recorrido Manhattan de cada dispositivo
     * al cuadro más una bajada, con 10 % de holgura. Posiciones en metros. null si no hay planta.
     *
     * @param array<string,mixed> $layout
     * @return array{totalM:float, byType:array<string,float>, devices:int}|null
     */
    public function layoutCable(array $layout): ?array
    {
        $devices = is_array($layout['devices'] ?? null) ? $layout['devices'] : [];
        $panel = is_array($layout['panel'] ?? null) ? $layout['panel'] : null;
        $devices = array_values(array_filter($devices, static fn ($d) => is_array($d) && ($d['type'] ?? '') !== 'panel'));
        if ($panel === null || count($devices) === 0) {
            return null;
        }

        $px = (float) ($panel['x'] ?? 0);
        $py = (float) ($panel['y'] ?? 0);
        $total = 0.0;
        $byType = [];
        foreach ($devices as $d) {
            $m = (abs((float) ($d['x'] ?? 0) - $px) + abs((float) ($d['y'] ?? 0) - $py) + 0.3) * 1.1;
            $total += $m;
            $type = (string) ($d['type'] ?? 'other');
            $byType[$type] = round(($byType[$type] ?? 0) + $m, 1);
        }

        return ['totalM' => round($total, 1), 'byType' => $byType, 'devices' => count($devices)];
    }

    /** Default corridor width, in metres, used only when the plan gives no polygon to measure. */
    private const ASSUMED_CORRIDOR_WIDTH = 1.2;

    /** What each shortfall is called, for a message a human can act on. */
    private const POINT_LABELS = [
        'lights'   => ['C1', 'punto de luz'],
        'switches' => ['C1', 'interruptor 10 A'],
        'c2'       => ['C2', 'base 16 A 2p+T (uso general)'],
        'c3'       => ['C3', 'base 25 A 2p+T (cocina/horno)'],
        'c4'       => ['C4', 'base 16 A 2p+T (lavadora, lavavajillas, termo)'],
        'c5'       => ['C5', 'base 16 A 2p+T (baño y auxiliares de cocina)'],
        'c10'      => ['C10', 'base 16 A 2p+T (secadora)'],
    ];

    /** The order in which a socket of unknown circuit is credited against what the room still lacks. */
    private const SOCKET_KEYS = ['c5', 'c3', 'c4', 'c10', 'c2'];

    /**
     * Check a drawn floor plan against the minimum points of ITC-BT-25 tabla 2, room by room.
     *
     * A device counts for the room whose polygon contains it. A socket declares which circuit it belongs to
     * (`circuit: C2|C3|C4|C5|C10`); one that doesn't is credited against whatever the room still lacks —
     * the benefit of the doubt, so an older plan is never accused of a shortfall it may not have.
     *
     * This checks the **number of points of use**, which is what can be read off a plan. It says nothing
     * about sections, earthing, insulation tests or anything else a certificate also depends on.
     *
     * ES: Contrasta la planta dibujada con los puntos mínimos de la tabla 2, estancia por estancia. Un
     * dispositivo cuenta para el polígono que lo contiene. Una toma declara su circuito; si no lo declara,
     * se le da el beneficio de la duda y se imputa a lo que aún falte. Comprueba el NÚMERO de puntos de
     * utilización, que es lo que un plano puede decir: nada sobre secciones, tierras ni mediciones.
     *
     * @param array<string,mixed> $layout
     * @param array<string,bool>  $loads
     * @return array{compliant:bool, checked:bool, rooms:array<int,array<string,mixed>>, missingTotal:int}
     */
    public function validateLayout(array $layout, array $loads = []): array
    {
        $tally = $this->tally($layout, $loads);
        if ($tally['rooms'] === []) {
            return ['compliant' => true, 'checked' => false, 'rooms' => [], 'missingTotal' => 0];
        }

        $rooms = [];
        $missingTotal = 0;
        foreach ($tally['rooms'] as $room) {
            $missing = [];
            foreach (self::POINT_LABELS as $key => [$circuit, $label]) {
                $short = $room['required'][$key] - $room['placed'][$key];
                if ($short > 0) {
                    $missing[] = ['circuit' => $circuit, 'item' => $label, 'need' => $room['required'][$key], 'have' => $room['placed'][$key], 'short' => $short];
                    $missingTotal += $short;
                }
            }
            $rooms[] = $room + ['missing' => $missing];
        }

        return ['compliant' => $missingTotal === 0, 'checked' => true, 'rooms' => $rooms, 'missingTotal' => $missingTotal];
    }

    /**
     * Attribute every drawn device to the room whose polygon contains it, and settle which circuit each
     * socket belongs to. Both the compliance check and the panel schedule read from here, so they can never
     * disagree about what is installed.
     *
     * ES: Imputa cada dispositivo dibujado a la estancia que lo contiene y decide de qué circuito cuelga
     * cada toma. La comprobación y el cuadro leen de aquí, así que no pueden discrepar sobre lo instalado.
     *
     * @return array{rooms:array<int,array<string,mixed>>, totals:array<string,int>, orphanSockets:int}
     */
    private function tally(array $layout, array $loads): array
    {
        $roomsIn = is_array($layout['rooms'] ?? null) ? $layout['rooms'] : [];
        $devicesIn = array_values(array_filter(
            is_array($layout['devices'] ?? null) ? $layout['devices'] : [],
            static fn ($d) => is_array($d)
        ));

        $polygons = [];
        foreach ($roomsIn as $r) {
            if (!is_array($r)) {
                continue;
            }
            $points = $this->roomPolygon($r);
            if (count($points) >= 3) {
                $polygons[] = ['type' => (string) ($r['type'] ?? 'otros'), 'points' => $points];
            }
        }

        $blank = ['lights' => 0, 'switches' => 0, 'c2' => 0, 'c3' => 0, 'c4' => 0, 'c5' => 0, 'c10' => 0];
        $totals = $blank;
        $rooms = [];
        $claimed = [];   // device indices already attributed to a room

        foreach ($polygons as $poly) {
            $area = $this->polygonArea($poly['points']);
            $length = $this->polygonLength($poly['points']);
            $required = $this->roomRequirements($poly['type'], $area, $length, $loads);

            $placed = $blank;
            $undeclared = [];
            foreach ($devicesIn as $i => $d) {
                if (!$this->pointInPolygon((float) ($d['x'] ?? 0), (float) ($d['y'] ?? 0), $poly['points'])) {
                    continue;
                }
                $claimed[$i] = true;
                $kind = (string) ($d['type'] ?? '');
                if ($kind === 'light') {
                    ++$placed['lights'];
                } elseif ($kind === 'switch') {
                    ++$placed['switches'];
                } elseif ($kind === 'socket') {
                    $circuit = strtolower((string) ($d['circuit'] ?? ''));
                    if (in_array($circuit, self::SOCKET_KEYS, true)) {
                        ++$placed[$circuit];
                    } else {
                        $undeclared[] = $i;
                    }
                }
            }

            // Credit the undeclared sockets to whatever is still missing, most specific circuit first.
            $spare = count($undeclared);
            foreach (self::SOCKET_KEYS as $key) {
                if ($spare <= 0) {
                    break;
                }
                $take = min($spare, max(0, $required[$key] - $placed[$key]));
                $placed[$key] += $take;
                $spare -= $take;
            }
            $placed['c2'] += $spare;   // anything still unexplained is a general-purpose socket

            foreach ($blank as $k => $_) {
                $totals[$k] += $placed[$k];
            }
            $rooms[] = ['type' => $poly['type'], 'area' => round($area, 2), 'length' => round($length, 2), 'required' => $required, 'placed' => $placed];
        }

        // Devices drawn outside every room still have to hang from a circuit.
        $orphanSockets = 0;
        foreach ($devicesIn as $i => $d) {
            if (isset($claimed[$i])) {
                continue;
            }
            $kind = (string) ($d['type'] ?? '');
            if ($kind === 'light') {
                ++$totals['lights'];
            } elseif ($kind === 'switch') {
                ++$totals['switches'];
            } elseif ($kind === 'socket') {
                $circuit = strtolower((string) ($d['circuit'] ?? ''));
                $totals[in_array($circuit, self::SOCKET_KEYS, true) ? $circuit : 'c2'] += 1;
                ++$orphanSockets;
            }
        }

        return ['rooms' => $rooms, 'totals' => $totals, 'orphanSockets' => $orphanSockets];
    }

    /** Rated current of the IGA for each contracted power, per ITC-BT-25 §2.1. */
    private const IGA_BY_POWER = [5750 => 25, 7360 => 32, 9200 => 40, 11500 => 50, 14490 => 63];

    /** Modules a DIN device occupies. 2P and 1P+N devices take two; a row of a domestic enclosure holds 12. */
    private const MODULES_PER_DEVICE = 2;
    private const MODULES_PER_ROW = 12;

    /**
     * The main panel (cuadro general de mando y protección) as it must actually be built, derived from the
     * devices **drawn on the plan** rather than from the theoretical minimums: the circuits are sized by the
     * points really connected, split when they exceed the maximum of tabla 1, hung from one differential per
     * five circuits, and counted into DIN modules so the enclosure can be ordered.
     *
     * Returns null when nothing is drawn — there is no panel to derive from an empty plan.
     *
     * ES: El cuadro general de mando y protección tal como hay que montarlo, derivado de los dispositivos
     * **dibujados en el plano** y no de los mínimos teóricos: circuitos dimensionados por los puntos
     * realmente conectados, desdoblados al superar el máximo de la tabla 1, colgados de un diferencial por
     * cada cinco circuitos, y contados en módulos DIN para poder pedir el cuadro.
     *
     * @param array<string,mixed> $layout
     * @param array<string,bool>  $loads
     * @return array<string,mixed>|null
     */
    public function panelSchedule(array $layout, array $loads = [], int $contractedPower = 5750): ?array
    {
        $tally = $this->tally($layout, $loads);
        $t = $tally['totals'];
        if (array_sum($t) === 0) {
            return null;
        }

        $load = static fn (string $k): bool => (bool) ($loads[$k] ?? false);

        $circuits = [];
        $this->addSplit($circuits, 'C1', 'C6', max($t['lights'], 1));
        $this->addSplit($circuits, 'C2', 'C7', max($t['c2'], 1));
        if ($t['c3'] > 0) {
            $this->addSplit($circuits, 'C3', 'C3', $t['c3']);
        }
        if ($t['c4'] > 0) {
            $this->addSplit($circuits, 'C4', 'C4', $t['c4']);
        }
        $this->addSplit($circuits, 'C5', 'C5', max($t['c5'], 1));
        if ($t['c10'] > 0 || $load('secadora')) {
            $circuits[] = $this->circuit('C10', max($t['c10'], 1));
        }
        foreach (['calefaccion' => 'C8', 'aire' => 'C9', 'domotica' => 'C11', 'vehiculo' => 'C12'] as $flag => $code) {
            if ($load($flag)) {
                $circuits[] = $this->circuit($code, self::CIRCUITS[$code][3] ?? 1);
            }
        }

        $iga = self::IGA_BY_POWER[$contractedPower] ?? 25;
        $differentialCount = max(1, (int) ceil(count($circuits) / 5));

        $differentials = [];
        foreach ($circuits as $i => &$c) {
            $n = intdiv($i, 5) + 1;
            $c['differential'] = $n;
            $differentials[$n][] = $c['code'];
        }
        unset($c);

        $rows = [];
        foreach ($differentials as $n => $codes) {
            $rows[] = ['index' => $n, 'current' => max(40, $iga), 'sensitivity' => 30, 'poles' => '2P', 'circuits' => $codes];
        }

        $devices = 1 + $differentialCount + count($circuits);   // IGA + differentials + one PIA per circuit
        $modules = $devices * self::MODULES_PER_DEVICE;
        $enclosureRows = max(1, (int) ceil($modules / self::MODULES_PER_ROW));

        return [
            'iga' => ['current' => $iga, 'poles' => '2P'],
            'differentials' => $rows,
            'circuits' => $circuits,
            'connected' => $t,
            'orphanSockets' => $tally['orphanSockets'],
            'modules' => [
                'total' => $modules,
                'rows' => $enclosureRows,
                'capacity' => $enclosureRows * self::MODULES_PER_ROW,
            ],
        ];
    }

    /** A room's polygon; a legacy rectangle is expanded into its four corners. */
    private function roomPolygon(array $r): array
    {
        if (is_array($r['points'] ?? null) && count($r['points']) >= 3) {
            $out = [];
            foreach ($r['points'] as $p) {
                if (is_array($p)) {
                    $out[] = [(float) ($p['x'] ?? 0), (float) ($p['y'] ?? 0)];
                }
            }

            return $out;
        }
        if (!isset($r['x'], $r['y'], $r['w'], $r['h'])) {
            return [];
        }
        $x = (float) $r['x'];
        $y = (float) $r['y'];
        $w = (float) $r['w'];
        $h = (float) $r['h'];

        return [[$x, $y], [$x + $w, $y], [$x + $w, $y + $h], [$x, $y + $h]];
    }

    /** Surface of a polygon, by the shoelace formula. Sign-independent, so vertex order does not matter. */
    public function polygonArea(array $points): float
    {
        $sum = 0.0;
        $n = count($points);
        for ($i = 0; $i < $n; ++$i) {
            [$x1, $y1] = $points[$i];
            [$x2, $y2] = $points[($i + 1) % $n];
            $sum += $x1 * $y2 - $x2 * $y1;
        }

        return abs($sum) / 2;
    }

    /** The longest side of the polygon's bounding box — how long a corridor "is". */
    private function polygonLength(array $points): float
    {
        $xs = array_column($points, 0);
        $ys = array_column($points, 1);

        return max(max($xs) - min($xs), max($ys) - min($ys));
    }

    /** Ray casting: is the point inside the polygon? */
    private function pointInPolygon(float $x, float $y, array $points): bool
    {
        $inside = false;
        $n = count($points);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = $points[$i];
            [$xj, $yj] = $points[$j];
            if ((($yi > $y) !== ($yj > $y)) && ($x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1e-12) + $xi)) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    /**
     * Minimum points of use for a room, **exactly** as ITC-BT-25 tabla 2 (Guía Técnica BT-25, jul-12 rev 2).
     *
     * $length is the room's longest span in metres; only corridors are ruled by length rather than surface.
     * `bell` (pulsador de timbre, estancia "Acceso") is returned for completeness but is explicitly NOT a
     * point of use of C1 — the regulation says so.
     *
     * ES: Puntos mínimos de utilización por estancia, **exactamente** según la tabla 2 de la ITC-BT-25.
     * $length es la mayor dimensión de la estancia en metros; solo los pasillos se rigen por longitud.
     *
     * @param array<string,bool> $loads
     * @return array{lights:int,switches:int,c2:int,c3:int,c4:int,c5:int,c8:int,c9:int,c10:int,bell:int}
     */
    public function roomRequirements(string $type, float $area, float $length, array $loads = []): array
    {
        $heat = (bool) ($loads['calefaccion'] ?? false);
        $ac = (bool) ($loads['aire'] ?? false);
        $dryer = (bool) ($loads['secadora'] ?? false);
        // "1 hasta 10 m², dos si S > 10 m²" — the rule that governs most rooms.
        $bySurface = $area > 10 ? 2 : 1;

        $r = ['lights' => 0, 'switches' => 0, 'c2' => 0, 'c3' => 0, 'c4' => 0, 'c5' => 0, 'c8' => 0, 'c9' => 0, 'c10' => 0, 'bell' => 0];

        switch ($type) {
            case 'acceso':
                $r['bell'] = 1;
                break;

            case 'vestibulo':
                $r['lights'] = 1;
                $r['switches'] = 1;
                $r['c2'] = 1;
                break;

            case 'salon':
            case 'comedor':
                $r['lights'] = $bySurface;
                $r['switches'] = $r['lights'];
                $r['c2'] = max(3, (int) ceil($area / 6));   // "3, una por cada 6 m² redondeado al entero superior"
                $r['c8'] = $heat ? $bySurface : 0;
                $r['c9'] = $ac ? $bySurface : 0;
                break;

            case 'dormitorio':
                $r['lights'] = $bySurface;
                $r['switches'] = $r['lights'];
                $r['c2'] = max(3, (int) ceil($area / 6));
                $r['c8'] = $heat ? 1 : 0;                    // en dormitorios, una toma, sin regla de superficie
                $r['c9'] = $ac ? 1 : 0;
                break;

            case 'bano':
                $r['lights'] = 1;
                $r['switches'] = 1;
                $r['c5'] = 1;
                $r['c8'] = $heat ? 1 : 0;
                break;

            case 'pasillo':
                $r['lights'] = max(1, (int) ceil($length / 5));   // "uno cada 5 m de longitud"
                // "un interruptor/conmutador en cada acceso": the plan does not tell us where the doors are,
                // so we can only demand one per light point. Under-demanding on purpose, never over-demanding.
                $r['switches'] = $r['lights'];
                $r['c2'] = $length > 5 ? 2 : 1;
                $r['c8'] = $heat ? 1 : 0;
                break;

            case 'cocina':
                $r['lights'] = $bySurface;
                $r['switches'] = $r['lights'];
                $r['c2'] = 2;   // extractor y frigorífico
                $r['c3'] = 1;   // cocina/horno, base de 25 A
                $r['c4'] = 3;   // lavadora, lavavajillas y termo
                $r['c5'] = 3;   // encima del plano de trabajo
                $r['c8'] = $heat ? 1 : 0;
                $r['c10'] = $dryer ? 1 : 0;
                break;

            case 'terraza':
            case 'vestidor':
                // Light and switch only: the table asks for no socket here.
                $r['lights'] = $bySurface;
                $r['switches'] = $r['lights'];
                break;

            case 'garaje':
            case 'trastero':
            default:
                $r['lights'] = $bySurface;
                $r['switches'] = $r['lights'];
                $r['c2'] = $bySurface;
                break;
        }

        return $r;
    }

    /** The span a corridor is judged by. Without a polygon we can only infer it from a standard width. */
    private function assumedLength(string $type, float $area): float
    {
        return $type === 'pasillo' ? $area / self::ASSUMED_CORRIDOR_WIDTH : 0.0;
    }

    /** Add one or more circuits for a category, splitting when the max points per circuit is exceeded. */
    private function addSplit(array &$circuits, string $code, string $extraCode, int $points): void
    {
        $max = self::CIRCUITS[$code][3] ?? $points;
        $n = max(1, (int) ceil($points / $max));
        for ($i = 0; $i < $n; $i++) {
            $left = $points - $max * $i;
            $circuits[] = $this->circuit($i === 0 ? $code : $extraCode, min($max, $left));
        }
    }

    /** @return array{code:string, name:string, section:string, breaker:int, points:int} */
    private function circuit(string $code, int $points): array
    {
        [$name, $section, $breaker] = self::CIRCUITS[$code];

        return ['code' => $code, 'name' => $name, 'section' => $section, 'breaker' => $breaker, 'points' => $points];
    }

    /**
     * Rough cable estimate: assume a squarish dwelling; per circuit ≈ a trunk run to the zone plus a
     * span per point, with 15 % slack. Grouped by section.
     * @param array<int,array<string,mixed>> $circuits
     * @return array{totalM:float, bySection:array<string,float>}
     */
    private function cableEstimate(array $circuits, float $totalArea): array
    {
        $side = sqrt(max($totalArea, 1));
        $total = 0.0;
        $bySection = [];
        foreach ($circuits as $c) {
            $points = max(1, (int) $c['points']);
            $m = round(($side * 0.9 + $points * 3.0) * 1.15, 1);
            $total += $m;
            $bySection[$c['section']] = round(($bySection[$c['section']] ?? 0) + $m, 1);
        }

        return ['totalM' => round($total, 1), 'bySection' => $bySection];
    }

    /**
     * @param array<int,array<string,mixed>> $circuits
     * @return array<int,array{item:string, qty:int|string}>
     */
    private function materials(array $circuits, int $lights, int $socketsGeneral, int $socketsC5, int $differentials, bool $elevado): array
    {
        $applianceSockets = 0;
        $breakerCounts = [];
        foreach ($circuits as $c) {
            $breakerCounts[$c['breaker']] = ($breakerCounts[$c['breaker']] ?? 0) + 1;
            if (in_array($c['code'], ['C3', 'C4', 'C8', 'C9', 'C10', 'C12'], true)) {
                $applianceSockets += (int) $c['points'];
            }
        }
        ksort($breakerCounts);

        $materials = [
            ['item' => 'Interruptor general automático (IGA) ' . ($elevado ? '40 A' : '25 A'), 'qty' => 1],
            ['item' => 'Interruptor diferencial 2P 40 A 30 mA', 'qty' => $differentials],
        ];
        foreach ($breakerCounts as $a => $n) {
            $materials[] = ['item' => 'Magnetotérmico (PIA) ' . $a . ' A', 'qty' => $n];
        }
        $materials[] = ['item' => 'Bases de enchufe', 'qty' => $socketsGeneral + $socketsC5 + $applianceSockets];
        $materials[] = ['item' => 'Puntos de luz', 'qty' => $lights];
        $materials[] = ['item' => 'Interruptores / conmutadores', 'qty' => $lights];

        return $materials;
    }
}
