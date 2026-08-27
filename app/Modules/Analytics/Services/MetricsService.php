<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Services;

use App\Shared\Enums\ResolutionType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Indicadores operativos del sistema (plan 15 y 26.bis).
 *
 * DOS REGLAS QUE ATRAVIESAN TODA LA CLASE
 *
 * 1. Los BORRADORES no cuentan como incidencias. Un docente que confirmó
 *    su aula y se fue sin reportar nada no es una incidencia: incluirlo
 *    inflaría los totales y hundiría artificialmente los porcentajes de
 *    resolución. Se cuentan aparte, como indicador de fricción.
 *
 * 2. El tiempo de resolución se mide desde que el docente CONFIRMÓ LA
 *    UBICACIÓN, no desde que se creó el ticket. Medir desde el ticket
 *    descontaría todo el rato que el docente pasó en el diagnóstico y haría
 *    que el sistema pareciera mejor de lo que es. Es la diferencia entre
 *    medir el proceso y medir solo la parte que nos favorece.
 */
final class MetricsService
{
    /**
     * Resumen para el tablero.
     *
     * @return array<string, mixed>
     */
    public function dashboard(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from ??= now()->subDays(30)->startOfDay();
        $to ??= now()->endOfDay();

        return [
            'period' => ['from' => $from, 'to' => $to],
            'volume' => $this->volume($from, $to),
            'resolution' => $this->resolution($from, $to),
            'times' => $this->times($from, $to),
            'friction' => $this->friction($from, $to),
            'channel' => $this->channelIntegrity($from, $to),
            'topRooms' => $this->topRooms($from, $to),
            'topCategories' => $this->topCategories($from, $to),
            'visualAid' => $this->visualAidEffect($from, $to),
            'satisfaction' => $this->satisfaction($from, $to),
        ];
    }

    /** @return array<string, int> */
    public function volume(Carbon $from, Carbon $to): array
    {
        $base = fn () => DB::table('incidents')
            ->where('is_draft', false)
            ->whereBetween('incidents.created_at', [$from, $to]);

        $byPriority = $base()
            ->leftJoin('incident_priorities as p', 'p.id', '=', 'incidents.priority_id')
            ->selectRaw('p.code, COUNT(*) as total')
            ->groupBy('p.code')
            ->pluck('total', 'code');

        $open = $base()
            ->join('incident_statuses as s', 's.id', '=', 'incidents.status_id')
            ->where('s.is_open', true)
            ->count();

        return [
            'total' => $base()->count(),
            'open' => $open,
            'blocking' => $base()->where('blocks_class', true)->count(),
            'critical' => (int) ($byPriority['CRITICAL'] ?? 0),
            'high' => (int) ($byPriority['HIGH'] ?? 0),
            'medium' => (int) ($byPriority['MEDIUM'] ?? 0),
            'low' => (int) ($byPriority['LOW'] ?? 0),
        ];
    }

    /**
     * Autonomía de resolución: el indicador central de la investigación.
     *
     * @return array<string, int|float>
     */
    public function resolution(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('incidents')
            ->where('is_draft', false)
            ->whereNotNull('resolution_type')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('resolution_type, COUNT(*) as total')
            ->groupBy('resolution_type')
            ->pluck('total', 'resolution_type');

        $assistant = (int) ($rows[ResolutionType::Assistant->value] ?? 0);
        $onsite = (int) ($rows[ResolutionType::Onsite->value] ?? 0);
        $remote = (int) ($rows[ResolutionType::Remote->value] ?? 0);
        $resolved = $assistant + $onsite + $remote;

        return [
            'resolved' => $resolved,
            'byAssistant' => $assistant,
            'onsite' => $onsite,
            'remote' => $remote,

            // Lo que el proyecto pretende mover.
            'autonomyRate' => $resolved > 0 ? round($assistant / $resolved * 100, 1) : 0.0,

            // Desplazamientos evitados: resueltas sin que nadie caminara.
            'tripsAvoided' => $assistant + $remote,
        ];
    }

