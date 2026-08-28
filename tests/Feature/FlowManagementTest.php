<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Diagnostics\Models\DiagnosticFlowVersion;
use App\Modules\Diagnostics\Models\DiagnosticStep;
use App\Modules\Incidents\Models\IncidentCategory;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;

/**
 * Árboles de diagnóstico desde el panel (CU-A-10).
 *
 * El contenido lo escribe soporte, no el programador: hasta ahora los
 * procedimientos solo entraban por un seeder.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(CatalogSeeder::class);

    $this->gestor = User::factory()->create();
    $this->gestor->assignRole('knowledge_manager');

    $this->tecnico = User::factory()->create();
    $this->tecnico->assignRole('technician');

    $this->categoria = IncidentCategory::where('code', 'PROJECTOR')->firstOrFail();

    $this->crearBorrador = function (): DiagnosticFlowVersion {
        $this->actingAs($this->gestor)->post(route('admin.flows.store'), [
            'category_id' => $this->categoria->id,
        ]);

        return DiagnosticFlowVersion::latest('id')->firstOrFail();
    };
});

it('el gestor de conocimiento crea un procedimiento', function () {
    $version = ($this->crearBorrador)();

    expect($version->version)->toBe(1)
        // Nace como borrador: ningún docente lo ve todavía.
        ->and($version->published_at)->toBeNull();
});

it('un técnico no puede escribir procedimientos', function () {
    $this->actingAs($this->tecnico)->get(route('admin.flows.index'))->assertForbidden();
});

it('acepta las respuestas escritas en texto llano, sin JSON', function () {
    $version = ($this->crearBorrador)();

    $this->actingAs($this->gestor)->post(route('admin.flows.steps.store', $version), [
        'step_key' => 'revisar_cable',
        'prompt_text' => '¿El cable está bien conectado?',
        'component_key' => 'hdmi_port',
        'respuestas' => "si|Sí, está conectado|revisar_fuente\nno|No, estaba suelto",
    ])->assertRedirect();

    $step = DiagnosticStep::where('step_key', 'revisar_cable')->firstOrFail();

    // Pedirle JSON a quien sabe de proyectores garantiza que se equivoque.
    expect($step->answer_options)->toBe([
        ['value' => 'si', 'label' => 'Sí, está conectado'],
        ['value' => 'no', 'label' => 'No, estaba suelto'],
    ])
        // Sin destino, la respuesta lleva a «¿se solucionó?»: es lo que se
        // quiere tras pedir una acción correctiva.
        ->and($step->next_step_map)->toBe(['si' => 'revisar_fuente']);
});

it('NO publica un árbol que salta a un paso inexistente', function () {
    $version = ($this->crearBorrador)();

    $this->actingAs($this->gestor)->post(route('admin.flows.steps.store', $version), [
        'step_key' => 'primero',
        'prompt_text' => '¿Está encendido?',
        'respuestas' => 'si|Sí|paso_que_no_existe',
    ]);

    // Publicarlo dejaría al docente atascado en mitad del diagnóstico, y lo
    // descubriría él, en el aula, con la clase esperando.
    $this->actingAs($this->gestor)->post(route('admin.flows.publish', $version))
        ->assertSessionHas('error');

    expect($version->fresh()->published_at)->toBeNull();
});

it('NO publica un árbol del que no se sale nunca', function () {
    $version = ($this->crearBorrador)();

    $this->actingAs($this->gestor)->post(route('admin.flows.steps.store', $version), [
        'step_key' => 'unico',
        'prompt_text' => '¿Está encendido?',
        'respuestas' => 'si|Sí',
    ]);

    $this->actingAs($this->gestor)->post(route('admin.flows.publish', $version))
        ->assertSessionHas('error');
});

it('publica un árbol correcto y lo pone en uso', function () {
    $version = ($this->crearBorrador)();

    $this->actingAs($this->gestor)->post(route('admin.flows.steps.store', $version), [
        'step_key' => 'inicio', 'prompt_text' => '¿Está encendido?', 'sort_order' => 0,
        'respuestas' => "si|Sí|fin\nno|No|fin",
    ]);
    $this->actingAs($this->gestor)->post(route('admin.flows.steps.store', $version), [
        'step_key' => 'fin', 'prompt_text' => 'Avisamos a soporte.', 'sort_order' => 1,
        'is_terminal' => '1', 'terminal_outcome' => 'escalate',
    ]);

    $this->actingAs($this->gestor)->post(route('admin.flows.publish', $version))->assertRedirect();

    expect($version->fresh()->published_at)->not->toBeNull();
});

it('una versión publicada NO se puede modificar', function () {
    $version = ($this->crearBorrador)();
    $version->update(['published_at' => now()]);

    // Hay incidencias cerradas que la ejecutaron: cambiarla haría que sus
    // respuestas dejaran de significar lo que significaban.
    $this->actingAs($this->gestor)->get(route('admin.flows.steps.create', $version))
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->actingAs($this->gestor)->post(route('admin.flows.steps.store', $version), [
        'step_key' => 'colado', 'prompt_text' => 'No debería entrar', 'is_terminal' => '1',
    ])->assertSessionHasErrors();

    expect(DiagnosticStep::where('step_key', 'colado')->exists())->toBeFalse();
});

it('duplicar copia los pasos en un borrador nuevo', function () {
    $version = ($this->crearBorrador)();
    $this->actingAs($this->gestor)->post(route('admin.flows.steps.store', $version), [
        'step_key' => 'uno', 'prompt_text' => 'Pregunta original', 'is_terminal' => '1',
    ]);
    $version->update(['published_at' => now()]);

    $this->actingAs($this->gestor)->post(route('admin.flows.duplicate', $version))->assertRedirect();

    $borrador = DiagnosticFlowVersion::whereNull('published_at')->latest('id')->firstOrFail();

    // Casi siempre se retoca una pregunta, no se empieza de cero.
    expect($borrador->version)->toBe(2)
        ->and($borrador->steps()->count())->toBe(1)
        ->and($borrador->steps()->first()->prompt_text)->toBe('Pregunta original')
        // Y la publicada queda intacta sosteniendo su historia.
        ->and($version->fresh()->steps()->count())->toBe(1);
});

it('publicar una versión retira la anterior', function () {
    $v1 = ($this->crearBorrador)();
    $this->actingAs($this->gestor)->post(route('admin.flows.steps.store', $v1), [
        'step_key' => 'uno', 'prompt_text' => 'Uno', 'is_terminal' => '1',
    ]);
    $this->actingAs($this->gestor)->post(route('admin.flows.publish', $v1));

    $this->actingAs($this->gestor)->post(route('admin.flows.duplicate', $v1));
    $v2 = DiagnosticFlowVersion::whereNull('published_at')->latest('id')->firstOrFail();
    $this->actingAs($this->gestor)->post(route('admin.flows.publish', $v2));

    // Dos versiones vigentes dejarían al motor eligiendo al azar.
    expect($v1->fresh()->published_at)->toBeNull()
        ->and($v2->fresh()->published_at)->not->toBeNull();
});

it('no admite dos pasos con la misma clave', function () {
    $version = ($this->crearBorrador)();

    $datos = ['step_key' => 'repetido', 'prompt_text' => 'Algo', 'is_terminal' => '1'];
    $this->actingAs($this->gestor)->post(route('admin.flows.steps.store', $version), $datos);

    // Los saltos apuntarían a cualquiera de los dos.
    $this->actingAs($this->gestor)->post(route('admin.flows.steps.store', $version), $datos)
        ->assertSessionHasErrors('step_key');

    expect(DiagnosticStep::where('step_key', 'repetido')->count())->toBe(1);
});

it('avisa cuando al borrar un paso quedan saltos rotos', function () {
    $version = ($this->crearBorrador)();

    $this->actingAs($this->gestor)->post(route('admin.flows.steps.store', $version), [
        'step_key' => 'destino', 'prompt_text' => 'Destino', 'is_terminal' => '1',
    ]);
    $this->actingAs($this->gestor)->post(route('admin.flows.steps.store', $version), [
        'step_key' => 'origen', 'prompt_text' => 'Origen', 'respuestas' => 'si|Sí|destino',
    ]);

    $destino = DiagnosticStep::where('step_key', 'destino')->firstOrFail();

    // No se corrigen solos: adivinar a dónde debería ir ahora el docente
    // sería inventar el procedimiento.
    $this->actingAs($this->gestor)->delete(route('admin.flows.steps.destroy', $destino))
        ->assertSessionHas('error');
});
