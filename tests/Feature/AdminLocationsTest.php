<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Equipment\Models\EquipmentType;
use App\Modules\Locations\Models\Building;
use App\Modules\Locations\Models\Floor;
use App\Modules\Locations\Models\Room;
use App\Modules\Locations\Models\Site;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;

/**
 * Administracion del catalogo: permisos, integridad y guarda de
 * dependencias (plan 39, 40 y 17.2).
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(CatalogSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->technician = User::factory()->create();
    $this->technician->assignRole('technician');

    $this->researcher = User::factory()->create();
    $this->researcher->assignRole('researcher');

    $this->site = Site::create(['code' => 'HYO', 'name' => 'Huancayo', 'is_active' => true]);
    $this->building = Building::create(['site_id' => $this->site->id, 'code' => 'C', 'name' => 'Pabellón C', 'is_active' => true]);
    $this->floor = Floor::create(['building_id' => $this->building->id, 'number' => 3, 'label' => 'Piso 3', 'is_active' => true]);
    $this->room = Room::create(['floor_id' => $this->floor->id, 'code' => 'C305', 'criticality' => 1, 'is_active' => true]);
});

// ---------------------------------------------------------------- permisos

it('exige autenticacion para entrar al panel', function () {
    $this->get(route('admin.rooms.index'))->assertRedirect(route('login'));
});

it('permite al administrador gestionar el catalogo', function () {
    $this->actingAs($this->admin)->get(route('admin.rooms.index'))->assertOk();
});

it('IMPIDE al tecnico administrar el catalogo', function () {
    // No es desconfianza: un cambio accidental en el catalogo de aulas
    // afecta a TODOS los tickets, incluidos los cerrados que la
    // investigacion va a analizar (plan RoleSeeder).
    $this->actingAs($this->technician)->get(route('admin.rooms.index'))->assertForbidden();
    $this->actingAs($this->technician)->post(route('admin.rooms.store'), [])->assertForbidden();
});

it('IMPIDE al investigador modificar nada', function () {
    // El investigador observa el proceso, no participa en el. Si pudiera
    // escribir, sus acciones se mezclarian con las de soporte en la
    // auditoria y contaminarian las metricas del piloto.
    $this->actingAs($this->researcher)->post(route('admin.rooms.store'), [])->assertForbidden();
    $this->actingAs($this->researcher)->get(route('admin.equipment.create'))->assertForbidden();
});

it('permite al tecnico consultar equipos pero no crearlos', function () {
    $this->actingAs($this->technician)->get(route('admin.equipment.index'))->assertOk();
    $this->actingAs($this->technician)->get(route('admin.equipment.create'))->assertForbidden();
});

// ------------------------------------------------------------- integridad

it('rechaza crear un aula en un piso inexistente', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.rooms.store'), [
            'floor_id' => 999999,
            'code' => 'X999',
            'criticality' => 1,
        ])
        ->assertSessionHasErrors('floor_id');

    expect(Room::where('code', 'X999')->exists())->toBeFalse();
});

it('rechaza dos aulas con el mismo codigo en el mismo piso', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.rooms.store'), [
            'floor_id' => $this->floor->id,
            'code' => 'C305',
            'criticality' => 1,
        ])
        ->assertSessionHasErrors('code');
});

it('permite el mismo codigo de aula en pisos distintos', function () {
    // No es un descuido: dos pabellones pueden tener cada uno su aula "101".
    // La unicidad es por piso, no global.
    $otroPiso = Floor::create(['building_id' => $this->building->id, 'number' => 4, 'label' => 'Piso 4', 'is_active' => true]);

    $this->actingAs($this->admin)
        ->post(route('admin.rooms.store'), [
            'floor_id' => $otroPiso->id,
            'code' => 'C305',
            'criticality' => 1,
        ])
        ->assertSessionHasNoErrors();

    expect(Room::where('code', 'C305')->count())->toBe(2);
});

it('acepta un codigo de aula fuera de nomenclatura', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.rooms.store'), [
            'floor_id' => $this->floor->id,
            'code' => 'LAB-2',
            'name' => 'Laboratorio 2',
            'criticality' => 3,
        ])
        ->assertSessionHasNoErrors();

    expect(Room::where('code', 'LAB-2')->exists())->toBeTrue();
});

it('rechaza un piso duplicado en el mismo pabellon', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.floors.store'), [
            'building_id' => $this->building->id,
            'number' => 3,
            'label' => 'Otro piso 3',
        ])
        ->assertSessionHasErrors('number');
});

it('acepta pisos negativos para sotanos', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.floors.store'), [
            'building_id' => $this->building->id,
            'number' => -1,
            'label' => 'Sótano',
        ])
        ->assertSessionHasNoErrors();

    expect(Floor::where('number', -1)->exists())->toBeTrue();
});

// ------------------------------------------------- guarda de dependencias

it('IMPIDE eliminar una sede que tiene pabellones', function () {
    $this->actingAs($this->admin)
        ->delete(route('admin.sites.destroy', $this->site))
        ->assertSessionHas('error');

    expect(Site::find($this->site->id))->not->toBeNull();
});

it('IMPIDE eliminar un aula que tiene equipos', function () {
    $type = EquipmentType::first();
    Equipment::create([
        'room_id' => $this->room->id,
        'equipment_type_id' => $type->id,
        'asset_code' => 'EQ-1',
        'status' => 'operational',
        'is_active' => true,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('admin.rooms.destroy', $this->room))
        ->assertSessionHas('error');

    expect(Room::find($this->room->id))->not->toBeNull();
});

it('explica POR QUE no se puede eliminar y ofrece la alternativa', function () {
    // Un "no se puede" sin motivo deja al administrador pensando que la
    // interfaz esta rota.
    $response = $this->actingAs($this->admin)->delete(route('admin.sites.destroy', $this->site));

    expect(session('error'))
        ->toContain('pabellones asociados')
        ->toContain('Desactívalo');
});

it('permite eliminar un aula sin dependencias', function () {
    $this->actingAs($this->admin)
        ->delete(route('admin.rooms.destroy', $this->room))
        ->assertSessionHas('status');

    expect(Room::find($this->room->id))->toBeNull();
});

it('permite desactivar siempre, aunque tenga dependencias', function () {
    // Desactivar es reversible y no pierde nada: es la operacion que
    // soporte usara a diario y no debe tener friccion.
    $this->actingAs($this->admin)
        ->patch(route('admin.sites.toggle', $this->site))
        ->assertSessionHas('status');

    expect($this->site->fresh()->is_active)->toBeFalse();
});

it('avisa cuantas aulas quedan fuera de servicio al desactivar un pabellon', function () {
    // La consecuencia no se ve desde la pantalla del pabellon: desactivarlo
    // deja fuera todas sus aulas sin tocar ni una fila de aulas.
    $this->actingAs($this->admin)->patch(route('admin.buildings.toggle', $this->building));

    expect(session('status'))->toContain('1 aula');
});

it('registra en auditoria los cambios del catalogo', function () {
    $this->actingAs($this->admin)->patch(route('admin.rooms.toggle', $this->room));

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'locations.toggle_active',
        'user_id' => $this->admin->id,
    ]);
});
