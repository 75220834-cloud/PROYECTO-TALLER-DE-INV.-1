<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Services;

/**
 * Resultado de evaluar la confianza, con sus tres componentes por separado.
 *
 * Guardar el desglose y no solo el número final es lo que permite
 * RECALIBRAR después con datos reales: si el sistema escala demasiado, hay
 * que poder saber si es por recuperación pobre, por clasificación dudosa o
 * por falta de citas. Con un único número eso es indistinguible.
 */
final readonly class ConfidenceVerdict
{
    public function __construct(
        public float $confidence,
        public string $band,
        public float $retrievalScore,
        public float $classificationScore,
        public float $coverageScore,
    ) {}

    public function canAnswer(): bool
    {
        return $this->band !== 'low';
    }

    public function shouldEscalate(): bool
    {
        return $this->band === 'low';
    }

    /** Responde, pero avisando de que puede no ser exacto. */
    public function needsWarning(): bool
    {
        return $this->band === 'medium';
    }

    /**
     * Mensaje literal que exige el plan cuando no hay confianza suficiente
     * (§44). No se parafrasea: es el compromiso explícito de que el sistema
     * reconoce sus límites en lugar de improvisar.
     */
    public function escalationMessage(): string
    {
        return 'No tengo suficiente información para resolver esta incidencia de forma segura. '
            .'Solicitaré soporte técnico.';
    }

    /** @return array<string, float|string> */
    public function toArray(): array
    {
        return [
            'confidence' => $this->confidence,
            'band' => $this->band,
            'retrieval' => $this->retrievalScore,
            'classification' => $this->classificationScore,
            'coverage' => $this->coverageScore,
        ];
    }
}
