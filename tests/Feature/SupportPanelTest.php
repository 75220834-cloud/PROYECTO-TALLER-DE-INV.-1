<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Incidents\Models\IncidentCategory;
use App\Modules\Incidents\Models\IncidentStatus as StatusModel;
use App\Modules\Locations\Models\Building;
use App\Modules\Locations\Models\Floor;
use App\Modules\Locations\Models\Room;
use App\Modules\Locations\Models\Site;
use App\Shared\Enums\IncidentPriority;
use App\Shared\Enums\IncidentStatus as S;
use App\Shared\Enums\ResolutionType;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;

/**
 * Panel de soporte: permisos y ciclo de atencion (plan 15, 45 y 17.2).
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(CatalogSeeder::class);

    $this->tecnico = User::factory()->create(['name' => 'Técnico Demo']);
    $this->tecnico->assignRole('technician');

    $this->coordinador = User::factory()->create();
    $this->coordinador->assignRole('coordinator');

    $this->investigador = User::factory()->create();
    $this->investigador->assignRole('researcher');

    $site = Site::create(['code' => 'HYO', 'name' => 'Huancayo', 'is_active' => true]);
    $building = Building::create(['site_id' => $site->id, 'code' => 'C', 'name' => 'Pabellón C', 'is_active' => true]);
    $floor = Floor::create(['building_id' => $building->id, 'number' => 3, 'label' => 'Piso 3', 'is_active' => true]);
    $this->room = Room::create(['floor_id' => $floor->id, 'code' => 'C305', 'criticality' => 1, 'is_active' => true]);

    $this->category = IncidentCategory::where('code', 'PROJECTOR')->firstOrFail();

    $this->ticket = Incident::create([
        'room_id' => $this->room->id,
        'category_id' => $this->category->id,
        'status_id' => StatusModel::idFor(S::New),
        'priority_id' => App\Modules\Incidents\Models\IncidentPriority::idFor(IncidentPriority::High),
        'ticket_number' => '000001',
        'is_draft' => false,
        'blocks_class' => true,
        'reported_description' => 'el proyector no muestra imagen',
        'reported_at' => now(),
    ]);
});

// ------------------------------------------------------------- permisos

it('exige autenticacion', function () {
    $this->get(route('support.incidents.index'))->assertRedirect(route('login'));
});

it('el tecnico ve la bandeja', function () {
    $this->actingAs($this->tecnico)
        ->get(route('support.incidents.index'))
        ->assertOk()
        ->assertSee('C305');
});

it('el investigador PUEDE ver pero NO puede modificar', function () {
    // Observa el proceso, no participa en él: si pudiera escribir, sus
    // acciones se mezclarían con las de soporte en la auditoría y
    // contaminarían las métricas del piloto.
    $this->actingAs($this->investigador)->get(route('support.incidents.index'))->assertOk();

    $this->actingAs($this->investigador)
        ->post(route('support.incidents.take', $this->ticket))
        ->assertForbidden();

    $this->actingAs($this->investigador)
        ->post(route('support.incidents.resolve', $this->ticket), [
            'resolution_type' => ResolutionType::Onsite->value,
            'resolution_notes' => 'x',
        ])->assertForbidden();
});

it('el tecnico NO puede cancelar incidencias', function () {
    // Cancelar borra una incidencia de las estadísticas; es decisión de
    // coordinación, no de quien la atiende.
    $this->actingAs($this->tecnico)
        ->post(route('support.incidents.cancel', $this->ticket), ['reason' => 'duplicada'])
        ->assertForbidden();

    $this->actingAs($this->coordinador)
        ->post(route('support.incidents.cancel', $this->ticket), ['reason' => 'duplicada'])
        ->assertSessionHas('status');
});

// -------------------------------------------------------------- detalle

it('el detalle muestra TODO lo que el tecnico necesita sin preguntar', function () {
    $this->actingAs($this->tecnico)
        ->get(route('support.incidents.show', $this->ticket))
        ->assertOk()
        ->assertSee('C305')
        ->assertSee('Pabellón C')
        ->assertSee('Piso 3')
        ->assertSee('Proyector')
        ->assertSee('el proyector no muestra imagen')
        ->assertSee('no puede dictar la clase');
});

it('la bandeja NO muestra borradores', function () {
    Incident::create([
        'room_id' => $this->room->id,
        'status_id' => StatusModel::idFor(S::Draft),
        'is_draft' => true,
    ]);

    $response = $this->actingAs($this->tecnico)->get(route('support.incidents.index'));

    expect($response->viewData('incidents')->total())->toBe(1);
});

// ------------------------------------------------------------ atencion

it('permite tomar un ticket y pasa a en atencion', function () {
    $this->actingAs($this->tecnico)
        ->post(route('support.incidents.take', $this->ticket))
        ->assertSessionHas('status');

    $this->ticket->refresh();

    expect($this->ticket->assigned_to)->toBe($this->tecnico->id)
        ->and($this->ticket->statusCode())->toBe(S::InProgress)
        ->and($this->ticket->first_response_at)->not->toBeNull();
});

it('la primera respuesta se marca UNA sola vez', function () {
    // Es un indicador de la investigación: sobrescribirlo al reasignar
    // falsearía el tiempo hasta la primera respuesta.
    $this->actingAs($this->tecnico)->post(route('support.incidents.take', $this->ticket));
    $primera = $this->ticket->fresh()->first_response_at;

    $this->travel(30)->minutes();

    $this->actingAs($this->coordinador)->post(route('support.incidents.assign', $this->ticket), [
        'technician_id' => $this->coordinador->id,
    ]);

    expect($this->ticket->fresh()->first_response_at->timestamp)->toBe($primera->timestamp);
});

it('permite resolver registrando qué se hizo', function () {
    $this->actingAs($this->tecnico)->post(route('support.incidents.take', $this->ticket));

    $this->actingAs($this->tecnico)->post(route('support.incidents.resolve', $this->ticket), [
        'resolution_type' => ResolutionType::Onsite->value,
        'technical_diagnosis' => 'cable HDMI dañado',
        'resolution_notes' => 'se reemplazó el cable',
    ])->assertSessionHas('status');

    $this->ticket->refresh();

    expect($this->ticket->statusCode())->toBe(S::Resolved)
        ->and($this->ticket->resolutionType())->toBe(ResolutionType::Onsite)
        ->and($this->ticket->resolutionType()->avoidedTrip())->toBeFalse()
        ->and($this->ticket->resolved_at)->not->toBeNull();
});

it('EXIGE describir la solución al resolver', function () {
    // La solución registrada es la materia prima de la base de conocimiento
    // y del análisis de recurrencia. Un ticket cerrado sin explicación no
    // enseña nada.
    $this->actingAs($this->tecnico)->post(route('support.incidents.take', $this->ticket));

    $this->actingAs($this->tecnico)
        ->post(route('support.incidents.resolve', $this->ticket), [
            'resolution_type' => ResolutionType::Onsite->value,
        ])
        ->assertSessionHasErrors('resolution_notes');

    expect($this->ticket->fresh()->statusCode())->not->toBe(S::Resolved);
});

it('permite cerrar tras resolver', function () {
    $this->actingAs($this->tecnico)->post(route('support.incidents.take', $this->ticket));
    $this->actingAs($this->tecnico)->post(route('support.incidents.resolve', $this->ticket), [
        'resolution_type' => ResolutionType::Onsite->value,
        'resolution_notes' => 'listo',
    ]);

    $this->actingAs($this->tecnico)->post(route('support.incidents.close', $this->ticket));

    expect($this->ticket->fresh()->statusCode())->toBe(S::Closed);
});

it('permite reabrir y cuenta las reaperturas', function () {
    $this->actingAs($this->tecnico)->post(route('support.incidents.take', $this->ticket));
    $this->actingAs($this->tecnico)->post(route('support.incidents.resolve', $this->ticket), [
        'resolution_type' => ResolutionType::Onsite->value,
        'resolution_notes' => 'listo',
    ]);

    $this->actingAs($this->tecnico)->post(route('support.incidents.reopen', $this->ticket), [
        'reason' => 'volvió a fallar al día siguiente',
    ])->assertSessionHas('status');

    $this->ticket->refresh();

    expect($this->ticket->statusCode())->toBe(S::InProgress)
        ->and($this->ticket->reopened_count)->toBe(1)
        ->and($this->ticket->resolved_at)->toBeNull();
});

// --------------------------------------------- transiciones invalidas

it('convierte una transición imposible en un mensaje, no en un error 500', function () {
    // Pasa sin que nadie haga nada raro: dos técnicos con la misma pantalla
    // abierta, uno cierra el ticket y el otro pulsa "resolver".
    $this->actingAs($this->tecnico)->post(route('support.incidents.take', $this->ticket));
    $this->actingAs($this->tecnico)->post(route('support.incidents.resolve', $this->ticket), [
        'resolution_type' => ResolutionType::Onsite->value,
        'resolution_notes' => 'listo',
    ]);
    $this->actingAs($this->tecnico)->post(route('support.incidents.close', $this->ticket));

    // Segundo técnico intenta resolver un ticket ya cerrado.
    $response = $this->actingAs($this->coordinador)->post(route('support.incidents.resolve', $this->ticket), [
        'resolution_type' => ResolutionType::Onsite->value,
        'resolution_notes' => 'otra cosa',
    ]);

    $response->assertSessionHas('error');
    expect(session('error'))->toContain('No se puede pasar de CLOSED');
});

// ------------------------------------------------------------ historial

it('el historial permite reconstruir todo el ciclo', function () {
    $this->actingAs($this->tecnico)->post(route('support.incidents.take', $this->ticket));
    $this->actingAs($this->tecnico)->post(route('support.incidents.resolve', $this->ticket), [
        'resolution_type' => ResolutionType::Onsite->value,
        'resolution_notes' => 'listo',
    ]);
    $this->actingAs($this->tecnico)->post(route('support.incidents.close', $this->ticket));

    $types = $this->ticket->events()->pluck('event_type')->all();

    expect($types)->toContain('assigned', 'resolved', 'closed', 'status_changed');
});

it('el historial registra QUIÉN hizo cada cosa', function () {
    $this->actingAs($this->tecnico)->post(route('support.incidents.take', $this->ticket));

    $this->assertDatabaseHas('incident_events', [
        'incident_id' => $this->ticket->id,
        'event_type' => 'assigned',
        'actor_id' => $this->tecnico->id,
        'actor_type' => 'user',
    ]);
});
