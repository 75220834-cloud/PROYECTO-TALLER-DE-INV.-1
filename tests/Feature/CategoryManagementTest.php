<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Incidents\Models\IncidentCategory;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;

/**
 * Catálogo de categorías (CU-A-04, del MVP).
 *
 * Es lo que el docente ve en la pantalla más usada del sistema, y hasta
 * ahora solo podía cambiarse desde un seeder.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(CatalogSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->tecnico = User::factory()->create();
    $this->tecnico->assignRole('technician');
});

it('el administrador crea una categoría', function () {
    $this->actingAs($this->admin)->post(route('admin.categories.store'), [
        'code' => 'VENTILACION',
        'name' => 'Ventilación',
        'teacher_label' => 'Hace mucho calor',
        'sort_order' => 5,
    ])->assertRedirect(route('admin.categories.index'));

    $categoria = IncidentCategory::where('code', 'VENTILACION')->firstOrFail();

    expect($categoria->teacherText())->toBe('Hace mucho calor')
        ->and($categoria->is_active)->toBeTrue();
});

it('un técnico no puede tocar el catálogo de categorías', function () {
    // Cambiar una categoría afecta a TODOS los tickets, igual que cambiar un
    // aula. No es algo que se haga entre atenciones.
    $this->actingAs($this->tecnico)->get(route('admin.categories.index'))->assertForbidden();
});

it('NO deja cambiar el código de una categoría existente', function () {
    $categoria = IncidentCategory::where('code', 'PROJECTOR')->firstOrFail();

    $this->actingAs($this->admin)->put(route('admin.categories.update', $categoria), [
        'code' => 'OTRO_CODIGO',
        'name' => 'Proyector multimedia',
    ])->assertRedirect();

    // El nombre sí cambia; el código no. Cambiarlo dejaría huérfanos los
    // tickets ya cerrados que el análisis va a leer.
    expect($categoria->fresh()->code)->toBe('PROJECTOR')
        ->and($categoria->fresh()->name)->toBe('Proyector multimedia');
});

it('rechaza un código con formato inválido', function () {
    $this->actingAs($this->admin)->post(route('admin.categories.store'), [
        'code' => 'con minúsculas y espacios',
        'name' => 'Algo',
    ])->assertSessionHasErrors('code');
});

it('no permite dos categorías con el mismo código', function () {
    $this->actingAs($this->admin)->post(route('admin.categories.store'), [
        'code' => 'PROJECTOR', 'name' => 'Duplicada',
    ])->assertSessionHasErrors('code');
});

it('ocultar una categoría la quita del docente pero no del histórico', function () {
    $categoria = IncidentCategory::where('code', 'PROJECTOR')->firstOrFail();

    $this->actingAs($this->admin)->patch(route('admin.categories.toggle', $categoria))->assertRedirect();

    expect($categoria->fresh()->is_active)->toBeFalse()
        // Sigue existiendo: las incidencias antiguas la conservan.
        ->and(IncidentCategory::where('code', 'PROJECTOR')->exists())->toBeTrue();

    // Y desaparece de la pantalla del docente.
    expect(IncidentCategory::active()->pluck('code'))->not->toContain('PROJECTOR');
});

it('el orden de la lista es el orden que ve el docente', function () {
    $this->actingAs($this->admin)->post(route('admin.categories.store'), [
        'code' => 'PRIMERA', 'name' => 'La primera', 'sort_order' => 0,
    ]);

    $primera = IncidentCategory::active()->orderBy('sort_order')->first();

    expect($primera->code)->toBe('PRIMERA');
});

it('explica que las categorías no se borran', function () {
    $this->actingAs($this->admin)->get(route('admin.categories.index'))
        ->assertOk()
        ->assertSee('Las categorías no se borran');
});
