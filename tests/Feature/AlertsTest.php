<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Incidents\Models\IncidentCategory;
use App\Modules\Incidents\Models\IncidentPriority;
use App\Modules\Incidents\Models\IncidentStatus as StatusModel;
use App\Modules\Locations\Models\Building;
use App\Modules\Locations\Models\Floor;
use App\Modules\Locations\Models\Room;
use App\Modules\Locations\Models\Site;
use App\Shared\Enums\IncidentStatus as S;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;

/**
 * Aviso de incidencias nuevas al panel (plan 12, CU-S-02).
 *
 * Sin esto, una incidencia urgente espera a que alguien recargue la bandeja
 * — y al otro lado hay un docente de pie frente a su clase. Además, ese
 * tiempo muerto ensucia el «tiempo hasta la primera respuesta», que es el
 * indicador central de la investigación.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(CatalogSeeder::class);

    $site = Site::create(['code' => 'HYO', 'name' => 'Huancayo', 'is_active' => true]);
    $building = Building::create(['site_id' => $site->id, 'code' => 'C', 'name' => 'Pabellón C', 'is_active' => true]);
    $floor = Floor::create(['building_id' => $building->id, 'number' => 3, 'label' => 'Piso 3', 'is_active' => true]);
    $this->room = Room::create(['floor_id' => $floor->id, 'code' => 'C305', 'criticality' => 1, 'is_active' => true]);

    $this->tecnico = User::factory()->create();
    $this->tecnico->assignRole('technician');

    $this->crear = function (array $extra = []): Incident {
        return Incident::create(array_merge([
            'room_id' => $this->room->id,
            'category_id' => IncidentCategory::value('id'),
            'status_id' => StatusModel::idFor(S::New),
            'is_draft' => false,
            'ticket_number' => 'T-'.random_int(1000, 9999),
            'confirmed_at' => now(),
            'reported_at' => now(),
        ], $extra));
    };
});

it('avisa de una incidencia que entró después del último vistazo', function () {
    $desde = now()->subMinutes(5)->toIso8601String();
    ($this->crear)();

    $this->actingAs($this->tecnico)->getJson(route('support.alerts', ['desde' => $desde]))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('incidencias.0.aula', 'C305');
});

it('NO repite las que el técnico ya vio', function () {
    ($this->crear)(['reported_at' => now()->subMinutes(10)]);

    // El panel manda la marca de su última consulta: lo anterior a eso ya
    // se avisó y volver a hacerlo entrenaría al técnico a ignorar el aviso.
    $this->actingAs($this->tecnico)->getJson(route('support.alerts', ['desde' => now()->subMinutes(2)->toIso8601String()]))
        ->assertOk()
        ->assertJsonPath('total', 0);
});

it('cuenta aparte las urgentes', function () {
    ($this->crear)(['hazard_reported' => true]);
    ($this->crear)();

    // No es lo mismo un proyector en un aula vacía que un equipo echando
    // humo: el panel da un aviso distinto para cada caso.
    $this->actingAs($this->tecnico)->getJson(route('support.alerts', ['desde' => now()->subMinutes(5)->toIso8601String()]))
        ->assertOk()
        ->assertJsonPath('total', 2)
        ->assertJsonPath('criticas', 1);
});

it('marca como urgente también la prioridad crítica', function () {
    ($this->crear)(['priority_id' => IncidentPriority::where('code', 'CRITICAL')->value('id')]);

    $this->actingAs($this->tecnico)->getJson(route('support.alerts', ['desde' => now()->subMinutes(5)->toIso8601String()]))
        ->assertJsonPath('criticas', 1);
});

it('no avisa de borradores abandonados', function () {
    ($this->crear)(['is_draft' => true, 'status_id' => StatusModel::idFor(S::Draft)]);

    // Un docente que entró y se fue no es una incidencia: avisar de eso
    // llenaría el panel de ruido.
    $this->actingAs($this->tecnico)->getJson(route('support.alerts', ['desde' => now()->subMinutes(5)->toIso8601String()]))
        ->assertJsonPath('total', 0);
});

it('acota la marca de tiempo que manda el navegador', function () {
    ($this->crear)(['reported_at' => now()->subDays(30)]);

    // El parámetro viene del cliente. Sin tope, una marca manipulada haría
    // recorrer la tabla entera de incidencias en cada sondeo — y el panel
    // sondea cada 20 segundos.
    $this->actingAs($this->tecnico)->getJson(route('support.alerts', ['desde' => '1990-01-01T00:00:00Z']))
        ->assertOk()
        ->assertJsonPath('total', 0);
});

it('aguanta una marca de tiempo con basura sin reventar', function () {
    $this->actingAs($this->tecnico)->getJson(route('support.alerts', ['desde' => 'no-es-una-fecha']))
        ->assertOk();
});

it('NO expone la descripción libre ni el contacto del docente', function () {
    ($this->crear)([
        'reported_description' => 'ESTO ES TEXTO PRIVADO DEL DOCENTE',
        'reporter_hint' => 'Profesor Contacto',
    ]);

    $respuesta = $this->actingAs($this->tecnico)
        ->getJson(route('support.alerts', ['desde' => now()->subMinutes(5)->toIso8601String()]))
        ->assertOk();

    // Es un endpoint que se llama cientos de veces al día. No tiene por qué
    // pasear datos que nadie va a leer en un aviso.
    expect($respuesta->getContent())
        ->not->toContain('ESTO ES TEXTO PRIVADO')
        ->and($respuesta->getContent())->not->toContain('Profesor Contacto');
});

it('exige permiso para consultar las alertas', function () {
    $sinPermiso = User::factory()->create();

    $this->actingAs($sinPermiso)->getJson(route('support.alerts'))->assertForbidden();

    // Sin sesión tampoco pasa. Lo que se comprueba es lo que importa —que
    // no salgan datos—, no el código exacto: da 403 porque el permiso se
    // evalúa antes que la sesión, y ninguno de los dos deja pasar.
    $respuesta = $this->getJson(route('support.alerts'));

    expect($respuesta->status())->toBeGreaterThanOrEqual(400)
        ->and($respuesta->getContent())->not->toContain('incidencias');
});

it('el panel trae el aviso montado en todas sus pantallas', function () {
    // El técnico puede estar en cualquier página cuando entra una
    // incidencia: si el aviso solo viviera en la bandeja, no serviría.
    $this->actingAs($this->tecnico)->get(route('support.dashboard'))
        ->assertOk()
        ->assertSee('data-alertas', false);
});
