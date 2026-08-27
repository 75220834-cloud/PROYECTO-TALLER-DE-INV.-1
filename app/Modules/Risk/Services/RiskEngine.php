<?php

declare(strict_types=1);

namespace App\Modules\Risk\Services;

use App\Modules\Risk\Contracts\RiskModel;
use App\Modules\Risk\Features\FeatureBuilder;
use App\Modules\Risk\Models\RiskScore;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Calcula y persiste las señales de riesgo (plan 15).
 *
 * QUE PARES SE CALCULAN Y POR QUE
 *
 * - Un score por AULA activa, siempre. Es la vista que soporte necesita para
 *   decidir por donde empezar una ronda de revision.
 * - Un score por AULA+CATEGORIA solo para los pares CON historial. Calcular
 *   el producto cartesiano completo generaria cientos de filas con score 0
 *   que no dicen nada y que enterrarian las pocas que si importan.
 *
 * Los scores anteriores NO se borran. La serie historica es lo unico que
 * permitira contrastar despues lo previsto contra lo ocurrido; sin ella el
 * modulo no seria evaluable y la seccion de resultados no tendria nada que
 * reportar.
 */
final class RiskEngine
{
    public function __construct(
        private readonly FeatureBuilder $features,
        private readonly RiskModel $model,
    ) {}

    /**
     * @return int Cantidad de scores persistidos.
     */
    public function computeAll(?Carbon $at = null): int
    {
        $at ??= now();
        $horizon = (int) config('incidencias.risk.horizon_days');
        $written = 0;

        /** @var list<int> $roomIds */
        $roomIds = DB::table('rooms')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->all();

        foreach ($roomIds as $roomId) {
            $this->persist('room', (int) $roomId, null, $at, $horizon);
            $written++;
        }

        /** @var list<object{room_id: int, category_id: int}> $pairs */
        $pairs = DB::table('incidents')
            ->select('room_id', 'category_id')
            ->where('is_draft', false)
            ->whereNotNull('category_id')
            ->whereNotNull('reported_at')
            ->where('reported_at', '<', $at)
            // Solo el pasado reciente: un par que no falla desde hace medio
            // año no es una señal, es ruido historico.
            ->where('reported_at', '>=', $at->copy()->subDays(90))
            ->whereIn('room_id', $roomIds)
            ->groupBy('room_id', 'category_id')
            ->get()
            ->all();

        foreach ($pairs as $pair) {
            $this->persist('room_category', (int) $pair->room_id, (int) $pair->category_id, $at, $horizon);
            $written++;
        }

        return $written;
    }

    public function persist(string $scopeType, int $roomId, ?int $categoryId, Carbon $at, int $horizon): RiskScore
    {
        $features = $this->features->build($roomId, $categoryId, $at);
        $prediction = $this->model->predict($features);

        return RiskScore::query()->create([
            'scope_type' => $scopeType,
            'scope_id' => $roomId,
            'category_id' => $categoryId,
            'horizon_days' => $horizon,
            'score' => $prediction->score,
            'risk_band' => $prediction->band,
            // El plan (15.5) prohibe persistir un score sin explicacion. El
            // modelo garantiza que `factors` nunca viene vacio; la columna es
            // NOT NULL para que un incumplimiento reviente aqui y no pase
            // inadvertido hasta que alguien mire el tablero.
            'factors' => $prediction->factors,
            'model_code' => $this->model->code(),
            'model_version' => $this->model->version(),
            'computed_at' => $at,
        ]);
    }

    /**
     * Los scores vigentes, uno por ambito, ordenados por riesgo.
     *
     * @return Collection<int, RiskScore>
     */
    public function current(string $scopeType = 'room', int $limit = 10)
    {
        // El ultimo calculo de cada ambito: se toma el id maximo por ambito y
        // despues se ordena por score. Hacerlo al reves mezclaria calculos de
        // fechas distintas en la misma lista.
        $latestIds = RiskScore::query()
            ->where('scope_type', $scopeType)
            ->selectRaw('MAX(id) as id')
            ->groupBy('scope_id', 'category_id')
            ->pluck('id');

        return RiskScore::query()
            ->whereIn('id', $latestIds)
            ->orderByDesc('score')
            ->orderBy('scope_id')
            ->limit($limit)
            ->get();
    }
}
