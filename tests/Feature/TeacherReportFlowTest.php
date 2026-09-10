<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Incidents\Models\IncidentCategory;
use App\Modules\Locations\Models\Building;
use App\Modules\Locations\Models\Floor;
use App\Modules\Locations\Models\Room;
use App\Modules\Locations\Models\Site;
use App\Shared\Enums\IncidentStatus;
use App\Shared\Enums\IncidentStatus as S;
use App\Shared\Enums\ResolutionType;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\DemoDiagnosticsSeeder;
use Database\Seeders\DemoMediaSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Flujo completo del docente, de punta a punta (plan 7.1 y 76).
 *
 * Recorre lo mismo que haria una persona: escanear, elegir aula, decir que
 * pasa y, segun el caso, cerrar solo o pedir apoyo. Todo sin autenticacion.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(CatalogSeeder::class);

    $this->site = Site::create(['code' => 'HYO', 'name' => 'Huancayo', 'is_active' => true]);
    $this->building = Building::create(['site_id' => $this->site->id, 'code' => 'C', 'name' => 'Pabellón C', 'is_active' => true]);
    $this->floor = Floor::create(['building_id' => $this->building->id, 'number' => 3, 'label' => 'Piso 3', 'is_active' => true]);
    $this->room = Room::create(['floor_id' => $this->floor->id, 'code' => 'C305', 'criticality' => 1, 'is_active' => true]);

    $this->projector = IncidentCategory::where('code', 'PROJECTOR')->firstOrFail();
});

/** Confirma la ubicacion, que es lo que crea el borrador. */
function confirmLocation(): void
{
    // Como TEXTO: es lo que envia un formulario HTML real.
    test()->post(route('teacher.confirm.store'), [
        'site_id' => (string) test()->site->id,
        'building_id' => (string) test()->building->id,
        'floor_id' => (string) test()->floor->id,
        'room_id' => (string) test()->room->id,
    ]);
}

// ------------------------------------------------------------ borrador

it('crea un BORRADOR al confirmar la ubicacion', function () {
    confirmLocation();

    $incident = Incident::first();

    expect($incident)->not->toBeNull()
        ->and($incident->is_draft)->toBeTrue()
        ->and($incident->statusCode())->toBe(S::Draft)
        ->and($incident->room_id)->toBe($this->room->id)
        ->and($incident->confirmed_at)->not->toBeNull()
        // Todavia NO es un ticket: sin numero y sin prioridad.
        ->and($incident->ticket_number)->toBeNull();
});

it('el borrador NO es visible para soporte', function () {
    confirmLocation();

    expect(Incident::visibleToSupport()->count())->toBe(0);
});

it('el borrador NO notifica a nadie', function () {
    // El sistema no debe movilizar a ninguna persona hasta que el docente
    // pida apoyo de forma explicita.
    User::factory()->create()->assignRole('technician');

    confirmLocation();

    expect(DB::table('notifications')->count())->toBe(0);
});

it('registra la confirmacion de ubicacion en el historial', function () {
    confirmLocation();

    $this->assertDatabaseHas('incident_events', [
        'event_type' => 'location_confirmed',
        'actor_type' => 'teacher',
    ]);
});

// ------------------------------------------------------------- reporte

it('muestra las categorias en la pantalla del problema', function () {
    confirmLocation();

    $this->get(route('teacher.category'))
        ->assertOk()
        ->assertSee('¿Qué problema tienes?')
        // Texto orientado al SINTOMA, no al componente: quien no sabe qué
        // es un HDMI sí sabe que no se ve la imagen.
        ->assertSee($this->projector->teacherText());
});

it('registra la categoria y pasa a diagnostico', function () {
    confirmLocation();

    $this->post(route('teacher.category.store'), [
        'category_id' => $this->projector->id,
        'description' => 'el proyector enciende pero no llega imagen',
    ])->assertRedirect(route('teacher.diagnostic'));

    $incident = Incident::first();

    expect($incident->statusCode())->toBe(S::Diagnosing)
        ->and($incident->category_id)->toBe($this->projector->id)
        ->and($incident->reported_description)->toContain('no llega imagen')
        ->and($incident->reported_at)->not->toBeNull();
});

it('redirige al inicio si no hay un borrador en curso', function () {
    $this->get(route('teacher.category'))->assertRedirect(route('teacher.start'));
});

// --------------------------------------------- confirmacion de solucion

