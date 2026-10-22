<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Assistant\Services\IncidentSuggestions;
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
 * Sugerencias de la IA en la ficha de cada incidencia.
 *
 * Antes el técnico abría un ticket y no veía nada que viniera de la IA: el
 * panel parecía un sistema de tickets cualquiera. Ahora ve los casos
 * parecidos que ya se resolvieron —y cómo— y la guía que mejor habla del
 * problema. Son sugerencias: no deciden nada.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(CatalogSeeder::class);

    $site = Site::create(['code' => 'HYO', 'name' => 'Huancayo', 'is_active' => true]);
    $building = Building::create(['site_id' => $site->id, 'code' => 'C', 'name' => 'Pabellón C', 'is_active' => true]);
    $floor = Floor::create(['building_id' => $building->id, 'number' => 3, 'label' => 'Piso 3', 'is_active' => true]);
    $this->c305 = Room::create(['floor_id' => $floor->id, 'code' => 'C305', 'criticality' => 1, 'is_active' => true]);
    $this->c306 = Room::create(['floor_id' => $floor->id, 'code' => 'C306', 'criticality' => 1, 'is_active' => true]);

    $this->proyector = IncidentCategory::where('code', 'PROJECTOR')->firstOrFail();
    $this->parlante = IncidentCategory::where('code', 'SPEAKER')->firstOrFail();

    $this->tecnico = User::factory()->create();
    $this->tecnico->assignRole('technician');

    $this->incidencia = function (Room $room, IncidentCategory $categoria, string $texto, ?string $solucion = null): Incident {
        return Incident::create([
            'room_id' => $room->id,
            'category_id' => $categoria->id,
            'status_id' => StatusModel::idFor($solucion !== null ? S::Closed : S::New),
            'ticket_number' => (string) random_int(100000, 999999),
            'is_draft' => false,
            'reported_description' => $texto,
            'reported_at' => now()->subDay(),
            'resolved_at' => $solucion !== null ? now()->subHours(20) : null,
            'resolution_type' => $solucion !== null ? ResolutionType::Onsite->value : null,
            'resolution_notes' => $solucion,
        ]);
    };

    $this->actual = ($this->incidencia)($this->c305, $this->proyector, 'el proyector no muestra imagen');
});

it('muestra los casos del mismo problema que ya se resolvieron y cómo', function () {
    ($this->incidencia)($this->c306, $this->proyector, 'no se ve nada en el ecran', 'Se cambió el cable HDMI.');
    ($this->incidencia)($this->c306, $this->parlante, 'no suena el parlante', 'Se subió el volumen del parlante.');
    ($this->incidencia)($this->c306, $this->proyector, 'el proyector parpadea'); // sin resolver

    $this->actingAs($this->tecnico)->get(route('support.incidents.suggestions', $this->actual))
        ->assertOk()
        ->assertSee('Se cambió el cable HDMI.')
        ->assertDontSee('Se subió el volumen del parlante.')
        ->assertDontSee('el proyector parpadea');
});

it('a igual parecido, gana el caso de la misma aula', function () {
    // El parecido del problema pesa más que el aula; el aula desempata.
    ($this->incidencia)($this->c306, $this->proyector, 'la imagen no se ve', 'Se ajustó la entrada con Source.');
    ($this->incidencia)($this->c305, $this->proyector, 'la imagen no se ve', 'Se cambió el cable HDMI del aula.');

    $casos = app(IncidentSuggestions::class)->for($this->actual)->cases;

    expect($casos->first()['incident']->room_id)->toBe($this->c305->id)
        ->and($casos->first()['sameRoom'])->toBeTrue();
});

it('sin casos ni guías lo dice, en lugar de dejar la tarjeta vacía', function () {
    $this->actingAs($this->tecnico)->get(route('support.incidents.suggestions', $this->actual))
        ->assertOk()
        ->assertSee('Todavía no hay casos resueltos de este tipo')
        ->assertSee('Ninguna guía habla de este problema');
});

it('la ficha de la incidencia pide las sugerencias sin hacer esperar la página', function () {
    // Se cargan aparte: si la IA tarda, la ficha ya está a la vista.
    $this->actingAs($this->tecnico)->get(route('support.incidents.show', $this->actual))
        ->assertOk()
        ->assertSee('data-sugerencias="'.route('support.incidents.suggestions', $this->actual).'"', false);
});

it('exige el permiso de ver incidencias', function () {
    $sinPermiso = User::factory()->create();

    $this->actingAs($sinPermiso)->get(route('support.incidents.suggestions', $this->actual))->assertForbidden();
});
