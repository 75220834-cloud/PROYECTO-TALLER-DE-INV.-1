<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Incidents\Models\IncidentCategory;
use App\Modules\Locations\Models\Building;
use App\Modules\Locations\Models\Floor;
use App\Modules\Locations\Models\Room;
use App\Modules\Locations\Models\Site;
use App\Shared\Enums\IncidentPriority;
use App\Shared\Enums\IncidentStatus as S;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;

/**
 * FLUJO COMPLETO DE EXTREMO A EXTREMO (plan 17.8).
 *
 * Recorre la cadena entera —QR genérico, cascada, confirmación, categoría,
 * escalamiento, ticket, panel, asignación, resolución, cierre— y después
 * verifica la CONSISTENCIA de los datos a lo largo de toda ella: el mismo
 * identificador de principio a fin, marcas de tiempo que avanzan y nunca
 * retroceden, y un historial que permite reconstruir qué pasó.
 *
 * Las pruebas por módulo verifican que cada pieza funciona. Esta verifica lo
 * que ninguna de ellas puede: que encajan entre sí. La mayoría de los fallos
 * que importan viven en las costuras.
 *
 * Se ejecuta sobre HTTP real, no llamando a los servicios: es la única forma
 * de que cuenten también las rutas, los permisos, la sesión y la validación.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(CatalogSeeder::class);

    $this->site = Site::create(['code' => 'HYO', 'name' => 'Huancayo', 'is_active' => true]);
    $building = Building::create(['site_id' => $this->site->id, 'code' => 'C', 'name' => 'Pabellón C', 'is_active' => true]);
    $this->floor = Floor::create(['building_id' => $building->id, 'number' => 3, 'label' => 'Piso 3', 'is_active' => true]);

    $this->roomA = Room::create(['floor_id' => $this->floor->id, 'code' => 'C305', 'criticality' => 1, 'is_active' => true]);
    // Código fuera de nomenclatura a propósito: demuestra que la ubicación se
    // resuelve por relaciones del catálogo y nunca concatenando (plan 11.3).
    $this->roomB = Room::create(['floor_id' => $this->floor->id, 'code' => 'LAB-2', 'criticality' => 3, 'is_active' => true]);

    $this->category = IncidentCategory::where('code', 'PROJECTOR')->firstOrFail();

    $this->tecnico = User::factory()->create(['name' => 'Técnico E2E']);
    $this->tecnico->assignRole('technician');

    /**
     * Un docente entra por el QR y llega a tener un ticket abierto.
     * Devuelve la incidencia ya creada.
     */
    $this->reportar = function (Room $room, string $descripcion, bool $bloqueaClase = true): Incident {
        // [1] El QR es el mismo para todas las aulas y no lleva ubicación.
        //     Lleva a UNA pantalla con las cuatro preguntas.
        $this->get(route('teacher.start'))->assertOk()->assertSee($room->code);

        // [2] La elección viaja junta. Se envían cadenas, que es lo que manda
        //     un formulario de verdad: un entero aquí escondería un fallo de
        //     conversión.
        $this->post(route('teacher.locate'), [
            'site_id' => (string) $this->site->id,
            'building_id' => (string) $room->floor->building_id,
            'floor_id' => (string) $room->floor_id,
            'room_id' => (string) $room->id,
        ])->assertRedirect();

        // [3] Confirmación explícita de la ubicación → nace el borrador.
        $this->post(route('teacher.confirm.store'), [
            'site_id' => (string) $this->site->id,
            'building_id' => (string) $room->floor->building_id,
            'floor_id' => (string) $room->floor_id,
            'room_id' => (string) $room->id,
        ])->assertRedirect(route('teacher.category'));

        // [4] Categoría y descripción libre.
        $this->post(route('teacher.category.store'), [
            'category_id' => (string) $this->category->id,
            'description' => $descripcion,
        ])->assertRedirect();

        // [5] Solicitar soporte, con confirmación explícita.
        $this->get(route('teacher.escalate'))->assertOk();

        $this->post(route('teacher.escalate.store'), [
            'blocks_class' => $bloqueaClase ? '1' : '0',
            'confirmed' => '1',
            'form_opened_at' => now()->subSeconds(30)->timestamp,
        ])->assertRedirect();

        return Incident::where('room_id', $room->id)->where('is_draft', false)->latest('id')->firstOrFail();
    };
});

