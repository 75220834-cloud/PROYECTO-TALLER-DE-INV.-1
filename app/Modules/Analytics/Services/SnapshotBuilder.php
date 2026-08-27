<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Agregados diarios por aula y categoria (plan 11.1, tabla metric_snapshots).
 *
 * Es CACHE RECONSTRUIBLE, no fuente de verdad. Se puede borrar entera y
 * regenerar desde `incidents` sin perder nada. Por eso el metodo es
 * idempotente: recalcular un dia dos veces deja el mismo resultado, lo que
 * permite reprocesar sin miedo cuando se corrige un dato historico.
 *
 * Existe por rendimiento: el tablero de un piloto de meses no deberia
 * recorrer la tabla de incidencias completa en cada carga.
 */
final class SnapshotBuilder
{
    public function buildDay(Carbon $day): int
    {
        $from = $day->copy()->startOfDay();
        $to = $day->copy()->endOfDay();

        /** @var list<object> $rows */
        $rows = DB::table('incidents')
            ->selectRaw('
                room_id,
                category_id,
                COUNT(*) AS incidents_total,
                SUM(CASE WHEN resolution_type IN ("assistant", "remote") THEN 1 ELSE 0 END) AS by_assistant,
                SUM(CASE WHEN resolution_type = "onsite" THEN 1 ELSE 0 END) AS onsite
            ')
            ->where('is_draft', false)
            ->whereBetween('reported_at', [$from, $to])
            ->groupBy('room_id', 'category_id')
            ->get()
            ->all();

        // Los borradores abandonados se cuentan aparte y por aula: no tienen
        // categoria (el docente se fue antes de elegirla), asi que no pueden
        // sumarse a las filas anteriores sin inventarles una.
        /** @var list<object> $abandoned */
        $abandoned = DB::table('incidents')
            ->selectRaw('room_id, COUNT(*) AS abandoned')
            ->where('is_draft', true)
            ->whereBetween('confirmed_at', [$from, $to])
            ->groupBy('room_id')
            ->get()
            ->all();

        $written = 0;

        foreach ($rows as $row) {
            DB::table('metric_snapshots')->updateOrInsert(
                ['day' => $from->toDateString(), 'room_id' => $row->room_id, 'category_id' => $row->category_id],
                [
                    'incidents_total' => (int) $row->incidents_total,
                    'resolved_by_assistant' => (int) $row->by_assistant,
                    'resolved_onsite' => (int) $row->onsite,
                    'median_resolution_minutes' => $this->medianResolution((int) $row->room_id, $row->category_id, $from, $to),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
            $written++;
        }

        foreach ($abandoned as $row) {
            DB::table('metric_snapshots')->updateOrInsert(
                ['day' => $from->toDateString(), 'room_id' => $row->room_id, 'category_id' => null],
                [
                    'abandoned_drafts' => (int) $row->abandoned,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
            $written++;
        }

        return $written;
    }

    /**
     * Mediana, no media (plan 29). Una incidencia olvidada un fin de semana
     * desplaza la media varias horas y vuelve el dato inservible para
     * describir el caso tipico.
     */
    private function medianResolution(int $roomId, ?int $categoryId, Carbon $from, Carbon $to): ?int
    {
        $query = DB::table('incidents')
            ->where('room_id', $roomId)
            ->where('is_draft', false)
            ->whereNotNull('resolved_at')
            ->whereNotNull('confirmed_at')
            ->whereBetween('reported_at', [$from, $to]);

        $categoryId === null
            ? $query->whereNull('category_id')
            : $query->where('category_id', $categoryId);

        /** @var list<int> $values */
        $values = $query
            ->selectRaw('TIMESTAMPDIFF(MINUTE, confirmed_at, resolved_at) AS minutes')
            ->pluck('minutes')
            ->map(static fn ($v): int => (int) $v)
            ->sort()
            ->values()
            ->all();

        if ($values === []) {
            return null;
        }

        $count = count($values);
        $middle = intdiv($count, 2);

        return $count % 2 === 1
            ? $values[$middle]
            : (int) round(($values[$middle - 1] + $values[$middle]) / 2);
    }
}
