<?php

declare(strict_types=1);

namespace App\Modules\Risk\Contracts;

use App\Modules\Risk\Features\FeatureSet;
use App\Modules\Risk\Services\RiskPrediction;

/**
 * Un metodo de estimacion de riesgo (plan 15.2).
 *
 * `predict()` devuelve SIEMPRE los factores que produjeron el numero. No es
 * una comodidad de la interfaz: un modelo que no pueda explicarse en estos
 * terminos no se adopta en este proyecto, por preciso que sea. Soporte
 * ignora —con razon— un numero que nadie sabe justificar, y un modulo que se
 * ignora no sirve de nada (plan 15.5).
 */
interface RiskModel
{
    /** Identificador estable del metodo, p. ej. `baseline-recency`. */
    public function code(): string;

    /** Version del metodo. Dos scores de versiones distintas no son comparables. */
    public function version(): string;

    public function predict(FeatureSet $features): RiskPrediction;
}
