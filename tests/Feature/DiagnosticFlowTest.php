<?php

declare(strict_types=1);

use App\Modules\Diagnostics\Engine\DiagnosticEngine;
use App\Modules\Diagnostics\Models\DiagnosticAnswer;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Incidents\Models\IncidentCategory;
use App\Modules\Locations\Models\Building;
use App\Modules\Locations\Models\Floor;
use App\Modules\Locations\Models\Room;
use App\Modules\Locations\Models\Site;
use App\Shared\Enums\IncidentStatus as S;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\DemoDiagnosticsSeeder;
use Database\Seeders\DemoMediaSeeder;
use Database\Seeders\RoleSeeder;

/**
 * Diagnostico guiado paso a paso (plan 11, 13.6 y 17).
 *
 * Se recorre el arbol como lo haria un docente, comprobando que el motor es
 * DETERMINISTA y que ningun camino deja al docente atrapado.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(CatalogSeeder::class);
    $this->seed(DemoMediaSeeder::class);
    $this->seed(DemoDiagnosticsSeeder::class);

    $this->engine = app(DiagnosticEngine::class);

    $this->site = Site::create(['code' => 'HYO', 'name' => 'Huancayo', 'is_active' => true]);
    $this->building = Building::create(['site_id' => $this->site->id, 'code' => 'C', 'name' => 'Pabellón C', 'is_active' => true]);
    $this->floor = Floor::create(['building_id' => $this->building->id, 'number' => 3, 'label' => 'Piso 3', 'is_active' => true]);
    $this->room = Room::create(['floor_id' => $this->floor->id, 'code' => 'C305', 'criticality' => 1, 'is_active' => true]);

    $this->projector = IncidentCategory::where('code', 'PROJECTOR')->firstOrFail();
});

/** Deja una incidencia lista en estado de diagnostico. */
function startDiagnosisFor(IncidentCategory $category): Incident
{
    test()->post(route('teacher.confirm.store'), [
        'site_id' => (string) test()->site->id,
        'building_id' => (string) test()->building->id,
        'floor_id' => (string) test()->floor->id,
        'room_id' => (string) test()->room->id,
    ]);

    test()->post(route('teacher.category.store'), ['category_id' => $category->id]);

    return Incident::latest('id')->first();
}

// ------------------------------------------------------------- el motor

it('empieza por el primer paso del arbol publicado', function () {
    $incident = startDiagnosisFor($this->projector);
    $version = $this->engine->flowFor($this->projector->id);

    expect($version)->not->toBeNull()
        ->and($this->engine->currentStep($incident, $version)->step_key)->toBe('projector_power');
});

it('avanza al paso que indica la respuesta', function () {
    $incident = startDiagnosisFor($this->projector);
    $version = $this->engine->flowFor($this->projector->id);
    $step = $this->engine->currentStep($incident, $version);

    // "luz verde" salta el encendido y va directo al cable.
    $outcome = $this->engine->answer($incident, $step, 'green');

    expect($outcome->hasNext())->toBeTrue()
        ->and($outcome->nextStep->step_key)->toBe('check_cable');
});

it('lleva por caminos distintos segun la respuesta', function () {
    // Es la esencia de un arbol de decision: si todas las respuestas
    // llevaran al mismo sitio, seria un cuestionario, no un diagnostico.
    $incident = startDiagnosisFor($this->projector);
    $version = $this->engine->flowFor($this->projector->id);
    $step = $version->stepByKey('projector_power');

    $otro = startDiagnosisFor($this->projector);

    $a = $this->engine->answer($incident, $step, 'green');
    $b = $this->engine->answer($otro, $step, 'none');

    expect($a->nextStep->step_key)->not->toBe($b->nextStep->step_key)
        ->and($b->nextStep->step_key)->toBe('check_power');
});

it('termina en RESUELTO cuando el arbol lo declara', function () {
    $incident = startDiagnosisFor($this->projector);
    $version = $this->engine->flowFor($this->projector->id);
    $final = $version->stepByKey('final_check');

    $outcome = $this->engine->answer($incident, $final, 'yes');

    expect($outcome->isResolved())->toBeTrue()
        ->and($outcome->shouldEscalate())->toBeFalse();
});

it('termina en ESCALAR cuando el arbol lo declara', function () {
    $incident = startDiagnosisFor($this->projector);
    $version = $this->engine->flowFor($this->projector->id);

    $outcome = $this->engine->answer($incident, $version->stepByKey('final_check'), 'no');

    expect($outcome->shouldEscalate())->toBeTrue();
});

it('NO muestra los pasos terminales como pantalla', function () {
    // Son marcadores de desenlace. Si se mostraran, el docente daría un
    // toque de más solo para enterarse de algo que el sistema ya sabe.
    $incident = startDiagnosisFor($this->projector);
    $version = $this->engine->flowFor($this->projector->id);

    $this->engine->answer($incident, $version->stepByKey('final_check'), 'yes');

    expect($this->engine->currentStep($incident, $version))->toBeNull();
});

it('escala cuando la respuesta no lleva a ningun sitio', function () {
    // Un mapa incompleto NUNCA deja al docente atrapado en una pantalla:
    // escalar es el desenlace conservador correcto.
    $incident = startDiagnosisFor($this->projector);
    $version = $this->engine->flowFor($this->projector->id);

    $step = $version->stepByKey('check_power');
    $step->update(['next_step_map' => []]);

    expect($this->engine->answer($incident, $step->fresh(), 'fixed')->shouldEscalate())->toBeTrue();
});

