<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Incidents\Models\AbuseRejection;
use App\Modules\Incidents\Models\IncidentCategory;
use App\Modules\Locations\Models\Building;
use App\Modules\Locations\Models\Floor;
use App\Modules\Locations\Models\Room;
use App\Modules\Locations\Models\Site;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;

/**
 * Consulta de auditoría y de rechazos del antiabuso (CU-A-11 y CU-A-06b).
 *
 * Ambos son del MVP y no existían: los datos se guardaban desde la fase 3,
 * pero nadie podía verlos sin abrir phpMyAdmin.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(CatalogSeeder::class);

    $site = Site::create(['code' => 'HYO', 'name' => 'Huancayo', 'is_active' => true]);
    $building = Building::create(['site_id' => $site->id, 'code' => 'C', 'name' => 'Pabellón C', 'is_active' => true]);
    $floor = Floor::create(['building_id' => $building->id, 'number' => 3, 'label' => 'Piso 3', 'is_active' => true]);
    $this->room = Room::create(['floor_id' => $floor->id, 'code' => 'C305', 'criticality' => 1, 'is_active' => true]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->coordinador = User::factory()->create();
    $this->coordinador->assignRole('coordinator');

    $this->tecnico = User::factory()->create();
    $this->tecnico->assignRole('technician');
});

it('el administrador ve quién cambió la configuración', function () {
    $this->actingAs($this->admin)->post(route('admin.sites.store'), [
        'code' => 'OTRA', 'name' => 'Otra sede', 'is_active' => '1',
    ]);

    $this->actingAs($this->admin)->get(route('admin.audit.index'))
        ->assertOk()
        ->assertSee($this->admin->name);
});

it('un técnico no puede consultar la auditoría', function () {
    // Ver quién cambió la configuración es una atribución administrativa.
    $this->actingAs($this->tecnico)->get(route('admin.audit.index'))->assertForbidden();
});

it('no muestra ninguna dirección IP, ni siquiera hasheada', function () {
    $this->actingAs($this->admin)->post(route('admin.sites.store'), [
        'code' => 'OTRA', 'name' => 'Otra sede', 'is_active' => '1',
    ]);

    $hash = DB::table('audit_logs')->latest('id')->value('ip_hash');

    // El hash sirve para agrupar en consultas, no para enseñarlo en pantalla:
    // un identificador de origen a la vista invita a tratarlo como si
    // identificara a alguien.
    $this->actingAs($this->admin)->get(route('admin.audit.index'))
        ->assertOk()
        ->assertDontSee($hash);
});

it('muestra los rechazos del antiabuso agrupados por motivo', function () {
    foreach (['ip_rate', 'ip_rate', 'duplicate'] as $reason) {
        AbuseRejection::create([
            'room_id' => $this->room->id,
            'category_id' => IncidentCategory::value('id'),
            'reason' => $reason,
            'occurred_at' => now(),
        ]);
    }

    $this->actingAs($this->coordinador)->get(route('admin.audit.rejections'))
        ->assertOk()
        ->assertSee('Demasiadas solicitudes desde la misma red')
        ->assertSee('Ya había una solicitud igual abierta')
        ->assertSee('C305');
});

it('explica que muchos rechazos por red significan umbral mal puesto', function () {
    // Es la razón de ser de la pantalla: los umbrales son provisionales y hay
    // que corregirlos con datos, no por intuición (riesgo R18).
    $this->actingAs($this->coordinador)->get(route('admin.audit.rejections'))
        ->assertOk()
        ->assertSee('no es un ataque', false)
        ->assertSee('Umbrales vigentes');
});

it('muestra los umbrales vigentes para no tener que abrir el .env', function () {
    $this->actingAs($this->coordinador)->get(route('admin.audit.rejections'))
        ->assertOk()
        ->assertSee('max_active_per_room');
});

it('acota el periodo consultable', function () {
    $this->actingAs($this->coordinador)->get(route('admin.audit.rejections', ['days' => 99999]))->assertOk();
    $this->actingAs($this->coordinador)->get(route('admin.audit.rejections', ['days' => -3]))->assertOk();
});
