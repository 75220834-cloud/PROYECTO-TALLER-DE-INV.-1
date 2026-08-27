<?php

declare(strict_types=1);

namespace App\Modules\Risk\Services;

use App\Modules\Risk\Contracts\RiskModel;
use App\Modules\Risk\Features\FeatureSet;

/**
 * Nivel N0 del plan (15.2): baseline explicable por reglas.
 *
 * QUE ES Y QUE NO ES ESTE NUMERO — leer antes de citarlo en el informe.
 *
 * El score NO es una probabilidad. Nadie lo ha calibrado contra datos
 * observados, porque durante el piloto no habra volumen suficiente para
 * hacerlo (plan 15.2, riesgo R1). Es un ORDENAMIENTO: sirve para responder
 * "¿que aulas conviene revisar primero?", no para afirmar "esta aula tiene
 * un 78 % de probabilidad de fallar". Presentarlo como lo segundo seria
 * inventar precision que no se tiene.
 *
 * Por que aun asi vale la pena: esta disponible desde la primera incidencia
 * registrada, cada termino se explica en una frase que un tecnico entiende,
 * y da la linea base contra la que habra que comparar cualquier modelo
 * supervisado. Un modelo que no supere a esto no se adopta (plan 17.7).
 *
 * FORMA DE LA FORMULA. Suma de terminos acotados, no producto ni exponencial.
 * Se eligio asi porque la contribucion de cada termino es directamente el
 * numero que se le muestra al tecnico: no hay que derivar nada ni repartir
 * atribuciones. El tope de cada termino evita que una sola señal —tipicamente
 * un aula con muchas incidencias antiguas— sature el score por si sola.
 *
 * Los pesos son juicio de ingenieria, no ajuste estadistico, y se declaran
 * como tales. Cambiarlos obliga a subir la version: dos numeros calculados
 * con pesos distintos no son comparables entre si.
 */
final class BaselineRecencyModel implements RiskModel
{
    public function code(): string
    {
        return 'baseline-recency';
    }

    public function version(): string
    {
        return '1.0';
    }

    public function predict(FeatureSet $f): RiskPrediction
    {
        $factors = [];

        // 1. Frecuencia reciente. El termino de mas peso: un aula que falla
        //    a menudo es el mejor predictor disponible de que volvera a fallar.
        if ($f->incidents30d > 0) {
            $factors[] = [
                'label' => 'Incidencias en los últimos 30 días',
                'detail' => $f->incidents30d.' incidencia'.($f->incidents30d === 1 ? '' : 's'),
                'contribution' => min(0.40, 0.10 * $f->incidents30d),
            ];
        }

        // 2. Racha. Se cuenta aparte de la frecuencia porque tres fallos esta
        //    semana no significan lo mismo que tres repartidos en un mes.
        if ($f->incidents7d > 0) {
            $factors[] = [
                'label' => 'Concentradas en los últimos 7 días',
                'detail' => $f->incidents7d.' incidencia'.($f->incidents7d === 1 ? '' : 's'),
                'contribution' => min(0.25, 0.08 * $f->incidents7d),
            ];
        }

        // 3. Tendencia. Solo cuenta si empeora de forma clara; una variacion
        //    pequeña entre dos periodos cortos es ruido, no tendencia.
        if ($f->trendRatio !== null && $f->trendRatio >= 1.5) {
            $increase = (int) round(($f->trendRatio - 1) * 100);
            $factors[] = [
                'label' => 'Frecuencia en aumento',
                'detail' => '+'.$increase.' % respecto al periodo anterior',
                'contribution' => min(0.15, 0.05 * ($f->trendRatio - 1)),
            ];
        }

        // 4. Recencia. Independiente del conteo: una sola incidencia anteayer
        //    dice mas sobre el estado actual del aula que cinco hace dos meses.
        $recency = $this->recencyContribution($f->daysSinceLast);
        if ($recency !== null) {
            $factors[] = $recency;
        }

        // 5. Mantenimiento. Contribucion pequeña a proposito: el registro de
        //    mantenimiento es el dato mas incompleto de todos, y no se debe
        //    penalizar a un aula por una laguna del inventario.
        $maintenance = $this->maintenanceContribution($f->daysSinceMaintenance);
        if ($maintenance !== null) {
            $factors[] = $maintenance;
        }

        // 6. Criticidad del aula. No predice averias: pondera consecuencias.
        //    Un auditorio y un aula pequeña con la misma señal no merecen la
        //    misma urgencia de revision.
        if ($f->criticality >= 2) {
            $factors[] = [
                'label' => 'Aula de uso crítico',
                'detail' => 'Nivel de criticidad '.$f->criticality,
                'contribution' => $f->criticality >= 3 ? 0.05 : 0.02,
            ];
        }

        $score = round(min(1.0, array_sum(array_column($factors, 'contribution'))), 4);

        // Sin señales, el riesgo no es "desconocido": es bajo, y se dice por
        // que. Una tarjeta vacia se lee como un fallo del sistema.
        if ($factors === []) {
            $factors[] = [
                'label' => 'Sin incidencias registradas',
                'detail' => 'No hay historial previo a la fecha de cálculo',
                'contribution' => 0.0,
            ];
        }

        return new RiskPrediction($score, $this->band($score), $factors);
    }

    /** @return array{label: string, detail: string, contribution: float}|null */
    private function recencyContribution(?int $days): ?array
    {
        if ($days === null) {
            return null;
        }

        $contribution = match (true) {
            $days <= 3 => 0.10,
            $days <= 7 => 0.06,
            $days <= 14 => 0.03,
            default => 0.0,
        };

        if ($contribution === 0.0) {
            return null;
        }

        return [
            'label' => 'Incidencia muy reciente',
            'detail' => $days === 0 ? 'Hoy mismo' : 'Hace '.$days.' día'.($days === 1 ? '' : 's'),
            'contribution' => $contribution,
        ];
    }

    /** @return array{label: string, detail: string, contribution: float}|null */
    private function maintenanceContribution(?int $days): ?array
    {
        if ($days === null) {
            return [
                'label' => 'Sin mantenimiento registrado',
                'detail' => 'No hay ningún registro para los equipos de esta aula',
                'contribution' => 0.05,
            ];
        }

        if ($days <= 90) {
            return null;
        }

        return [
            'label' => 'Mantenimiento antiguo',
            'detail' => 'Último registro hace '.$days.' días',
            'contribution' => 0.05,
        ];
    }

    private function band(float $score): string
    {
        /** @var array{low: float, medium: float} $bands */
        $bands = config('incidencias.risk.bands');

        return match (true) {
            $score < $bands['low'] => 'low',
            $score < $bands['medium'] => 'medium',
            default => 'high',
        };
    }
}
