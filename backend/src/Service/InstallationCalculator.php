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

        // 1) Points per room (ITC-BT-25) and totals.
        $rooms = [];
        $totalArea = 0.0;
        $lights = 0;
        $socketsGeneral = 0;
        $socketsC5 = 0;
        $switches = 0;
        $hasKitchen = false;
        foreach ($roomsIn as $r) {
            $type = (string) ($r['type'] ?? 'otros');
            $area = max(0.0, (float) ($r['area'] ?? 0));
            $p = $this->roomPoints($type, $area);
            $rooms[] = ['type' => $type, 'area' => $area] + $p;
            $totalArea += $area;
            $lights += $p['lights'];
            $socketsGeneral += $p['socketsGeneral'];
            $socketsC5 += $p['socketsC5'];
            $switches += $p['switches'];
            $hasKitchen = $hasKitchen || $type === 'cocina';
        }

        $load = static fn (string $k, bool $default = false): bool => (bool) ($loads[$k] ?? $default);

        // 2) Circuits (mandatory básico C1/C2/C5, appliance C3/C4, elevado extras).
        $circuits = [];
        $this->addSplit($circuits, 'C1', 'C6', max($lights, 1));
        $this->addSplit($circuits, 'C2', 'C7', max($socketsGeneral, 1));
        if ($load('cocina', $hasKitchen)) {
            $circuits[] = $this->circuit('C3', 2);
        }
        if ($load('lavadora', $hasKitchen)) {
            $circuits[] = $this->circuit('C4', 3);
        }
        $this->addSplit($circuits, 'C5', 'C5', max($socketsC5, 1));
        foreach (['calefaccion' => 'C8', 'aire' => 'C9', 'secadora' => 'C10', 'domotica' => 'C11', 'vehiculo' => 'C12'] as $flag => $code) {
            if ($load($flag)) {
                $circuits[] = $this->circuit($code, self::CIRCUITS[$code][3] ?? 1);
            }
        }

        // 3) Grade & contracted power.
        $autoElevado = $totalArea > 160 || $load('calefaccion') || $load('aire') || $load('secadora') || $load('domotica') || $load('vehiculo');
        $gradeInput = in_array($input['grade'] ?? 'auto', ['basico', 'elevado'], true) ? $input['grade'] : 'auto';
        $elevado = $gradeInput === 'elevado' || ($gradeInput === 'auto' && $autoElevado) || count($circuits) > 5;
        $grade = $elevado ? 'elevado' : 'basico';
        $contractedPower = $elevado ? 9200 : 5750;

        // 4) Differentials: one per 5 circuits (min 2 on elevado).
        $differentials = max($elevado ? 2 : 1, (int) ceil(count($circuits) / 5));
        foreach ($circuits as $i => &$c) {
            $c['differential'] = intdiv($i, 5) + 1;
        }
        unset($c);

        // 5) Cable estimate + materials.
        $cable = $this->cableEstimate($circuits, $totalArea);
        $materials = $this->materials($circuits, $lights, $socketsGeneral, $socketsC5, $differentials, $elevado);

        $notes = [];
        if ($gradeInput === 'basico' && count($circuits) > 5) {
            $notes[] = 'La instalación supera los 5 circuitos: corresponde grado de electrificación elevado.';
        }
        if ($autoElevado && $gradeInput === 'auto') {
            $notes[] = 'Grado elevado por superficie o cargas previstas (calefacción, A/A, secadora, domótica o vehículo).';
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

    /**
     * Minimum points of use for a room, per ITC-BT-25 (approximated from surface).
     * @return array{lights:int, socketsGeneral:int, socketsC5:int, switches:int}
     */
    private function roomPoints(string $type, float $area): array
    {
        $big = $area > 10;
        $p = ['lights' => 1, 'socketsGeneral' => 0, 'socketsC5' => 0, 'switches' => 1];

        switch ($type) {
            case 'salon':
            case 'comedor':
            case 'dormitorio':
                $p['lights'] = $big ? 2 : 1;
                $p['socketsGeneral'] = $big ? 4 : 3;
                break;
            case 'cocina':
                $p['lights'] = $big ? 2 : 1;
                $p['socketsC5'] = $big ? 4 : 3; // encimera / auxiliares
                break;
            case 'bano':
                $p['lights'] = 1;
                $p['socketsC5'] = 1;
                break;
            case 'pasillo':
                $p['lights'] = max(1, (int) ceil($area / 6)); // ~1 punto por 5 m
                $p['socketsGeneral'] = 1;
                break;
            case 'vestibulo':
            case 'terraza':
            case 'garaje':
            case 'trastero':
            default:
                $p['lights'] = 1;
                $p['socketsGeneral'] = 1;
                break;
        }
        $p['switches'] = $p['lights'];

        return $p;
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
