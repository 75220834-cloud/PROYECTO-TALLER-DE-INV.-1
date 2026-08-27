<?php

declare(strict_types=1);

namespace App\Modules\Diagnostics\Engine;

use App\Modules\Diagnostics\Models\DiagnosticStep;

/**
 * Resultado de responder un paso del diagnostico.
 *
 * Cuatro desenlaces posibles y ninguno mas. Modelarlos como un objeto en
 * lugar de devolver el siguiente paso "o null, o false, o un string" evita
 * que quien llame tenga que adivinar que significa cada valor.
 */
final readonly class StepOutcome
{
    private function __construct(
        public string $type,
        public ?DiagnosticStep $nextStep = null,
    ) {}

    /** Hay otro paso que mostrar. */
    public static function next(DiagnosticStep $step): self
    {
        return new self('next', $step);
    }

    /** El arbol declara que aqui el problema queda resuelto. */
    public static function resolved(): self
    {
        return new self('resolved');
    }

    /** Se acabaron los pasos conocidos: toca soporte presencial. */
    public static function escalate(): self
    {
        return new self('escalate');
    }

    /** La respuesta enviada no es una de las opciones del paso. */
    public static function invalid(): self
    {
        return new self('invalid');
    }

    public function hasNext(): bool
    {
        return $this->type === 'next';
    }

    public function isResolved(): bool
    {
        return $this->type === 'resolved';
    }

    public function shouldEscalate(): bool
    {
        return $this->type === 'escalate';
    }

    public function isInvalid(): bool
    {
        return $this->type === 'invalid';
    }
}
