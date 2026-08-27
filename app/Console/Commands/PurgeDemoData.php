<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Borra TODOS los datos marcados como demostración (plan 21.1).
 *
 * Es el comando que hay que ejecutar antes de cargar el catálogo real: si
 * quedan aulas de prueba conviviendo con las de verdad, las métricas de la
 * investigación quedan contaminadas y ya no hay forma de separarlas después.
 *
 * BORRA DE VERDAD, no marca como eliminado. Las aulas usan borrado suave en
 * la operación normal —desactivar un aula no debe perder su historial—, pero
 * una purga que dejara filas ocultas no serviría para nada: seguirían
 * apareciendo en las consultas de la investigación.
 *
 * El orden importa. Las claves foráneas de la cadena de ubicaciones son
 * RESTRICT a propósito (plan 26), así que hay que vaciar de dentro hacia
 * fuera: lo que cuelga de las incidencias, las incidencias, los equipos, y
 * solo entonces aulas → pisos → pabellones → sede. Todo en una transacción:
 * una purga a medias deja el catálogo en un estado que el sistema no sabe
 * manejar.
 */
class PurgeDemoData extends Command
{
    protected $signature = 'demo:purge {--force : No pedir confirmación}';

    protected $description = 'Elimina todos los datos de demostración';

    public function handle(): int
    {
        $rooms = DB::table('rooms')->where('is_demo', true)->count();
        $incidents = DB::table('incidents')
            ->whereIn('room_id', DB::table('rooms')->where('is_demo', true)->select('id'))
            ->count();

        if ($rooms === 0) {
            $this->info('No hay datos de demostración que borrar.');

            return self::SUCCESS;
        }

        $this->warn("Se van a borrar {$rooms} aulas de demostración y {$incidents} incidencias asociadas.");
        $this->line('Esto no se puede deshacer.');

        if (! $this->option('force') && ! $this->confirm('¿Continuar?')) {
            $this->info('Cancelado.');

            return self::SUCCESS;
        }

        DB::transaction(function (): void {
            $roomIds = DB::table('rooms')->where('is_demo', true)->pluck('id');
            $incidentIds = DB::table('incidents')->whereIn('room_id', $roomIds)->pluck('id');

            // Todo lo que cuelga de una incidencia.
            foreach ([
                'diagnostic_answers', 'incident_events', 'incident_messages',
                'satisfaction_responses', 'assistant_interactions',
            ] as $table) {
                if (DB::getSchemaBuilder()->hasTable($table)) {
                    DB::table($table)->whereIn('incident_id', $incidentIds)->delete();
                }
            }

            // Las incidencias fusionadas apuntan a otra incidencia: hay que
            // soltar el enlace antes de borrar, o la FK lo impide.
            DB::table('incidents')->whereIn('merged_into_id', $incidentIds)->update(['merged_into_id' => null]);
            DB::table('incidents')->whereIn('id', $incidentIds)->delete();

            DB::table('incident_abuse_rejections')->whereIn('room_id', $roomIds)->delete();
            DB::table('risk_scores')->whereIn('scope_id', $roomIds)->whereIn('scope_type', ['room', 'room_category'])->delete();
            DB::table('metric_snapshots')->whereIn('room_id', $roomIds)->delete();

            $equipmentIds = DB::table('equipment')->whereIn('room_id', $roomIds)->pluck('id');
            DB::table('maintenance_records')->whereIn('equipment_id', $equipmentIds)->delete();
            DB::table('equipment')->whereIn('id', $equipmentIds)->delete();

            // De dentro hacia fuera: aulas → pisos → pabellones → sedes.
            DB::table('rooms')->whereIn('id', $roomIds)->delete();

            $buildingIds = DB::table('buildings')->where('is_demo', true)->pluck('id');
            DB::table('floors')->whereIn('building_id', $buildingIds)->delete();
            DB::table('buildings')->whereIn('id', $buildingIds)->delete();
            DB::table('sites')->where('is_demo', true)->delete();
        });

        $this->info('Datos de demostración eliminados.');

        // El banco de imágenes y los documentos demo NO se tocan aquí a
        // propósito: los diagramas de conectores son correctos y siguen
        // sirviendo con el catálogo real. Borrarlos obligaría a rehacer el
        // trabajo visual sin ninguna ganancia.
        $this->line('El banco de imágenes y los documentos de conocimiento se conservan.');

        return self::SUCCESS;
    }
}