it('escala cuando el arbol apunta a un paso que no existe', function () {
    // Error de edicion del arbol. El docente no puede pagar por él.
    $incident = startDiagnosisFor($this->projector);
    $version = $this->engine->flowFor($this->projector->id);

    $step = $version->stepByKey('projector_power');
    $step->update(['next_step_map' => ['green' => 'PASO_QUE_NO_EXISTE']]);

    expect($this->engine->answer($incident, $step->fresh(), 'green')->shouldEscalate())->toBeTrue();
});

it('rechaza una respuesta que no es una de las opciones', function () {
    $incident = startDiagnosisFor($this->projector);
    $version = $this->engine->flowFor($this->projector->id);
    $step = $version->stepByKey('projector_power');

    $outcome = $this->engine->answer($incident, $step, 'respuesta_inventada');

    expect($outcome->isInvalid())->toBeTrue()
        // Y no deja rastro: una respuesta invalida no es dato.
        ->and(DiagnosticAnswer::count())->toBe(0);
});

it('registra cada respuesta con su duracion y las imagenes mostradas', function () {
    $incident = startDiagnosisFor($this->projector);
    $version = $this->engine->flowFor($this->projector->id);
    $step = $version->stepByKey('projector_power');

    $this->engine->answer($incident, $step, 'green', 4200, [7]);

    $answer = DiagnosticAnswer::first();

    expect($answer->answer_value)->toBe('green')
        ->and($answer->duration_ms)->toBe(4200)
        // Permite contrastar la resolucion autonoma con y sin apoyo visual.
        ->and($answer->media_shown)->toBe([7])
        ->and($answer->sawImage())->toBeTrue();
});

it('no hay arbol para una categoria sin procedimiento cargado', function () {
    // Null es un caso NORMAL, no un error: mientras soporte no cargue los
    // procedimientos, el sistema reporta y escala sin inventar pasos.
    $teclado = IncidentCategory::where('code', 'KEYBOARD')->firstOrFail();

    expect($this->engine->flowFor($teclado->id))->toBeNull();
});

// ------------------------------------------------------------ interfaz

it('muestra el primer paso con su imagen', function () {
    startDiagnosisFor($this->projector);

    $this->get(route('teacher.diagnostic'))
        ->assertOk()
        ->assertSee('¿El proyector tiene alguna luz encendida?')
        ->assertSee('Sí, luz verde o azul')
        ->assertSee('No estoy seguro');
});

it('salta el diagnostico si la categoria no tiene arbol', function () {
    $teclado = IncidentCategory::where('code', 'KEYBOARD')->firstOrFail();
    startDiagnosisFor($teclado);

    $this->get(route('teacher.diagnostic'))->assertRedirect(route('teacher.outcome'));
});

it('recorre el arbol completo hasta resolver', function () {
    startDiagnosisFor($this->projector);
    $version = $this->engine->flowFor($this->projector->id);

    $camino = [
        'projector_power' => 'green',
        'check_cable' => 'yes',
        'check_source' => 'done',
        'duplicate_screen' => 'done',
        'final_check' => 'yes',
    ];

    foreach ($camino as $key => $answer) {
        $step = $version->stepByKey($key);

        $this->post(route('teacher.diagnostic.answer'), [
            'step_id' => (string) $step->id,
            'answer' => $answer,
        ])->assertRedirect();
    }

    expect(DiagnosticAnswer::count())->toBe(count($camino));

    // Y aun asi se PREGUNTA antes de cerrar: el procedimiento puede dar por
    // bueno algo que en el aula no funciono (plan 12).
    $this->get(route('teacher.outcome'))
        ->assertOk()
        ->assertSee('¿El problema ya se solucionó?');
});

it('ofrece la ayuda "cual es esa pieza" cuando hay imagen del componente', function () {
    startDiagnosisFor($this->projector);
    $version = $this->engine->flowFor($this->projector->id);
    $step = $version->stepByKey('check_cable');

    // Se avanza hasta el paso del cable.
    $this->post(route('teacher.diagnostic.answer'), [
        'step_id' => (string) $version->stepByKey('projector_power')->id,
        'answer' => 'green',
    ]);

    $this->get(route('teacher.diagnostic'))
        ->assertOk()
        ->assertSee('¿Cuál es esa pieza?');

    $this->get(route('teacher.diagnostic.reference', ['componentKey' => $step->component_key]))
        ->assertOk()
        ->assertSee('HDMI');
});

it('el docente siempre puede salir a pedir soporte', function () {
    // Un docente con una clase esperando no puede quedar obligado a
    // terminar un cuestionario.
    startDiagnosisFor($this->projector);

    $this->get(route('teacher.diagnostic'))
        ->assertOk()
        ->assertSee('Prefiero pedir soporte ahora');
});

it('exige diagnostico en curso para responder', function () {
    $this->post(route('teacher.diagnostic.answer'), ['step_id' => '1', 'answer' => 'x'])
        ->assertRedirect(route('teacher.start'));
});

it('el escalamiento tras el diagnostico conserva los pasos ejecutados', function () {
    // Es el punto del sistema: soporte recibe QUE se intento ya (plan 45).
    startDiagnosisFor($this->projector);
    $version = $this->engine->flowFor($this->projector->id);

    $this->post(route('teacher.diagnostic.answer'), [
        'step_id' => (string) $version->stepByKey('projector_power')->id,
        'answer' => 'green',
    ]);

    $this->post(route('teacher.escalate.store'), [
        'blocks_class' => '1', 'confirmed' => '1',
        'form_opened_at' => now()->subSeconds(40)->timestamp,
    ]);

    $incident = Incident::whereNotNull('ticket_number')->first();

    expect($incident)->not->toBeNull()
        ->and($incident->hasStatus(S::New))->toBeTrue()
        ->and(DiagnosticAnswer::where('incident_id', $incident->id)->count())->toBe(1);
});