it('recorre la cadena completa y deja los datos consistentes de punta a punta', function () {
    $ticket = ($this->reportar)($this->roomA, 'El proyector no muestra imagen');

    // --- lo que recibe soporte: nada que el docente deba repetir (plan 45)
    expect($ticket->ticket_number)->not->toBeNull()
        ->and($ticket->room_id)->toBe($this->roomA->id)
        ->and($ticket->category_id)->toBe($this->category->id)
        ->and($ticket->reported_description)->toBe('El proyector no muestra imagen')
        ->and($ticket->blocks_class)->toBeTrue()
        ->and($ticket->priority_id)->not->toBeNull()
        ->and($ticket->is_draft)->toBeFalse();

    // --- el técnico atiende
    $this->actingAs($this->tecnico)
        ->get(route('support.incidents.show', $ticket))
        ->assertOk()
        ->assertSee($this->roomA->code)
        ->assertSee('El proyector no muestra imagen');

    $this->actingAs($this->tecnico)->post(route('support.incidents.take', $ticket))->assertRedirect();
    $this->actingAs($this->tecnico)->post(route('support.incidents.resolve', $ticket), [
        'technical_diagnosis' => 'Cable HDMI suelto en el panel del proyector',
        'resolution_notes' => 'Se reconectó y se aseguró el conector',
        'resolution_type' => 'onsite',
    ])->assertRedirect();
    $this->actingAs($this->tecnico)->post(route('support.incidents.close', $ticket))->assertRedirect();

    $ticket->refresh();

    // --- CONSISTENCIA: el reloj solo avanza
    $hitos = array_filter([
        'confirmado' => $ticket->confirmed_at,
        'reportado' => $ticket->reported_at,
        'asignado' => $ticket->assigned_at,
        'resuelto' => $ticket->resolved_at,
        'cerrado' => $ticket->closed_at,
    ]);

    expect($hitos)->toHaveCount(5);

    $anterior = null;
    foreach ($hitos as $nombre => $momento) {
        if ($anterior !== null) {
            // Una marca que retrocede corrompe en silencio TODOS los
            // indicadores de tiempo de la investigación.
            expect($momento->greaterThanOrEqualTo($anterior))->toBeTrue("«{$nombre}» va hacia atrás");
        }
        $anterior = $momento;
    }

    // --- CONSISTENCIA: el historial permite reconstruir qué pasó (plan 57)
    $eventos = DB::table('incident_events')->where('incident_id', $ticket->id)
        ->orderBy('occurred_at')->pluck('event_type')->all();

    expect($eventos)->toContain('problem_reported')
        ->and($eventos)->toContain('escalated_to_support')
        ->and($eventos)->toContain('resolved');

    // --- CONSISTENCIA: aparece en el tablero y en la exportación
    $investigador = User::factory()->create();
    $investigador->assignRole('researcher');

    $this->actingAs($investigador)->get(route('support.dashboard'))->assertOk();

    $csv = $this->actingAs($investigador)->get(route('support.dashboard.export'))
        ->assertOk()->streamedContent();

    expect($csv)->toContain($ticket->uuid)->toContain('C305');
});

it('el mismo QR funciona igual desde otra aula, sin estado residual', function () {
    // Segunda pasada obligatoria del plan 17.8: demuestra que el QR es
    // genuinamente genérico y que nada de la sesión anterior se arrastra.
    $primero = ($this->reportar)($this->roomA, 'No hay imagen en el proyector');

    // El aula B tiene un código fuera de nomenclatura: si la resolución de
    // ubicación concatenara pabellón+piso en lugar de consultar el catálogo,
    // esta pasada fallaría.
    $segundo = ($this->reportar)($this->roomB, 'La pantalla no baja');

    expect($segundo->id)->not->toBe($primero->id)
        ->and($segundo->room_id)->toBe($this->roomB->id)
        ->and($segundo->uuid)->not->toBe($primero->uuid)
        ->and($segundo->ticket_number)->not->toBe($primero->ticket_number)
        ->and($segundo->reported_description)->toBe('La pantalla no baja');
});

it('ante un riesgo físico salta el diagnóstico y escala con prioridad máxima', function () {
    // Plan 17.5: pedirle a alguien que revise el cable de un equipo que echa
    // humo sería mandarlo a acercarse. El diagnóstico se omite entero.
    $this->get(route('teacher.start'));
    $this->post(route('teacher.confirm.store'), [
        'site_id' => (string) $this->site->id,
        'building_id' => (string) $this->roomA->floor->building_id,
        'floor_id' => (string) $this->roomA->floor_id,
        'room_id' => (string) $this->roomA->id,
    ]);

    $this->post(route('teacher.category.store'), [
        'category_id' => (string) $this->category->id,
        'description' => 'Sale humo del proyector y huele a quemado',
    ])->assertRedirect(route('teacher.escalate'));

    $this->get(route('teacher.escalate'))
        ->assertOk()
        ->assertSee('No manipules el equipo');

    $this->post(route('teacher.escalate.store'), [
        'blocks_class' => '1',
        'confirmed' => '1',
        'form_opened_at' => now()->subSeconds(30)->timestamp,
    ])->assertRedirect();

    $ticket = Incident::where('room_id', $this->roomA->id)->where('is_draft', false)->latest('id')->firstOrFail();

    expect($ticket->hazard_reported)->toBeTrue()
        ->and($ticket->hazard_term)->toBe('humo')
        // El aula es de criticidad baja y no hay reincidencia: sin la regla de
        // riesgo esto no habría llegado a crítica.
        ->and(DB::table('incident_priorities')->where('id', $ticket->priority_id)->value('code'))
        ->toBe(IncidentPriority::Critical->value);

    // Ni un solo paso de diagnóstico: no se le pidió tocar nada.
    expect(DB::table('diagnostic_answers')->where('incident_id', $ticket->id)->count())->toBe(0);

    // Y soporte lo ve antes de salir.
    $this->actingAs($this->tecnico)->get(route('support.incidents.show', $ticket))
        ->assertOk()
        ->assertSee('Riesgo físico reportado');
});

it('el flujo del docente se completa con la IA apagada del todo', function () {
    // Criterio de aceptación del plan 27.1: con LLM_PROVIDER=null el docente
    // llega al ticket igual. Se pierde comodidad, no funcionalidad.
    config()->set('incidencias.llm.provider', 'null');

    $ticket = ($this->reportar)($this->roomA, 'El proyector está apagado');

    expect($ticket->ticket_number)->not->toBeNull()
        ->and($ticket->hasStatus(S::New))->toBeTrue();
});
