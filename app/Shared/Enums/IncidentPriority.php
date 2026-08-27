<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * Prioridades. La calcula SIEMPRE el sistema (plan 16.4).
 *
 * El docente aporta una senal ("impide continuar la clase"), nunca fija la
 * prioridad. Si pudiera, todo llegaria marcado como urgente y el campo
 * dejaria de servir para ordenar el trabajo de soporte.
 */
enum IncidentPriority: string
{
    case Low = 'LOW';
    case Medium = 'MEDIUM';
    case High = 'HIGH';
    case Critical = 'CRITICAL';

    /** Mayor = mas urgente. Permite comparar sin depender del texto. */
    public function level(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
            self::Critical => 4,
        };
    }

    public static function fromLevel(int $level): self
    {
        return match (true) {
            $level >= 4 => self::Critical,
            $level === 3 => self::High,
            $level === 2 => self::Medium,
            default => self::Low,
        };
    }
}
