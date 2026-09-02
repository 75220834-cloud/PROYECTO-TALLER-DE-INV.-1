<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Incidents\Models\IncidentCategory;
use App\Modules\Incidents\Models\IncidentStatus as StatusModel;
use App\Modules\Knowledge\Models\KnowledgeDocument;
use App\Modules\Locations\Models\Building;
use App\Modules\Locations\Models\Floor;
use App\Modules\Locations\Models\Room;
use App\Modules\Locations\Models\Site;
use App\Shared\Enums\IncidentStatus as S;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * Reportes por periodo (CU-S-15) y conversión de una solución en artículo
 * de conocimiento (CU-S-16).
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(CatalogSeeder::class);
    Storage::fake(config('incidencias.media.disk'));
    Queue::fake();

    $site = Site::create(['code' => 'HYO', 'name' => 'Huancayo', 'is_active' => true]);

    $this->pabellonC = Building::create(['site_id' => $site->id, 'code' => 'C', 'name' => 'Pabellón C', 'is_active' => true]);
    $this->pabellonD = Building::create(['site_id' => $site->id, 'code' => 'D', 'name' => 'Pabellón D', 'is_active' => true]);

    $pisoC = Floor::create(['building_id' => $this->pabellonC->id, 'number' => 1, 'label' => 'Piso 1', 'is_active' => true]);
    $pisoD = Floor::create(['building_id' => $this->pabellonD->id, 'number' => 1, 'label' => 'Piso 1', 'is_active' => true]);

    $this->aulaC = Room::create(['floor_id' => $pisoC->id, 'code' => 'C101', 'criticality' => 1, 'is_active' => true]);
    $this->aulaD = Room::create(['floor_id' => $pisoD->id, 'code' => 'D101', 'criticality' => 1, 'is_active' => true]);

    $this->jefe = User::factory()->create();
    $this->jefe->assignRole('coordinator');

    $this->crear = fn (Room $aula, array $extra = []): Incident => Incident::create(array_merge([
        'room_id' => $aula->id,
        'category_id' => IncidentCategory::value('id'),
        'status_id' => StatusModel::idFor(S::New),
        'is_draft' => false,
        'ticket_number' => 'T-'.random_int(1000, 9999),
        'reported_at' => now()->subDays(2),
    ], $extra));
});

/* ------------------------------------------------------------------ */
/* Reportes */
/* ------------------------------------------------------------------ */

it('agrupa las incidencias por pabellón', function () {
    ($this->crear)($this->aulaC);
    ($this->crear)($this->aulaC);
    ($this->crear)($this->aulaD);

    $this->actingAs($this->jefe)->get(route('support.reports', ['por' => 'pabellon']))
        ->assertOk()
        ->assertSee('C - Pabellón C')
        ->assertSee('D - Pabellón D');
});

it('el filtro de pabellón excluye lo que no le corresponde', function () {
    ($this->crear)($this->aulaC);
    ($this->crear)($this->aulaD);

    $this->actingAs($this->jefe)
        ->get(route('support.reports', ['por' => 'aula', 'pabellon' => $this->pabellonC->id]))
        ->assertOk()
        ->assertSee('C101')
        ->assertDontSee('D101');
});

it('no cuenta los borradores abandonados', function () {
    ($this->crear)($this->aulaC);
    ($this->crear)($this->aulaC, ['is_draft' => true, 'status_id' => StatusModel::idFor(S::Draft)]);

    // Contarlos inflaría cualquier cifra que acabe citada en la tesis.
    $this->actingAs($this->jefe)->get(route('support.reports'))
        ->assertOk()
        ->assertSeeInOrder(['Incidencias en el periodo', '1']);
});

it('deja fuera lo que ocurrió antes del periodo', function () {
    ($this->crear)($this->aulaC, ['reported_at' => now()->subDays(200)]);

    $this->actingAs($this->jefe)->get(route('support.reports'))
        ->assertOk()
        ->assertSee('No hay incidencias en este periodo');
});

it('avisa cuando hay tan pocos casos que no se puede concluir nada', function () {
    ($this->crear)($this->aulaC);

    // El reporte va a acabar citado: con 1 caso, cualquier diferencia entre
    // filas es ruido, y el sistema tiene que decirlo.
    $this->actingAs($this->jefe)->get(route('support.reports'))
        ->assertOk()
        ->assertSee('Es muy poco para sacar', false);
});

it('una fecha ilegible en la barra de direcciones no tumba la página', function () {
    $this->actingAs($this->jefe)->get(route('support.reports', ['desde' => 'ayer', 'hasta' => '../../etc']))
        ->assertOk();
});

it('una agrupación inventada cae al valor por defecto', function () {
    ($this->crear)($this->aulaC);

    $this->actingAs($this->jefe)->get(route('support.reports', ['por' => 'DROP TABLE']))
        ->assertOk()
        ->assertSee('Pabellón');
});

it('el CSV lleva dentro el periodo del que habla', function () {
    ($this->crear)($this->aulaC);

    // Exportar es un permiso aparte del de mirar: descargar el detalle del
    // piloto no es lo mismo que consultar un total en pantalla.
    $investigador = User::factory()->create();
    $investigador->assignRole('researcher');

    $csv = $this->actingAs($investigador)
        ->get(route('support.reports.export', ['por' => 'pabellon']))
        ->assertOk()
        ->streamedContent();

    // Un CSV suelto sin su rango de fechas no se puede citar.
    expect($csv)->toContain('Desde')->toContain('Hasta')->toContain('Pabellón C');
});

