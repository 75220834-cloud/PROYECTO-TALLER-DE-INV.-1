<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Providers;

use App\Modules\Assistant\Contracts\Classification;
use App\Modules\Assistant\Contracts\LlmProvider;

/**
 * Sin modelo de lenguaje. Modo degradado EXPLICITO.
 *
 * No es un apano ni un caso de error: es un modo de operacion legitimo y
 * soportado. Con este proveedor el sistema conserva TODA su funcionalidad
 * —reportar, diagnosticar paso a paso, confirmar, escalar, gestionar el
 * ticket—; lo unico que se pierde es la comodidad de clasificar texto libre
 * y reformular instrucciones.
 *
 * Que esto siga siendo cierto es un criterio de aceptacion del plan, y hay
 * pruebas que lo comprueban.
 */
final class NullLlmProvider implements LlmProvider
{
    public function classify(string $text, array $allowedLabels): Classification
    {
        // Sin clasificacion: el docente elige la categoria con los botones,
        // que es el camino principal de todos modos.
        return Classification::unknown('null');
    }

    public function rephrase(string $text, string $context = ''): ?string
    {
        // El texto original del paso siempre es valido por si mismo.
        return null;
    }

    public function answerGrounded(string $question, array $passages): ?string
    {
        // Sin modelo no hay generacion: quien llama muestra los pasajes
        // recuperados tal cual, con su cita. Es menos comodo de leer y
        // exactamente igual de fiable.
        return null;
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function identifier(): string
    {
        return 'null';
    }
}
