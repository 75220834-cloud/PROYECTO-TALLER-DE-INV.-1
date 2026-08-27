<?php

declare(strict_types=1);

namespace App\Modules\Risk\Features;

use Illuminate\Support\Carbon;

/**
 * Las caracteristicas de una observacion (aula, categoria, fecha de corte).
 *
 * Es inmutable y lleva su propia fecha de corte dentro. No es un detalle
 * decorativo: la fuga temporal se comete al mezclar un vector de
 * caracteristicas con una ventana de tiempo que no le corresponde, y
 * llevando el corte pegado al dato esa confusion se vuelve visible.
 */
final class FeatureSet
{
    /**
     * @param  int|null  $daysSinceLast  null = nunca hubo una incidencia antes del corte.
     * @param  float|null  $trendRatio  null = no habia periodo anterior con el que comparar.
     * @param  int|null  $daysSinceMaintenance  null = sin mantenimiento registrado.
     */
    public function __construct(
        public readonly int $roomId,
        public readonly ?int $categoryId,
        public readonly Carbon $cutoff,
        public readonly int $incidents7d,
        public readonly int $incidents30d,
        public readonly int $incidents90d,
        public readonly int $incidentsTotal,
        public readonly ?int $daysSinceLast,
        public readonly ?float $trendRatio,
        public readonly ?int $daysSinceMaintenance,
        public readonly int $criticality,
        public readonly int $equipmentCount,
        public readonly ?float $assistantResolutionRatio,
    ) {}

    /**
     * Representacion plana, para persistir junto al score y para exportar el
     * conjunto de datos al analisis estadistico externo.
     *
     * @return array<string, int|float|string|null>
     */
    public function toArray(): array
    {
        return [
            'room_id' => $this->roomId,
            'category_id' => $this->categoryId,
            'cutoff' => $this->cutoff->toDateString(),
            'incidents_7d' => $this->incidents7d,
            'incidents_30d' => $this->incidents30d,
            'incidents_90d' => $this->incidents90d,
            'incidents_total' => $this->incidentsTotal,
            'days_since_last' => $this->daysSinceLast,
            'trend_ratio' => $this->trendRatio,
            'days_since_maintenance' => $this->daysSinceMaintenance,
            'criticality' => $this->criticality,
            'equipment_count' => $this->equipmentCount,
            'assistant_resolution_ratio' => $this->assistantResolutionRatio,
        ];
    }
}
