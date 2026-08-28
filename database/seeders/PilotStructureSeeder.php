<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Estructura APROXIMADA del piloto, para probar el sistema a escala real.
 *
 * QUE ES REAL AQUI Y QUE NO — leer antes de usar estos datos para nada.
 *
 * REAL (lo indicó Ítalo; los pabellones los confirmó el 2026-08-28):
 *   - Sede única: Huancayo.
 *   - Del pabellón A al K existen, pero el piloto usa C, D, E, G, H, I, J.
 *   - Esos pabellones tienen 5 pisos, y entre 3 y 4 aulas por piso.
 *
 * NO REAL, generado aquí:
 *   - Los CÓDIGOS concretos de cada aula (C101, C102...). La convención es
 *     verosímil, pero nadie la ha confirmado contra el catálogo oficial.
 *   - Cuántas aulas exactas tiene cada piso (se alterna 3 y 4).
 *   - Capacidades y criticidades.
 *
 * POR ESO TODO LLEVA is_demo = true, y la interfaz sigue mostrando el aviso
 * de "DATOS DE DEMOSTRACIÓN". Lo que NO lleva es el prefijo `DEMO-` en el
 * código, y esa es una excepción deliberada al plan (§21.1): con `DEMO-C305`
 * en pantalla no se puede evaluar de verdad si un docente encuentra su aula
 * en la cascada, que es justo lo que hay que medir antes del piloto. El
 * marcado se mantiene donde importa —la bandera de base de datos, el aviso
 * en pantalla y `demo:purge`—, así que estos datos siguen sin poder
 * presentarse como reales ni contaminar la investigación.
 *
 * Cuando Brayan entregue el catálogo oficial, esto se purga y se reemplaza.
 * No se corrige a mano: se sustituye entero.
 *
 * Se ejecuta aparte del seed normal, con:
 *
 *     php artisan db:seed --class=Database\\Seeders\\PilotStructureSeeder
 */
class PilotStructureSeeder extends Seeder
{
    /**
     * Los 7 pabellones del piloto, confirmados por Ítalo el 2026-08-28.
     * A, B, F y K existen en la sede pero quedan fuera del alcance acordado.
     *
     * @var list<string>
     */
    private const BUILDINGS = ['C', 'D', 'E', 'G', 'H', 'I', 'J'];

    private const FLOORS = 5;