it('ofrece las TRES opciones obligatorias cuando quedan pasos por probar', function () {
    $this->seed(DemoMediaSeeder::class);
    $this->seed(DemoDiagnosticsSeeder::class);

    confirmLocation();
    $this->post(route('teacher.category.store'), ['category_id' => $this->projector->id]);

    $this->get(route('teacher.outcome'))
        ->assertOk()
        ->assertSee('Sí, funciona correctamente')
        ->assertSee('No, todavía tengo el problema')
        ->assertSee('Necesito soporte técnico');
});

it('no ofrece "seguir probando" cuando ya no quedan pasos', function () {
    // Sin árbol publicado, "todavía tengo el problema" y "necesito soporte"
    // serían el MISMO botón. Mostrar los dos haría que el docente toque uno
    // y no pase nada visible, y eso se lee como que el sistema se colgó.
    // El botón de soporte absorbe la respuesta negativa en su etiqueta.
    confirmLocation();
    $this->post(route('teacher.category.store'), ['category_id' => $this->projector->id]);

    $this->get(route('teacher.outcome'))
        ->assertOk()
        ->assertSee('Sí, funciona correctamente')
        ->assertSee('No, necesito soporte técnico')
        ->assertDontSee('No, todavía tengo el problema');
});

it('cierra la incidencia cuando el docente dice que se solucionó', function () {
    confirmLocation();
    $this->post(route('teacher.category.store'), ['category_id' => $this->projector->id]);

    $this->post(route('teacher.resolved'))->assertRedirect();

    $incident = Incident::first();

    expect($incident->statusCode())->toBe(S::Resolved)
        ->and($incident->resolutionType())->toBe(ResolutionType::Assistant)
        ->and($incident->resolutionType()->avoidedTrip())->toBeTrue()
        ->and($incident->resolved_at)->not->toBeNull()
        // Deja de ser borrador: cuenta como incidencia real resuelta, que es
        // el indicador central de la investigación.
        ->and($incident->is_draft)->toBeFalse();
});

it('NO notifica a soporte cuando el docente resuelve solo', function () {
    User::factory()->create()->assignRole('technician');

    confirmLocation();
    $this->post(route('teacher.category.store'), ['category_id' => $this->projector->id]);
    $this->post(route('teacher.resolved'));

    expect(DB::table('notifications')->count())->toBe(0);
});

// --------------------------------------------------------- escalamiento

it('convierte el borrador en TICKET al solicitar soporte', function () {
    confirmLocation();
    $this->post(route('teacher.category.store'), ['category_id' => $this->projector->id]);

    $this->post(route('teacher.escalate.store'), [
        'blocks_class' => 1,
        'confirmed' => 1,
        'form_opened_at' => now()->subSeconds(40)->timestamp,
    ])->assertRedirect();

    $incident = Incident::first();

    expect($incident->statusCode())->toBe(S::New)
        ->and($incident->is_draft)->toBeFalse()
        ->and($incident->ticket_number)->not->toBeNull()
        ->and($incident->priority_id)->not->toBeNull()
        ->and($incident->blocks_class)->toBeTrue();
});

it('sube la prioridad cuando la clase está detenida', function () {
    confirmLocation();
    $this->post(route('teacher.category.store'), ['category_id' => $this->projector->id]);
    $this->post(route('teacher.escalate.store'), [
        'blocks_class' => 1, 'confirmed' => 1,
        'form_opened_at' => now()->subSeconds(40)->timestamp,
    ]);

    $bloqueada = Incident::first()->priority->level;

    // Misma categoría y misma aula, pero sin clase detenida.
    $this->flushSession();
    confirmLocation();
    $this->post(route('teacher.category.store'), ['category_id' => IncidentCategory::where('code', 'SPEAKER')->first()->id]);
    $this->post(route('teacher.escalate.store'), [
        'blocks_class' => 0, 'confirmed' => 1,
        'form_opened_at' => now()->subSeconds(40)->timestamp,
    ]);

    $normal = Incident::orderByDesc('id')->first()->priority->level;

    expect($bloqueada)->toBeGreaterThan($normal);
});