it('el reporte exige permiso', function () {
    $sinPermiso = User::factory()->create();
    $this->actingAs($sinPermiso)->get(route('support.reports'))->assertForbidden();

    // El tecnico tampoco: el plan le da el tablero y el historial del aula,
    // que es lo que necesita para atender. Los reportes agregados sirven
    // para decidir compras y turnos, y esa es otra silla.
    $tecnico = User::factory()->create();
    $tecnico->assignRole('technician');
    $this->actingAs($tecnico)->get(route('support.reports'))->assertForbidden();

    $investigador = User::factory()->create();
    $investigador->assignRole('researcher');
    $this->actingAs($investigador)->get(route('support.reports'))->assertOk();
});

/* ------------------------------------------------------------------ */
/* Solución → artículo */
/* ------------------------------------------------------------------ */

it('convierte una solución resuelta en un artículo borrador', function () {
    $gestor = User::factory()->create();
    $gestor->assignRole('knowledge_manager');

    $incident = ($this->crear)($this->aulaC, [
        'resolved_at' => now()->subHour(),
        'resolution_notes' => 'Se cambió el cable HDMI del proyector',
        'status_id' => StatusModel::idFor(S::Resolved),
    ]);

    $this->actingAs($gestor)->get(route('admin.knowledge.from-incident', $incident))
        ->assertOk()
        ->assertSee('Se cambió el cable HDMI del proyector');

    $this->actingAs($gestor)->post(route('admin.knowledge.from-incident.store', $incident), [
        'title' => 'El proyector no muestra imagen aunque la laptop sí',
        'body' => str_repeat('Revisar primero el cable HDMI en ambos extremos. ', 4),
    ])->assertRedirect();

    $document = KnowledgeDocument::firstOrFail();

    // Nace en borrador a propósito: una nota escrita para un compañero puede
    // contener un apaño que no debe generalizarse, y publicarla equivale a
    // que el asistente se lo recomiende a todos los docentes.
    expect($document->status)->toBe('draft')
        ->and($document->source_incident_id)->toBe($incident->id)
        ->and($document->versions()->count())->toBe(1);
});

it('el archivo guardado dice de qué ticket salió', function () {
    $gestor = User::factory()->create();
    $gestor->assignRole('knowledge_manager');

    $incident = ($this->crear)($this->aulaC, [
        'ticket_number' => 'T-4242',
        'resolved_at' => now()->subHour(),
        'resolution_notes' => 'Se reemplazó el cable',
    ]);

    $this->actingAs($gestor)->post(route('admin.knowledge.from-incident.store', $incident), [
        'title' => 'Proyector sin imagen en C101',
        'body' => str_repeat('Pasos completos para revisar la conexión del proyector. ', 3),
    ]);

    $version = KnowledgeDocument::firstOrFail()->versions()->firstOrFail();
    $contenido = Storage::disk(config('incidencias.media.disk'))->get($version->file_path);

    // La procedencia va dentro del archivo, no solo en la base de datos: el
    // archivo se descarga y circula por correo suelto.
    expect($contenido)->toContain('T-4242')->toContain('C101');
});

it('no deja documentar un ticket que sigue abierto', function () {
    $gestor = User::factory()->create();
    $gestor->assignRole('knowledge_manager');

    $incident = ($this->crear)($this->aulaC);

    $this->actingAs($gestor)->get(route('admin.knowledge.from-incident', $incident))
        ->assertRedirect(route('support.incidents.show', $incident));

    expect(KnowledgeDocument::count())->toBe(0);
});

it('no deja documentar un cierre sin explicación', function () {
    $gestor = User::factory()->create();
    $gestor->assignRole('knowledge_manager');

    $incident = ($this->crear)($this->aulaC, ['resolved_at' => now(), 'resolution_notes' => null]);

    $this->actingAs($gestor)->get(route('admin.knowledge.from-incident', $incident))
        ->assertRedirect(route('support.incidents.show', $incident));
});

it('no permite dos artículos del mismo ticket', function () {
    $gestor = User::factory()->create();
    $gestor->assignRole('knowledge_manager');

    $incident = ($this->crear)($this->aulaC, [
        'resolved_at' => now(),
        'resolution_notes' => 'Se cambió el cable',
    ]);

    $payload = [
        'title' => 'Proyector sin imagen',
        'body' => str_repeat('Revisar el cable HDMI en ambos extremos del proyector. ', 3),
    ];

    $this->actingAs($gestor)->post(route('admin.knowledge.from-incident.store', $incident), $payload);
    $this->actingAs($gestor)->post(route('admin.knowledge.from-incident.store', $incident), $payload);

    expect(KnowledgeDocument::count())->toBe(1);
});

it('rechaza un artículo que solo copia la nota de resolución', function () {
    $gestor = User::factory()->create();
    $gestor->assignRole('knowledge_manager');

    $incident = ($this->crear)($this->aulaC, [
        'resolved_at' => now(),
        'resolution_notes' => 'Cambié el cable',
    ]);

    // «Cambié el cable» no le sirve a quien no estuvo ahí.
    $this->actingAs($gestor)->post(route('admin.knowledge.from-incident.store', $incident), [
        'title' => 'Proyector sin imagen',
        'body' => 'Cambié el cable',
    ])->assertSessionHasErrors('body');

    expect(KnowledgeDocument::count())->toBe(0);
});

it('un técnico no puede crear artículos', function () {
    $tecnico = User::factory()->create();
    $tecnico->assignRole('technician');

    $incident = ($this->crear)($this->aulaC, ['resolved_at' => now(), 'resolution_notes' => 'Algo']);

    // Publicar conocimiento cambia lo que el asistente le dice a TODOS los
    // docentes: es un permiso aparte por diseño.
    $this->actingAs($tecnico)->get(route('admin.knowledge.from-incident', $incident))->assertForbidden();
});
