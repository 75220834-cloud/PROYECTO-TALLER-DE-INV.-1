<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Datos DEMO de ubicaciones y equipos.
 *
 * TODO lo que crea este seeder lleva prefijo DEMO- e is_demo = true, para
 * que jamas pueda confundirse con informacion institucional real (plan 21
 * y 69). Nada de aqui pretende describir la Universidad Continental: los
 * datos reales los entregara soporte y reemplazaran a estos.
 *
 * IRREGULARIDADES DELIBERADAS (plan 21, v3)
 * ------------------------------------------------------------------
 * El catalogo demo incluye a proposito tres casos incomodos:
 *
 *   1. Un aula con codigo fuera de nomenclatura (DEMO-LAB-2 en el
 *      pabellon C, piso 3). Si alguien "optimizara" el codigo derivando
 *      el codigo del aula por concatenacion, esta aula lo delataria de
 *      inmediato.
 *   2. Un pabellon que empieza en el piso 2, sin piso 1.
 *   3. Un aula desactivada.
 *
 * Sin estos casos, las pruebas de integridad pasarian por casualidad y no
 * demostrarian nada. Son parte del diseno de la suite, no relleno.
 */
class DemoLocationsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $siteId = $this->upsertReturningId('sites', ['code' => 'DEMO-HYO'], [
            'name' => 'DEMO - Sede de demostracion',
            'is_active' => true,
            'is_demo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Pabellon => [pisos disponibles]
        // El pabellon C arranca en el piso 2: irregularidad (2).
        $buildings = [
            'DEMO-A' => ['name' => 'DEMO - Pabellon A', 'floors' => [1, 2, 3], 'order' => 1],
            'DEMO-B' => ['name' => 'DEMO - Pabellon B', 'floors' => [1, 2], 'order' => 2],
            'DEMO-C' => ['name' => 'DEMO - Pabellon C', 'floors' => [2, 3], 'order' => 3],
        ];

        $roomCount = 0;

        foreach ($buildings as $bCode => $bData) {
            $buildingId = $this->upsertReturningId('buildings', [
                'site_id' => $siteId,
                'code' => $bCode,
            ], [
                'name' => $bData['name'],
                'sort_order' => $bData['order'],
                'is_active' => true,
                'is_demo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($bData['floors'] as $number) {
                $floorId = $this->upsertReturningId('floors', [
                    'building_id' => $buildingId,
                    'number' => $number,
                ], [
                    'label' => "Piso {$number}",
                    'is_active' => true,
                    'is_demo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // Letra del pabellon para formar codigos tipo DEMO-C301.
                $letter = substr($bCode, -1);

                for ($n = 1; $n <= 5; $n++) {
                    $code = sprintf('DEMO-%s%d%02d', $letter, $number, $n);

                    // Irregularidad (3): una sola aula desactivada.
                    $isActive = ! ($bCode === 'DEMO-B' && $number === 2 && $n === 5);

                    $this->upsertReturningId('rooms', [
                        'floor_id' => $floorId,
                        'code' => $code,
                    ], [
                        'name' => "Aula {$code}",
                        'capacity' => 30 + $n,
                        'criticality' => ($n === 1) ? 2 : 1,
                        'is_active' => $isActive,
                        'is_demo' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $roomCount++;
                }

                // Irregularidad (1): codigo que NO sigue la nomenclatura.
                if ($bCode === 'DEMO-C' && $number === 3) {
                    $this->upsertReturningId('rooms', [
                        'floor_id' => $floorId,
                        'code' => 'DEMO-LAB-2',
                    ], [
                        'name' => 'DEMO - Laboratorio 2',
                        'capacity' => 24,
                        'criticality' => 3,
                        'is_active' => true,
                        'is_demo' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $roomCount++;
                }
            }
        }

        $this->seedEquipment($now);

        $this->command->info("  DEMO: {$roomCount} aulas creadas (todas marcadas is_demo).");
    }

    /**
     * Equipos demo: proyector, PC y parlantes en cada aula activa.
     * Marcas y modelos quedan NULOS: inventarlos seria fabricar inventario
     * institucional, justo lo que el plan prohibe (plan 69).
     */
    private function seedEquipment(Carbon $now): void
    {
        $types = DB::table('equipment_types')
            ->whereIn('code', ['PROJECTOR', 'DESKTOP_PC', 'SPEAKERS'])
            ->pluck('id', 'code');

        $rooms = DB::table('rooms')->where('is_demo', true)->where('is_active', true)->get(['id', 'code']);

        $rows = [];
        foreach ($rooms as $room) {
            foreach ($types as $typeCode => $typeId) {
                $suffix = match ($typeCode) {
                    'PROJECTOR' => 'PRY',
                    'DESKTOP_PC' => 'PC',
                    default => 'SPK',
                };

                $rows[] = [
                    'room_id' => $room->id,
                    'equipment_type_id' => $typeId,
                    'asset_code' => "{$room->code}-{$suffix}",
                    'brand' => null,
                    'model' => null,
                    'serial_number' => null,
                    'status' => 'operational',
                    'commissioned_at' => null,
                    'is_active' => true,
                    'is_demo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('equipment')->upsert($chunk, ['asset_code'], ['status', 'is_active', 'updated_at']);
        }
    }

    /**
     * Inserta si no existe y devuelve el id. Idempotente: volver a sembrar
     * no duplica ni rompe nada.
     *
     * @param  array<string, mixed>  $keys
     * @param  array<string, mixed>  $values
     */
    private function upsertReturningId(string $table, array $keys, array $values): int
    {
        $existing = DB::table($table)->where($keys)->first();

        if ($existing !== null) {
            DB::table($table)->where('id', $existing->id)->update(
                array_diff_key($values, ['created_at' => null])
            );

            return (int) $existing->id;
        }

        return (int) DB::table($table)->insertGetId(array_merge($keys, $values));
    }
}