it('guarda POR QUÉ el ticket tiene esa prioridad', function () {
    // Una prioridad sin explicación se percibe como arbitraria y acaba
    // ignorándose por el equipo de soporte.
    confirmLocation();
    $this->post(route('teacher.category.store'), ['category_id' => $this->projector->id]);
    $this->post(route('teacher.escalate.store'), [
        'blocks_class' => 1, 'confirmed' => 1,
        'form_opened_at' => now()->subSeconds(40)->timestamp,
    ]);

    $event = DB::table('incident_events')->where('event_type', 'escalated_to_support')->first();
    $metadata = json_decode($event->metadata, true);

    expect($metadata['priority_factors'])->toBeArray()->not->toBeEmpty()
        ->and(implode(' ', $metadata['priority_factors']))->toContain('impide continuar la clase');
});

it('NOTIFICA a soporte al escalar', function () {
    $tecnico = User::factory()->create();
    $tecnico->assignRole('technician');

    confirmLocation();
    $this->post(route('teacher.category.store'), ['category_id' => $this->projector->id]);
    $this->post(route('teacher.escalate.store'), [
        'blocks_class' => 1, 'confirmed' => 1,
        'form_opened_at' => now()->subSeconds(40)->timestamp,
    ]);

    $notification = DB::table('notifications')->where('notifiable_id', $tecnico->id)->first();

    expect($notification)->not->toBeNull();

    $data = json_decode($notification->data, true);

    // El aviso lleva YA todo lo necesario para decidir si ir y con qué
    // prioridad: soporte no tiene que preguntar nada (plan §45).
    expect($data['room_code'])->toBe('C305')
        ->and($data['category'])->toBe('Proyector')
        ->and($data['blocks_class'])->toBeTrue()
        ->and($data['ticket_number'])->not->toBeNull();
});

it('RECHAZA el escalamiento sin confirmación explícita', function () {
    confirmLocation();
    $this->post(route('teacher.category.store'), ['category_id' => $this->projector->id]);

    $this->post(route('teacher.escalate.store'), [
        'blocks_class' => 1,
        'form_opened_at' => now()->subSeconds(40)->timestamp,
    ])->assertSessionHasErrors('confirmed');

    expect(Incident::whereNotNull('ticket_number')->count())->toBe(0);
});

it('el docente NUNCA elige la prioridad directamente', function () {
    confirmLocation();
    $this->post(route('teacher.category.store'), ['category_id' => $this->projector->id]);

    // Intento de forzar prioridad crítica desde el formulario.
    $this->post(route('teacher.escalate.store'), [
        'blocks_class' => 0,
        'confirmed' => 1,
        'priority_id' => 4,
        'priority' => 'CRITICAL',
        'form_opened_at' => now()->subSeconds(40)->timestamp,
    ]);

    // La prioridad es la que calculan las reglas, no la enviada.
    expect(Incident::first()->priority->code)->not->toBe('CRITICAL');
});

// ----------------------------------------------------- pantalla final

it('la pantalla final dice al docente que soporte ya lo sabe todo', function () {
    confirmLocation();
    $this->post(route('teacher.category.store'), ['category_id' => $this->projector->id]);
    $this->post(route('teacher.escalate.store'), [
        'blocks_class' => 1, 'confirmed' => 1,
        'form_opened_at' => now()->subSeconds(40)->timestamp,
    ]);

    $this->get(route('teacher.done', ['uuid' => Incident::first()->uuid]))
        ->assertOk()
        ->assertSee('Soporte ya fue avisado')
        ->assertSee('No hace falta que llames');
});

// ------------------------------------------------------ antiduplicados

it('ofrece sumarse a un ticket existente en lugar de crear otro', function () {
    // Primer docente escala.
    confirmLocation();
    $this->post(route('teacher.category.store'), ['category_id' => $this->projector->id]);
    $this->post(route('teacher.escalate.store'), [
        'blocks_class' => 1, 'confirmed' => 1,
        'form_opened_at' => now()->subSeconds(40)->timestamp,
    ]);

    $primerTicket = Incident::whereNotNull('ticket_number')->first();

    // Segundo docente, mismo problema, misma aula.
    $this->flushSession();
    confirmLocation();
    $this->post(route('teacher.category.store'), ['category_id' => $this->projector->id]);

    $this->post(route('teacher.escalate.store'), [
        'blocks_class' => 1, 'confirmed' => 1,
        'form_opened_at' => now()->subSeconds(40)->timestamp,
    ])->assertRedirect(route('teacher.join', ['uuid' => $primerTicket->uuid]));

    // No se creó un segundo ticket.
    expect(Incident::whereNotNull('ticket_number')->count())->toBe(1);
});

