<?php

declare(strict_types=1);

namespace App\Modules\Incidents\Services;

use App\Modules\Incidents\Models\Incident;
use App\Modules\Incidents\Models\IncidentCategory;
use App\Modules\Locations\Models\Room;
use App\Shared\Enums\IncidentPriority;
use Illuminate\Support\Facades\DB;

/**
 * Calculo de la prioridad de un ticket (plan 16.4).
 *
 * La prioridad la fija SIEMPRE el sistema. El docente aporta una senal
 * ("impide continuar la clase"), pero no elige el valor: si pudiera, todo
 * llegaria marcado como urgente y el campo dejaria de ordenar el trabajo
 * de soporte.
 *
 * El resultado es EXPLICABLE. Cada factor que sumo queda registrado, de
 * modo que un tecnico pueda ver por que un ticket esta por encima de otro
 * en su bandeja. Una prioridad sin explicacion se percibe como arbitraria
 * y termina ignorandose.
 */
final class PriorityCalculator
{
    /** Incidencias de la misma aula y categoria que se consideran recurrencia. */
    private const RECURRENCE_THRESHOLD = 3;

    private const RECURRENCE_WINDOW_DAYS = 30;

    public function calculate(
        ?IncidentCategory $category,
        Room $room,
        bool $blocksClass,
    ): PriorityResult {
        $factors = [];

        // 1. Base: la prioridad por defecto de la categoria.
        $level = $this->baseLevel($category);
        $factors[] = sprintf(
            'Categoría %s (base: %s)',
            $category === null ? 'sin clasificar' : $category->name,
            IncidentPriority::fromLevel($level)->value
        );

        // 2. La clase esta detenida. Es el factor de mayor peso: no es lo
        //    mismo un proyector que falla en un aula vacia que uno que
        //    tiene a cuarenta personas esperando.
        if ($blocksClass) {
            $level++;
            $factors[] = 'El problema impide continuar la clase (+1)';
        }

        // 3. Criticidad del aula (auditorios, laboratorios).
        if ($room->criticality >= 3) {
            $level++;
            $factors[] = 'Aula de criticidad alta (+1)';
        }

        // 4. Reincidencia: un problema que vuelve suele indicar que la
        //    reparacion anterior no resolvio la causa.
        $recent = $this->recentSimilarCount($room, $category);
        if ($recent >= self::RECURRENCE_THRESHOLD) {
            $level++;
            $factors[] = sprintf(
                '%d incidencias iguales en esta aula en los últimos %d días (+1)',
                $recent,
                self::RECURRENCE_WINDOW_DAYS
            );
        }

        $level = max(1, min(4, $level));

        return new PriorityResult(IncidentPriority::fromLevel($level), $factors);
    }

    private function baseLevel(?IncidentCategory $category): int
    {
        if ($category?->default_priority_id === null) {
            return IncidentPriority::Medium->level();
        }

        $level = DB::table('incident_priorities')
            ->where('id', $category->default_priority_id)
            ->value('level');

        return $level !== null ? (int) $level : IncidentPriority::Medium->level();
    }

    /**
     * Incidencias reales previas de la misma aula y categoria.
     * Excluye borradores: un docente que entro y no completo no es una
     * incidencia y no debe inflar la prioridad de las siguientes.
     */
    private function recentSimilarCount(Room $room, ?IncidentCategory $category): int
    {
        if ($category === null) {
            return 0;
        }

        return Incident::query()
            ->where('room_id', $room->id)
            ->where('category_id', $category->id)
            ->where('is_draft', false)
            ->where('created_at', '>=', now()->subDays(self::RECURRENCE_WINDOW_DAYS))
            ->count();
    }
}