    /**
     * Tiempos, en minutos y como MEDIANA.
     *
     * Mediana y no media a propósito: una sola incidencia que se quedó
     * abierta un fin de semana desplaza la media varias horas y hace
     * ilegible el indicador. La mediana describe el caso típico, que es lo
     * que interesa comparar.
     *
     * @return array<string, float|null>
     */
    public function times(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('incidents')
            ->where('is_draft', false)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('
                TIMESTAMPDIFF(MINUTE, reported_at, first_response_at) AS to_first_response,
                TIMESTAMPDIFF(MINUTE, confirmed_at, resolved_at) AS to_resolution
            ')
            ->get();

        return [
            'medianFirstResponse' => $this->median($rows->pluck('to_first_response')),
            'medianResolution' => $this->median($rows->pluck('to_resolution')),
        ];
    }

    /**
     * Fricción de acceso: cuántos entran y no llegan a reportar.
     *
     * Es el indicador que mide el coste del QR genérico (riesgo R19 del
     * plan). Si sale alto, es un hallazgo legítimo de la investigación y se
     * reporta; no es algo que convenga esconder.
     *
     * @return array<string, int|float>
     */
    public function friction(Carbon $from, Carbon $to): array
    {
        $drafts = DB::table('incidents')
            ->where('is_draft', true)
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $reported = DB::table('incidents')
            ->where('is_draft', false)
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $started = $drafts + $reported;

        return [
            'abandoned' => $drafts,
            'completed' => $reported,
            'abandonmentRate' => $started > 0 ? round($drafts / $started * 100, 1) : 0.0,
        ];
    }

    /**
     * Rechazos del antiabuso, por motivo.
     *
     * Se muestran SIEMPRE, incluso cuando son cero. Si aparecen rechazos de
     * tipo ip_rate hay que revisar los umbrales: probablemente se esté
     * bloqueando a docentes legítimos que comparten la WiFi (riesgo R18).
     *
     * @return array<string, int>
     */
    public function channelIntegrity(Carbon $from, Carbon $to): array
    {
        return DB::table('incident_abuse_rejections')
            ->whereBetween('occurred_at', [$from, $to])
            ->selectRaw('reason, COUNT(*) as total')
            ->groupBy('reason')
            ->pluck('total', 'reason')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /** @return Collection<int, \stdClass> */
    public function topRooms(Carbon $from, Carbon $to, int $limit = 5): Collection
    {
        return DB::table('incidents')
            ->join('rooms', 'rooms.id', '=', 'incidents.room_id')
            ->where('incidents.is_draft', false)
            ->whereBetween('incidents.created_at', [$from, $to])
            ->selectRaw('rooms.code, COUNT(*) as total')
            ->groupBy('rooms.code')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();
    }

    /** @return Collection<int, \stdClass> */
    public function topCategories(Carbon $from, Carbon $to, int $limit = 5): Collection
    {
        return DB::table('incidents')
            ->join('incident_categories as c', 'c.id', '=', 'incidents.category_id')
            ->where('incidents.is_draft', false)
            ->whereBetween('incidents.created_at', [$from, $to])
            ->selectRaw('c.name, COUNT(*) as total')
            ->groupBy('c.name')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();
    }

    /**
     * ¿Ayudan las imágenes?
     *
     * Compara la proporción de pasos completados con y sin apoyo visual.
     * Convierte una decisión de diseño en una observación medible, en lugar
     * de una afirmación de opinión (plan 13.6).
     *
     * @return array<string, int|float|null>
     */
    public function visualAidEffect(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('diagnostic_answers')
            ->whereBetween('answered_at', [$from, $to])
            ->selectRaw('
                SUM(CASE WHEN media_shown IS NOT NULL THEN 1 ELSE 0 END) AS con_imagen,
                SUM(CASE WHEN media_shown IS NULL THEN 1 ELSE 0 END) AS sin_imagen,
                AVG(CASE WHEN media_shown IS NOT NULL THEN duration_ms END) AS ms_con,
                AVG(CASE WHEN media_shown IS NULL THEN duration_ms END) AS ms_sin
            ')
            ->first();

        return [
            'stepsWithImage' => (int) ($rows->con_imagen ?? 0),
            'stepsWithoutImage' => (int) ($rows->sin_imagen ?? 0),
            'avgSecondsWithImage' => $rows?->ms_con !== null ? round((float) $rows->ms_con / 1000, 1) : null,
            'avgSecondsWithoutImage' => $rows?->ms_sin !== null ? round((float) $rows->ms_sin / 1000, 1) : null,
        ];
    }

    /** @return array<string, int|float|null> */
    public function satisfaction(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('satisfaction_responses')
            ->whereBetween('answered_at', [$from, $to])
            ->selectRaw('COUNT(*) as total, AVG(ease_score) as promedio')
            ->first();

        return [
            'responses' => (int) ($rows->total ?? 0),
            'averageEase' => $rows?->promedio !== null ? round((float) $rows->promedio, 2) : null,
        ];
    }

    /**
     * @param  Collection<int, mixed>  $values
     */
    private function median(Collection $values): ?float
    {
        $clean = $values
            ->filter(fn ($v) => $v !== null)
            ->map(fn ($v) => (float) $v)
            ->sort()
            ->values();

        if ($clean->isEmpty()) {
            return null;
        }

        $count = $clean->count();
        $middle = (int) floor($count / 2);

        return $count % 2 === 0
            ? round(((float) $clean[$middle - 1] + (float) $clean[$middle]) / 2, 1)
            : round((float) $clean[$middle], 1);
    }
}
