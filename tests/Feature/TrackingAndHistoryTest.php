<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Incidents\Models\IncidentCategory;
use App\Modules\Incidents\Models\IncidentStatus as StatusModel;
use App\Modules\Incidents\Services\ReporterPhotoStore;
use App\Modules\Locations\Models\Building;
use App\Modules\Locations\Models\Floor;
use App\Modules\Locations\Models\Room;
use App\Modules\Locations\Models\Site;
use App\Shared\Enums\IncidentStatus as S;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Seguimiento para el docente (CU-D-11), historial del aula (CU-S-12) y foto
 * adjunta (decisión D-12).
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(CatalogSeeder::class);
    Storage::fake(config('incidencias.media.disk'));

    $site = Site::create(['code' => 'HYO', 'name' => 'Huancayo', 'is_active' => true]);
    $building = Building::create(['site_id' => $site->id, 'code' => 'C', 'name' => 'Pabellón C', 'is_active' => true]);
    $floor = Floor::create(['building_id' => $building->id, 'number' => 3, 'label' => 'Piso 3', 'is_active' => true]);
    $this->room = Room::create(['floor_id' => $floor->id, 'code' => 'C305', 'criticality' => 1, 'is_active' => true]);

    $this->tecnico = User::factory()->create();
    $this->tecnico->assignRole('technician');

    $this->crear = fn (array $extra = []): Incident => Incident::create(array_merge([
        'room_id' => $this->room->id,
        'category_id' => IncidentCategory::value('id'),
        'status_id' => StatusModel::idFor(S::New),
        'is_draft' => false,
        'ticket_number' => 'T-'.random_int(1000, 9999),
        'confirmed_at' => now()->subMinutes(20),
        'reported_at' => now()->subMinutes(15),
    ], $extra));
});

/* ------------------------------------------------------------------ */
/* Seguimiento del docente */
/* ------------------------------------------------------------------ */

it('el docente ve el estado de su solicitud sin cuenta ni contraseña', function () {
    $incident = ($this->crear)();

    // Pedirle una cuenta para ver su propio ticket contradiría la decisión
    // D-2 del plan, que es que el docente no se autentica nunca.
    $this->get(route('teacher.track', ['uuid' => $incident->uuid]))
        ->assertOk()
        ->assertSee('Soporte ya fue avisado')
        ->assertSee($incident->ticket_number);
});

it('muestra que un técnico se hizo cargo', function () {
    $incident = ($this->crear)(['assigned_at' => now()->subMinutes(5), 'assigned_to' => $this->tecnico->id]);

    $this->get(route('teacher.track', ['uuid' => $incident->uuid]))
        ->assertOk()
        ->assertSee('Un técnico se hizo cargo')
        // El nombre NO: el docente necesita saber que alguien se hizo cargo,
        // no a quién buscar por WhatsApp saltándose el canal que se mide.
        ->assertDontSee($this->tecnico->name);
});

it('la línea de tiempo solo muestra lo que ya pasó', function () {
    $incident = ($this->crear)();

    // Una lista con pasos futuros en gris haría creer que el sistema sabe
    // cuándo van a ocurrir, y no lo sabe.
    $this->get(route('teacher.track', ['uuid' => $incident->uuid]))
        ->assertOk()
        ->assertSee('Enviaste la solicitud')
        ->assertDontSee('El técnico llegó al aula');
});

it('no expone borradores abandonados', function () {
    $borrador = ($this->crear)(['is_draft' => true, 'status_id' => StatusModel::idFor(S::Draft)]);

    $this->get(route('teacher.track', ['uuid' => $borrador->uuid]))->assertNotFound();
});

it('un uuid inventado no revela nada', function () {
    $this->get(route('teacher.track', ['uuid' => '00000000-0000-0000-0000-000000000000']))
        ->assertNotFound();
});

/* ------------------------------------------------------------------ */
/* Foto que adjunta el docente */
/* ------------------------------------------------------------------ */

it('la foto es opcional: sin ella el ticket se crea igual', function () {
    $this->get(route('teacher.start'));
    $this->post(route('teacher.confirm.store'), [
        'site_id' => (string) $this->room->floor->building->site_id,
        'building_id' => (string) $this->room->floor->building_id,
        'floor_id' => (string) $this->room->floor_id,
        'room_id' => (string) $this->room->id,
    ]);
    $this->post(route('teacher.category.store'), [
        'category_id' => (string) IncidentCategory::value('id'),
    ]);

    // Un docente con el aula esperando no puede quedar bloqueado porque la
    // cámara no abre.
    $this->post(route('teacher.escalate.store'), [
        'blocks_class' => '1', 'confirmed' => '1',
        'form_opened_at' => now()->subSeconds(30)->timestamp,
    ])->assertRedirect();

    expect(Incident::where('is_draft', false)->count())->toBe(1);
});

it('la foto se reprocesa y no se guarda el archivo original', function () {
    $incident = ($this->crear)();
    $ruta = app(ReporterPhotoStore::class)
        ->store(UploadedFile::fake()->image('problema.jpg', 4000, 3000), $incident->uuid);

    // El reprocesado elimina los metadatos EXIF, incluida la ubicación donde
    // se tomó la foto. Es la única subida que el sistema acepta sin
    // autenticar: la superficie más expuesta que hay.
    expect($ruta)->toEndWith('.webp')
        ->and($ruta)->not->toContain('problema.jpg');

    Storage::disk(config('incidencias.media.disk'))->assertExists($ruta);
});

it('la foto NO se sirve a cualquiera que adivine la dirección', function () {
    $incident = ($this->crear)(['reporter_photo_path' => 'media/reportes/x.webp']);

    // Puede mostrar una pizarra con nombres o una pantalla con datos: nadie
    // decidió publicar eso.
    $this->get(route('support.incidents.photo', $incident))->assertRedirect();

    $sinPermiso = User::factory()->create();
    $this->actingAs($sinPermiso)->get(route('support.incidents.photo', $incident))->assertForbidden();
});

it('devuelve 404 si la incidencia no tiene foto', function () {
    $incident = ($this->crear)();

    $this->actingAs($this->tecnico)->get(route('support.incidents.photo', $incident))->assertNotFound();
});

/* ------------------------------------------------------------------ */
/* Historial del aula */
/* ------------------------------------------------------------------ */

it('el historial dice qué falla y cómo se resolvió', function () {
    ($this->crear)([
        'resolution_type' => 'onsite',
        'resolution_notes' => 'Se cambió el cable HDMI',
        'resolved_at' => now()->subDay(),
    ]);

    // Es el dato que evita el segundo viaje: el técnico sale con la pieza
    // correcta en la mano.
    $this->actingAs($this->tecnico)->get(route('support.rooms.history', $this->room))
        ->assertOk()
        ->assertSee('Qué falla en esta aula')
        ->assertSee('Se cambió el cable HDMI')
        ->assertSee('requirió ir', false);
});

it('el historial exige permiso de ver incidencias', function () {
    $sinPermiso = User::factory()->create();

    $this->actingAs($sinPermiso)->get(route('support.rooms.history', $this->room))->assertForbidden();
});

it('un aula sin historial no revienta', function () {
    $this->actingAs($this->tecnico)->get(route('support.rooms.history', $this->room))
        ->assertOk()
        ->assertSee('Sin incidencias registradas todavía');
});