it('al sumarse, el ticket original registra el reporte adicional', function () {
    confirmLocation();
    $this->post(route('teacher.category.store'), ['category_id' => $this->projector->id]);
    $this->post(route('teacher.escalate.store'), [
        'blocks_class' => 1, 'confirmed' => 1,
        'form_opened_at' => now()->subSeconds(40)->timestamp,
    ]);

    $ticket = Incident::whereNotNull('ticket_number')->first();

    $this->flushSession();
    confirmLocation();
    $this->post(route('teacher.category.store'), ['category_id' => $this->projector->id]);
    $this->post(route('teacher.join.store', ['uuid' => $ticket->uuid]))->assertRedirect();

    // Saber que hay MÁS de una persona afectada es información operativa
    // útil, no ruido: por eso el borrador se enlaza y no se descarta.
    $this->assertDatabaseHas('incident_events', [
        'incident_id' => $ticket->id,
        'event_type' => 'additional_report_received',
    ]);

    expect(Incident::where('merged_into_id', $ticket->id)->count())->toBe(1);
});

/* ------------------------------------------------------------------ */
/* Encuesta de facilidad de uso (plan 26.bis, decisión D-8) */
/* ------------------------------------------------------------------ */

it('la encuesta se ofrece al final y es opcional', function () {
    $incident = Incident::create([
        'room_id' => $this->room->id,
        'status_id' => App\Modules\Incidents\Models\IncidentStatus::idFor(IncidentStatus::Resolved),
        'is_draft' => false,
        'resolution_type' => 'assistant',
        'confirmed_at' => now()->subMinutes(10),
        'reported_at' => now()->subMinutes(8),
        'resolved_at' => now(),
    ]);

    $this->get(route('teacher.done', ['uuid' => $incident->uuid]))
        ->assertOk()
        ->assertSee('¿Te resultó fácil de usar?', false)
        ->assertSee('Opcional', false);
});

it('registra la puntuación y agradece', function () {
    $incident = Incident::create([
        'room_id' => $this->room->id,
        'status_id' => App\Modules\Incidents\Models\IncidentStatus::idFor(IncidentStatus::Resolved),
        'is_draft' => false,
        'resolution_type' => 'assistant',
        'confirmed_at' => now()->subMinutes(10),
        'reported_at' => now()->subMinutes(8),
        'resolved_at' => now(),
    ]);

    $this->post(route('teacher.survey', ['uuid' => $incident->uuid]), ['ease_score' => '4'])
        ->assertRedirect(route('teacher.done', ['uuid' => $incident->uuid]));

    $this->assertDatabaseHas('satisfaction_responses', [
        'incident_id' => $incident->id,
        'ease_score' => 4,
    ]);
});

it('no pisa una respuesta ya dada', function () {
    $incident = Incident::create([
        'room_id' => $this->room->id,
        'status_id' => App\Modules\Incidents\Models\IncidentStatus::idFor(IncidentStatus::Resolved),
        'is_draft' => false,
        'resolution_type' => 'assistant',
        'confirmed_at' => now()->subMinutes(10),
        'reported_at' => now()->subMinutes(8),
        'resolved_at' => now(),
    ]);

    $this->post(route('teacher.survey', ['uuid' => $incident->uuid]), ['ease_score' => '5']);
    $this->post(route('teacher.survey', ['uuid' => $incident->uuid]), ['ease_score' => '1']);

    // Un reenvío accidental no debe cambiar un dato de la investigación.
    expect(DB::table('satisfaction_responses')->where('incident_id', $incident->id)->count())->toBe(1)
        ->and(DB::table('satisfaction_responses')->where('incident_id', $incident->id)->value('ease_score'))->toBe(5);
});

it('rechaza una puntuación fuera de rango', function () {
    $incident = Incident::create([
        'room_id' => $this->room->id,
        'status_id' => App\Modules\Incidents\Models\IncidentStatus::idFor(IncidentStatus::Resolved),
        'is_draft' => false,
        'resolution_type' => 'assistant',
        'confirmed_at' => now()->subMinutes(10),
        'reported_at' => now()->subMinutes(8),
        'resolved_at' => now(),
    ]);

    $this->post(route('teacher.survey', ['uuid' => $incident->uuid]), ['ease_score' => '9'])
        ->assertSessionHasErrors('ease_score');
});
