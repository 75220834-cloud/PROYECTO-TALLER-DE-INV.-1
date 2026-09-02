<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reportes agrupados por periodo, pabellon, aula, categoria, tipo de equipo
 * y tecnico (plan CU-S-15).
 *
 * NO duplica el tablero. El tablero responde "como va el servicio ahora";
 * esto responde "donde se concentra el problema", que es la pregunta que
 * sostiene una decision de compra, de mantenimiento o de turno.
 *
 * Todas las agrupaciones salen de UNA misma consulta filtrada, para que las
 * cifras de las distintas tablas del reporte no puedan contradecirse entre
 * si: si el total por pabellon suma 40 y el total por categoria suma 38,
 * el reporte deja de ser citable.
 */
final class ReportBuilder
{
    /** @var list<string> */
    public const AGRUPACIONES = ['pabellon', 'aula', 'categoria', 'equipo', 'tecnico', 'mes'];

    /**
     * @param  array{desde:Carbon,hasta:Carbon,building_id:?int,category_id:?int,assigned_to:?int}  $filtros
     */
    public function agrupar(string $por, array $filtros): Collection
    {
        $q = match ($por) {
            'pabellon' => $this->base($filtros)
                ->selectRaw('CONCAT(b.code, " - ", b.name) AS etiqueta')
                ->groupBy('etiqueta'),
            'aula' => $this->base($filtros)
                ->selectRaw('r.code AS etiqueta')
                ->groupBy('etiqueta'),
            'categoria' => $this->base($filtros)
                ->selectRaw('COALESCE(c.name, "Sin clasificar") AS etiqueta')
                ->groupBy('etiqueta'),
            'equipo' => $this->base($filtros)
                ->selectRaw('COALESCE(et.name, "Sin equipo asociado") AS etiqueta')
                ->groupBy('etiqueta'),
            // Sin asignar es una fila legitima y de las mas informativas:
            // son los tickets que nadie tomo.
            'tecnico' => $this->base($filtros)
                ->selectRaw('COALESCE(u.name, "Sin asignar") AS etiqueta')
                ->groupBy('etiqueta'),
            'mes' => $this->base($filtros)
                ->selectRaw('DATE_FORMAT(i.reported_at, "%Y-%m") AS etiqueta')
                ->groupBy('etiqueta'),
            default => $this->base($filtros)
                ->selectRaw('CONCAT(b.code, " - ", b.name) AS etiqueta')
                ->groupBy('etiqueta'),
        };

        return $q->selectRaw('
                COUNT(*) AS total,
                SUM(CASE WHEN i.blocks_class = 1 THEN 1 ELSE 0 END) AS bloquearon_clase,
                SUM(CASE WHEN i.resolution_type = "assistant" THEN 1 ELSE 0 END) AS resueltas_sin_visita,
                SUM(CASE WHEN i.resolution_type = "onsite" THEN 1 ELSE 0 END) AS requirieron_visita,
                SUM(CASE WHEN i.resolved_at IS NULL THEN 1 ELSE 0 END) AS abiertas,
                AVG(CASE WHEN i.resolved_at IS NOT NULL AND i.reported_at IS NOT NULL
                    THEN TIMESTAMPDIFF(MINUTE, i.reported_at, i.resolved_at) END) AS minutos_promedio
            ')
            // El orden por mes es cronologico; en el resto, lo que mas duele
            // primero.
            ->when($por === 'mes', fn (Builder $q): Builder => $q->orderBy('etiqueta'))
            ->when($por !== 'mes', fn (Builder $q): Builder => $q->orderByDesc('total'))
            ->get();
    }

    /**
     * Consulta base con los filtros ya aplicados.
     *
     * @param  array{desde:Carbon,hasta:Carbon,building_id:?int,category_id:?int,assigned_to:?int}  $filtros
     */
    private function base(array $filtros): Builder
    {
        return DB::table('incidents as i')
            ->leftJoin('rooms as r', 'r.id', '=', 'i.room_id')
            ->leftJoin('floors as f', 'f.id', '=', 'r.floor_id')
            ->leftJoin('buildings as b', 'b.id', '=', 'f.building_id')
            ->leftJoin('incident_categories as c', 'c.id', '=', 'i.category_id')
            ->leftJoin('equipment as e', 'e.id', '=', 'i.equipment_id')
            ->leftJoin('equipment_types as et', 'et.id', '=', 'e.equipment_type_id')
            ->leftJoin('users as u', 'u.id', '=', 'i.assigned_to')
            // Los borradores son intentos abandonados: contarlos inflaria
            // cualquier cifra que se cite en la tesis.
            ->where('i.is_draft', false)
            ->whereBetween('i.reported_at', [$filtros['desde'], $filtros['hasta']])
            ->when($filtros['building_id'], fn (Builder $q, int $id): Builder => $q->where('f.building_id', $id))
            ->when($filtros['category_id'], fn (Builder $q, int $id): Builder => $q->where('i.category_id', $id))
            ->when($filtros['assigned_to'], fn (Builder $q, int $id): Builder => $q->where('i.assigned_to', $id));
    }

    /**
     * Totales del periodo, para que quien lea el reporte sepa sobre cuantos
     * casos se esta hablando antes de mirar los porcentajes.
     *
     * @param  array{desde:Carbon,hasta:Carbon,building_id:?int,category_id:?int,assigned_to:?int}  $filtros
     * @return array{total:int,abiertas:int,bloquearon_clase:int,resueltas_sin_visita:int}
     */
    public function totales(array $filtros): array
    {
        $fila = $this->base($filtros)->selectRaw('
            COUNT(*) AS total,
            SUM(CASE WHEN i.resolved_at IS NULL THEN 1 ELSE 0 END) AS abiertas,
            SUM(CASE WHEN i.blocks_class = 1 THEN 1 ELSE 0 END) AS bloquearon_clase,
            SUM(CASE WHEN i.resolution_type = "assistant" THEN 1 ELSE 0 END) AS resueltas_sin_visita
        ')->first();

        return [
            'total' => (int) ($fila->total ?? 0),
            'abiertas' => (int) ($fila->abiertas ?? 0),
            'bloquearon_clase' => (int) ($fila->bloquearon_clase ?? 0),
            'resueltas_sin_visita' => (int) ($fila->resueltas_sin_visita ?? 0),
        ];
    }
}
