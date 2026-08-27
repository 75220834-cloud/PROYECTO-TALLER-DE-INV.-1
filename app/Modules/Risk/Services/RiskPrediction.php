<?php

declare(strict_types=1);

namespace App\Modules\Risk\Services;

/**
 * Resultado de una estimacion: el numero y su explicacion, inseparables.
 *
 * Van juntos en un solo objeto a proposito. Si el score y los factores
 * viajaran por separado seria posible persistir uno sin el otro, y el plan
 * (15.5) prohibe exactamente eso.
 */
final class RiskPrediction
{
    /**
     * @param  float  $score  0..1. NO es una probabilidad calibrada — ver BaselineRecencyModel.
     * @param  list<array{label: string, detail: string, contribution: float}>  $factors
     */
    public function __construct(
        public readonly float $score,
        public readonly string $band,
        public readonly array $factors,
    ) {}
}
