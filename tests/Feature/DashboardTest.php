<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Incidents\Models\AbuseRejection;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Incidents\Models\IncidentCategory;
use App\Modules\Incidents\Models\IncidentStatus as StatusModel;
use App\Modules\Locations\Models\Building;
use App\Modules\Locations\Models\Floor;
use App\Modules\Locations\Models\Room;
use App\Modules\Locations\Models\Site;
use App\Shared\Enums\IncidentStatus as S;
use App\Shared\Enums\ResolutionType;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;

/**
 * Tablero y exportacion del conjunto de datos (plan 15, 26.bis, CU-A-12).
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(CatalogSeeder::class);

    $site = Site::create(['code' => 'HYO', 'name' => 'Huancayo', 'is_active' => true]);
    $building = Building::create(['site_id' => $site->id, 'code' => 'C', 'name' => 'Pabellón C', 'is_active' => true]);
    $floor = Floor::create(['building_id' => $building->id, 'number' => 3, 'label' => 'Piso 3', 'is_active' => true]);
    $this->room = Room::create(['floor_id' => $floor->id, 'code' => 'C305', 'criticality' => 1, 'is_active' => true]);

    $this->category = IncidentCategory::where('code', 'PROJECTOR')->firstOrFail();

    $this->tecnico = User::factory()->create();
    $this->tecnico->assignRole('technician');

    $this->investigador = User::factory()->create();
    $this->investigador->assignRole('researcher');

    $this->make = function (array $attrs = []): Incident {
        return Incident::create(array_merge([
            'room_id' => $this->room->id,
            'category_id' => $this->category->id,
            'status_id' => StatusModel::idFor(S::Closed),
            'is_draft' => false,
            'confirmed_at' => now()->subMinutes(40),
            'reported_at' => now()->subMinutes(35),
            'resolved_at' => now()->subMinutes(10),
            'resolution_type' => ResolutionType::Assistant->value,
            'device_key' => 'dispositivo-secreto',
            'ip_hash' => 'hash-de-ip-secreto',
            'reporter_hint' => 'Profesor Contacto',
        ], $attrs));
    };
});

it('exige el permiso dashboard.view', function () {
    $sinPermiso = User::factory()->create();

    $this->actingAs($this->tecnico)->get(route('support.dashboard'))->assertOk();
    $this->actingAs($sinPermiso)->get(route('support.dashboard'))->assertForbidden();
});

it('muestra la autonomía de resolución, que es el indicador central', function () {
    ($this->make)();
    ($this->make)(['resolution_type' => ResolutionType::Onsite->value]);

    $this->actingAs($this->tecnico)->get(route('support.dashboard'))
        ->assertOk()
        ->assertSee('Autonomía de resolución')
        ->assertSee('50%'); // 1 de 2 resueltas sin desplazamiento
});

it('muestra también los indicadores incómodos: abandono y rechazos', function () {
    // Un borrador que nadie promovió: el coste real del QR genérico.
    ($this->make)([
        'is_draft' => true, 'status_id' => StatusModel::idFor(S::Draft),
        'reported_at' => null, 'resolved_at' => null, 'resolution_type' => null,
    ]);
    ($this->make)();

    AbuseRejection::create([
        'room_id' => $this->room->id,
        'category_id' => $this->category->id,
        'reason' => 'ip_rate',
        'occurred_at' => now(),
    ]);

    $this->actingAs($this->tecnico)->get(route('support.dashboard'))
        ->assertOk()
        ->assertSee('Fricción de acceso')
        ->assertSee('Solicitudes rechazadas')
        // Un tablero que solo enseña lo que salió bien no sirve para decidir.
        ->assertSee('Demasiadas solicitudes desde la misma red');
});

it('acota el periodo consultable para que no se pueda barrer toda la base desde la URL', function () {
    $this->actingAs($this->tecnico)->get(route('support.dashboard', ['days' => 99999]))->assertOk();
    $this->actingAs($this->tecnico)->get(route('support.dashboard', ['days' => -5]))->assertOk();
});

it('solo exporta el conjunto de datos con el permiso reports.export', function () {
    $this->actingAs($this->tecnico)->get(route('support.dashboard.export'))->assertForbidden();
    $this->actingAs($this->investigador)->get(route('support.dashboard.export'))->assertOk();
});

it('no saca del sistema los identificadores de origen ni el contacto del docente', function () {
    ($this->make)();

    $response = $this->actingAs($this->investigador)->get(route('support.dashboard.export'))->assertOk();
    $csv = $response->streamedContent();

    // device_key e ip_hash sirven al antiabuso DENTRO del sistema; sacarlos
    // aquí sería mover identificadores de origen a una hoja que después
    // circula por correo. reporter_hint es un dato de contacto que el docente
    // dio para que soporte lo ubicara, no para acabar en un conjunto de datos.
    expect($csv)->not->toContain('dispositivo-secreto')
        ->and($csv)->not->toContain('hash-de-ip-secreto')
        ->and($csv)->not->toContain('Profesor Contacto')
        ->and($csv)->toContain('C305')
        ->and($csv)->toStartWith("\xEF\xBB\xBF"); // BOM: sin él Excel rompe las tildes
});

it('deja constancia de quién exportó los datos', function () {
    $this->actingAs($this->investigador)->get(route('support.dashboard.export'))->assertOk();

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $this->investigador->id,
        'action' => 'research.dataset_exported',
    ]);
});

it('no incluye borradores abandonados en el conjunto de datos', function () {
    ($this->make)([
        'is_draft' => true, 'status_id' => StatusModel::idFor(S::Draft),
        'reported_description' => 'ESTE ES UN BORRADOR',
    ]);

    $response = $this->actingAs($this->investigador)->get(route('support.dashboard.export'))->assertOk();

    expect($response->streamedContent())->not->toContain('ESTE ES UN BORRADOR');
});
