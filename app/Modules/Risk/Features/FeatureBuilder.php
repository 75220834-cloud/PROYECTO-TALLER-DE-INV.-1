<?php

declare(strict_types=1);

namespace App\Modules\Risk\Features;

use App\Modules\Locations\Models\Room;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Construye el vector de caracteristicas de una observacion (plan 15.3).
 *
 * PREVENCION DE FUGA TEMPORAL — el punto critico de todo el modulo (15.4)
 *
 * TODA consulta de esta clase filtra por `< $cutoff`. Ninguna caracteristica
 * puede mirar un solo dia mas alla del corte. El motivo no es purismo: si el
 * modelo se entrena con informacion del futuro, sus metricas salen infladas y
 * el trabajo entero deja de ser defendible. Es el error mas comun y mas
 * descalificante en un trabajo de esta clase.
 *
 * Por eso la etiqueta vive en un metodo aparte (`label()`), que es el UNICO
 * que mira hacia adelante. Separarlos permite que exista una prueba que
 * verifique que las caracteristicas no cambian aunque se inventen incidencias
 * despues del corte — y esa prueba es la que sostiene la afirmacion.
 *
 * El corte se interpreta como INSTANTE, no como dia: se usa `<` y no `<=`
 * para que una incidencia ocurrida exactamente en el corte quede del lado
 * del futuro, nunca del pasado.
 */
final class FeatureBuilder
{
    public function build(int $roomId, ?int $categoryId, Carbon $cutoff): FeatureSet
    {
        // Solo se necesita la criticidad: hidratar el modelo entero para leer
        // una columna es trabajo que no se aprovecha, y esto corre una vez por
        // aula y por par en cada recalculo.
        $criticality = Room::query()->where('id', $roomId)->value('criticality');

        return new FeatureSet(
            roomId: $roomId,
            categoryId: $categoryId,
            cutoff: $cutoff->copy(),
            incidents7d: $this->countInWindow($roomId, $categoryId, $cutoff, 7),
            incidents30d: $this->countInWindow($roomId, $categoryId, $cutoff, 30),
            incidents90d: $this->countInWindow($roomId, $categoryId, $cutoff, 90),
            incidentsTotal: $this->countInWindow($roomId, $categoryId, $cutoff, null),
            daysSinceLast: $this->daysSinceLast($roomId, $categoryId, $cutoff),
            trendRatio: $this->trendRatio($roomId, $categoryId, $cutoff),
            daysSinceMaintenance: $this->daysSinceMaintenance($roomId, $cutoff),
            criticality: (int) ($criticality ?? 1),
            equipmentCount: $this->equipmentCount($roomId),
            assistantResolutionRatio: $this->assistantResolutionRatio($roomId, $categoryId, $cutoff),
        );
    }

    /**
     * Etiqueta supervisada: ¿hubo al menos una incidencia en (corte, corte+H]?
     *
     * Este es el unico metodo que mira despues del corte, y por eso esta
     * separado del resto. Nunca debe llamarse desde `build()`.
     */
    public function label(int $roomId, ?int $categoryId, Carbon $cutoff, int $horizonDays): bool
    {
        return $this->baseQuery($roomId, $categoryId)
            ->where('reported_at', '>', $cutoff)
            ->where('reported_at', '<=', $cutoff->copy()->addDays($horizonDays))
            ->exists();
    }

    /**
     * Solo incidencias reales: los borradores no promovidos no son eventos de
     * mantenimiento, son abandonos. Contarlos como incidencias inflaria el
     * riesgo de las aulas donde los docentes se confunden al navegar, que es
     * exactamente lo contrario de lo que interesa medir.
     *
     * @return Builder
     */
    private function baseQuery(int $roomId, ?int $categoryId)
    {
        $query = DB::table('incidents')
            ->where('room_id', $roomId)
            ->where('is_draft', false)
            ->whereNull('merged_into_id')
            ->whereNotNull('reported_at');

        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        return $query;
    }

    private function countInWindow(int $roomId, ?int $categoryId, Carbon $cutoff, ?int $days): int
    {
        $query = $this->baseQuery($roomId, $categoryId)->where('reported_at', '<', $cutoff);

        if ($days !== null) {
            $query->where('reported_at', '>=', $cutoff->copy()->subDays($days));
        }

        return $query->count();
    }

    private function daysSinceLast(int $roomId, ?int $categoryId, Carbon $cutoff): ?int
    {
        $last = $this->baseQuery($roomId, $categoryId)
            ->where('reported_at', '<', $cutoff)
            ->max('reported_at');

        if ($last === null) {
            return null;
        }

        return (int) Carbon::parse($last)->diffInDays($cutoff);
    }

    /**
     * Tendencia: ultimos 30 dias contra los 30 anteriores.
     *
     * Devuelve null cuando el periodo anterior esta vacio. Es deliberado: sin
     * denominador no hay razon que calcular, y sustituirlo por 0 o por 1
     * fabricaria una tendencia que los datos no respaldan.
     */
    private function trendRatio(int $roomId, ?int $categoryId, Carbon $cutoff): ?float
    {
        $recent = $this->countInWindow($roomId, $categoryId, $cutoff, 30);

        $previous = $this->baseQuery($roomId, $categoryId)
            ->where('reported_at', '<', $cutoff->copy()->subDays(30))
            ->where('reported_at', '>=', $cutoff->copy()->subDays(60))
            ->count();

        if ($previous === 0) {
            return null;
        }

        return round($recent / $previous, 3);
    }

    private function daysSinceMaintenance(int $roomId, Carbon $cutoff): ?int
    {
        $last = DB::table('maintenance_records')
            ->join('equipment', 'equipment.id', '=', 'maintenance_records.equipment_id')
            ->where('equipment.room_id', $roomId)
            ->where('maintenance_records.performed_at', '<', $cutoff)
            ->max('maintenance_records.performed_at');

        if ($last === null) {
            return null;
        }

        return (int) Carbon::parse($last)->diffInDays($cutoff);
    }

    private function equipmentCount(int $roomId): int
    {
        return DB::table('equipment')
            ->where('room_id', $roomId)
            ->where('is_active', true)
            ->count();
    }

    /**
     * Proporcion historica resuelta por el propio docente. Es un indicador
     * indirecto de complejidad: un aula cuyas incidencias siempre exigen que
     * alguien se desplace tiene problemas de otra naturaleza que una donde
     * basta reconectar un cable.
     */
    private function assistantResolutionRatio(int $roomId, ?int $categoryId, Carbon $cutoff): ?float
    {
        $total = $this->baseQuery($roomId, $categoryId)
            ->where('reported_at', '<', $cutoff)
            ->whereNotNull('resolution_type')
            ->count();

        if ($total === 0) {
            return null;
        }

        $byAssistant = $this->baseQuery($roomId, $categoryId)
            ->where('reported_at', '<', $cutoff)
            ->whereIn('resolution_type', ['assistant', 'remote'])
            ->count();

        return round($byAssistant / $total, 3);
    }
}
