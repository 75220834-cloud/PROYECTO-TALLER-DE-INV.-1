<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Contracts;

/**
 * Resultado de clasificar un texto libre.
 *
 * Lleva la confianza SIEMPRE. Un clasificador que solo devuelve la etiqueta
 * obliga a tratar por igual "estoy seguro" y "no tengo ni idea", y eso es
 * justo lo que el plan prohibe: ante poca confianza hay que preguntar o
 * escalar, no adivinar (plan 13.5).
 */
final readonly class Classification
{
    /**
     * @param  list<string>  $alternatives  otras etiquetas consideradas
     */
    public function __construct(
        public ?string $label,
        public float $confidence,
        public array $alternatives = [],
        public string $method = 'llm',
    ) {}

    public static function unknown(string $method = 'llm'): self
    {
        return new self(null, 0.0, [], $method);
    }

    public function isConfident(float $threshold): bool
    {
        return $this->label !== null && $this->confidence >= $threshold;
    }

    /** Ambigua: hay una etiqueta, pero no lo bastante clara para asumirla. */
    public function isAmbiguous(float $threshold): bool
    {
        return $this->label !== null && $this->confidence < $threshold;
    }
}