    public function run(): void
    {
        $now = now();

        $siteId = $this->upsert('sites', ['code' => 'HYO'], [
            'name' => 'Huancayo',
            'is_active' => true,
            'is_demo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $rooms = 0;

        foreach (self::BUILDINGS as $index => $letter) {
            $buildingId = $this->upsert('buildings', ['site_id' => $siteId, 'code' => $letter], [
                'name' => "Pabellón {$letter}",
                'sort_order' => $index + 1,
                'is_active' => true,
                'is_demo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            for ($floor = 1; $floor <= self::FLOORS; $floor++) {
                $floorId = $this->upsert('floors', ['building_id' => $buildingId, 'number' => $floor], [
                    'label' => "Piso {$floor}",
                    'is_active' => true,
                    'is_demo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // Entre 3 y 4 aulas: se alterna para que el catálogo no quede
                // artificialmente uniforme. Un catálogo perfectamente regular
                // esconde los fallos que solo aparecen con datos desparejos.
                $count = ($floor % 2 === 0) ? 4 : 3;

                for ($n = 1; $n <= $count; $n++) {
                    $code = sprintf('%s%d%02d', $letter, $floor, $n);

                    $this->upsert('rooms', ['floor_id' => $floorId, 'code' => $code], [
                        'name' => "Aula {$code}",
                        'capacity' => 30 + ($n * 5),

                        // Criticidad: la primera aula de cada piso se marca
                        // media, solo para que el cálculo de prioridad tenga
                        // con qué trabajar. NO describe la criticidad real de
                        // ninguna aula: eso lo dirá soporte. La única alta es
                        // el laboratorio, y también es una suposición.
                        'criticality' => $n === 1 ? 2 : 1,
                        'is_active' => true,
                        'is_demo' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $rooms++;
                }
            }
        }

        $rooms += $this->seedIrregularities($siteId, $now);
        $this->seedEquipment($now);

        $this->command->info("  Piloto: {$rooms} aulas en ".count(self::BUILDINGS).' pabellones (marcadas is_demo).');
        $this->command->warn('  Los códigos de aula son PROVISIONALES: nadie los ha confirmado contra el catálogo oficial.');
    }

    /**
     * Casos incómodos que la suite necesita para no pasar por casualidad
     * (plan 21 y 17.4).
     *
     * Un laboratorio con código fuera de nomenclatura demuestra que la
     * ubicación se resuelve consultando el catálogo y nunca concatenando
     * pabellón + piso + número. Un aula desactivada demuestra que el
     * catálogo la esconde del docente sin borrarla.
     *
     * No son relleno: sin ellos, las pruebas de integridad no probarían nada.
     */
    private function seedIrregularities(int $siteId, Carbon $now): int
    {
        $floorId = DB::table('floors')
            ->join('buildings', 'buildings.id', '=', 'floors.building_id')
            ->where('buildings.site_id', $siteId)
            ->where('buildings.code', 'H')
            ->where('floors.number', 3)
            ->value('floors.id');

        if ($floorId === null) {
            return 0;
        }

        $this->upsert('rooms', ['floor_id' => (int) $floorId, 'code' => 'LAB-SIS-2'], [
            'name' => 'Laboratorio de Sistemas 2',
            'capacity' => 24,
            'criticality' => 3,
            'is_active' => true,
            'is_demo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->upsert('rooms', ['floor_id' => (int) $floorId, 'code' => 'H304'], [
            'name' => 'Aula H304 (fuera de servicio)',
            'capacity' => 30,
            'criticality' => 1,
            'is_active' => false,
            'is_demo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return 2;
    }

    /**
     * Proyector, PC y parlantes por aula activa.
     *
     * Marca, modelo y número de serie quedan NULOS a propósito: inventar
     * inventario institucional es exactamente lo que el plan prohíbe (§69).
     * Los pondrá soporte cuando cargue el inventario real.
     */
    private function seedEquipment(Carbon $now): void
    {
        $types = DB::table('equipment_types')
            ->whereIn('code', ['PROJECTOR', 'DESKTOP_PC', 'SPEAKERS'])
            ->pluck('id', 'code');

        if ($types->isEmpty()) {
            $this->command->warn('  Sin tipos de equipo en el catálogo: no se sembraron equipos.');

            return;
        }

        $rows = [];

        DB::table('rooms')
            ->where('is_demo', true)
            ->where('is_active', true)
            ->orderBy('id')
            ->chunk(200, function ($rooms) use ($types, $now, &$rows) {
                foreach ($rooms as $room) {
                    foreach ($types as $code => $typeId) {
                        $rows[] = [
                            'room_id' => $room->id,
                            'equipment_type_id' => $typeId,
                            'asset_code' => $room->code.'-'.match ($code) {
                                'PROJECTOR' => 'PRY',
                                'DESKTOP_PC' => 'PC',
                                default => 'SPK',
                            },
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
            });

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('equipment')->upsert($chunk, ['asset_code'], ['status', 'is_active', 'updated_at']);
        }
    }

    /**
     * Inserta si no existe y devuelve el id. Idempotente: volver a sembrar
     * no duplica nada ni pisa lo que ya se cargó.
     *
     * @param  array<string, mixed>  $keys
     * @param  array<string, mixed>  $values
     */
    private function upsert(string $table, array $keys, array $values): int
    {
        $existing = DB::table($table)->where($keys)->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        return (int) DB::table($table)->insertGetId($keys + $values);
    }
}
