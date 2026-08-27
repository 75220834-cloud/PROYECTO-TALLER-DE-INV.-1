<?php

declare(strict_types=1);

use App\Modules\Incidents\Exceptions\InvalidTransitionException;
use App\Modules\Incidents\StateMachine\IncidentStateMachine;
use App\Shared\Enums\IncidentStatus as S;

/**
 * Maquina de estados (plan 7.2, 17.1).
 *
 * Logica pura, sin base de datos. Se prueban las transiciones permitidas y,
 * sobre todo, que las PROHIBIDAS fallen: si los estados pudieran cambiar en
 * cualquier orden, las marcas de tiempo dejarian de ser interpretables y
 * los indicadores de la investigacion medirian ruido.
 */
beforeEach(function () {
    $this->machine = new IncidentStateMachine;
});

// -------------------------------------------------- transiciones validas

it('permite el camino feliz del docente que resuelve solo', function () {
    expect($this->machine->canTransition(S::Draft, S::Diagnosing))->toBeTrue()
        ->and($this->machine->canTransition(S::Diagnosing, S::Resolved))->toBeTrue()
        ->and($this->machine->canTransition(S::Resolved, S::Closed))->toBeTrue();
});

it('permite el camino de escalamiento a soporte', function () {
    expect($this->machine->canTransition(S::Diagnosing, S::New))->toBeTrue()
        ->and($this->machine->canTransition(S::New, S::InProgress))->toBeTrue()
        ->and($this->machine->canTransition(S::InProgress, S::Resolved))->toBeTrue()
        ->and($this->machine->canTransition(S::Resolved, S::Closed))->toBeTrue();
});

it('permite escalar a un tercero y volver', function () {
    expect($this->machine->canTransition(S::InProgress, S::Escalated))->toBeTrue()
        ->and($this->machine->canTransition(S::Escalated, S::InProgress))->toBeTrue()
        ->and($this->machine->canTransition(S::Escalated, S::Resolved))->toBeTrue();
});

// ------------------------------------------------ transiciones invalidas

it('IMPIDE saltarse el diagnostico y crear un ticket directo', function () {
    expect($this->machine->canTransition(S::Draft, S::New))->toBeFalse();
});

it('IMPIDE resolver un borrador sin pasar por el diagnostico', function () {
    expect($this->machine->canTransition(S::Draft, S::Resolved))->toBeFalse();
});

it('IMPIDE que una incidencia cerrada vuelva al diagnostico', function () {
    expect($this->machine->canTransition(S::Closed, S::Diagnosing))->toBeFalse();
});

it('IMPIDE resucitar una incidencia cancelada', function () {
    // Cancelada es definitiva. Si se pudiera revivir, el historial dejaria
    // de reflejar lo que realmente paso.
    expect($this->machine->allowedFrom(S::Cancelled))->toBe([])
        ->and($this->machine->canTransition(S::Cancelled, S::New))->toBeFalse()
        ->and($this->machine->canTransition(S::Cancelled, S::InProgress))->toBeFalse();
});

it('IMPIDE cerrar sin resolver antes', function () {
    expect($this->machine->canTransition(S::InProgress, S::Closed))->toBeFalse()
        ->and($this->machine->canTransition(S::New, S::Closed))->toBeFalse();
});

it('IMPIDE asignar un ticket que todavia es borrador', function () {
    expect($this->machine->canTransition(S::Draft, S::InProgress))->toBeFalse();
});

// ----------------------------------------------------------- excepciones

it('lanza excepcion al intentar una transicion prohibida', function () {
    $this->machine->assertCanTransition(S::Closed, S::Diagnosing);
})->throws(InvalidTransitionException::class);

it('lanza excepcion al transicionar al mismo estado', function () {
    $this->machine->assertCanTransition(S::InProgress, S::InProgress);
})->throws(InvalidTransitionException::class);

it('el mensaje de error dice QUE estados si eran posibles', function () {
    // Un "transicion invalida" a secas obliga a abrir la maquina de estados
    // para entender que se podia hacer.
    try {
        $this->machine->assertCanTransition(S::New, S::Closed);
        $this->fail('Debería haber lanzado la excepción.');
    } catch (InvalidTransitionException $e) {
        expect($e->getMessage())
            ->toContain('IN_PROGRESS')
            ->toContain('CANCELLED');
    }
});

it('avisa cuando el estado de origen es final', function () {
    try {
        $this->machine->assertCanTransition(S::Cancelled, S::New);
        $this->fail('Debería haber lanzado la excepción.');
    } catch (InvalidTransitionException $e) {
        expect($e->getMessage())->toContain('estado final');
    }
});

// ------------------------------------------------------------ reapertura

it('permite reabrir una incidencia resuelta', function () {
    expect($this->machine->canReopen(S::Resolved, null, 7))->toBeTrue();
});

it('permite reabrir una cerrada dentro de la ventana', function () {
    expect($this->machine->canReopen(S::Closed, now()->subDays(3), 7))->toBeTrue();
});

it('IMPIDE reabrir una cerrada fuera de la ventana', function () {
    // Pasada la ventana, un problema "igual" casi siempre es uno nuevo.
    // Reabrir el viejo falsearia el tiempo de resolucion (se contaria desde
    // el reporte original) y ocultaria una recurrencia que el modulo de
    // riesgo necesita ver como DOS eventos, no como uno.
    expect($this->machine->canReopen(S::Closed, now()->subDays(30), 7))->toBeFalse();
});

it('IMPIDE reabrir algo que nunca se resolvio', function () {
    expect($this->machine->canReopen(S::InProgress, null, 7))->toBeFalse()
        ->and($this->machine->canReopen(S::Cancelled, null, 7))->toBeFalse();
});

// ------------------------------------------------------------ cobertura

it('declara transiciones para TODOS los estados del enum', function () {
    // Si manana se anade un estado y se olvida declarar sus transiciones,
    // esta prueba falla en lugar de que el sistema reviente en produccion.
    foreach (S::cases() as $state) {
        expect(fn () => $this->machine->allowedFrom($state))->not->toThrow(Throwable::class);
    }
});
