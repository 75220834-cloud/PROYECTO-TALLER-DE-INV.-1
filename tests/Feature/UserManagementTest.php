<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;

/**
 * Gestión de usuarios del panel (CU-A-05, del MVP).
 *
 * Hasta ahora las cuentas del personal solo podían crearse desde el seeder,
 * así que dar de alta a un técnico exigía tocar código y volver a sembrar.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(CatalogSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->coordinador = User::factory()->create();
    $this->coordinador->assignRole('coordinator');

    $this->datos = [
        'name' => 'Técnico Nuevo',
        'email' => 'nuevo@demo.local',
        'password' => 'contrasena-larga-1',
        'password_confirmation' => 'contrasena-larga-1',
        'role' => 'technician',
    ];
});

it('el administrador crea una cuenta con su rol', function () {
    $this->actingAs($this->admin)->post(route('admin.users.store'), $this->datos)
        ->assertRedirect(route('admin.users.index'));

    $user = User::where('email', 'nuevo@demo.local')->firstOrFail();

    expect($user->hasRole('technician'))->toBeTrue()
        // La contraseña se guarda hasheada, nunca en claro.
        ->and($user->password)->not->toBe('contrasena-larga-1');
});

it('un coordinador no puede administrar usuarios', function () {
    // Crear cuentas es una atribución administrativa: quien puede darse un
    // rol puede darse cualquiera.
    $this->actingAs($this->coordinador)->get(route('admin.users.index'))->assertForbidden();
});

it('exige una contraseña de al menos 10 caracteres', function () {
    $this->actingAs($this->admin)->post(route('admin.users.store'), [
        ...$this->datos, 'password' => 'corta', 'password_confirmation' => 'corta',
    ])->assertSessionHasErrors('password');

    expect(User::where('email', 'nuevo@demo.local')->exists())->toBeFalse();
});

it('exige que las dos contraseñas coincidan', function () {
    $this->actingAs($this->admin)->post(route('admin.users.store'), [
        ...$this->datos, 'password_confirmation' => 'otra-cosa-distinta',
    ])->assertSessionHasErrors('password');
});

it('no permite dos cuentas con el mismo correo', function () {
    $this->actingAs($this->admin)->post(route('admin.users.store'), $this->datos);
    $this->actingAs($this->admin)->post(route('admin.users.store'), $this->datos)
        ->assertSessionHasErrors('email');

    expect(User::where('email', 'nuevo@demo.local')->count())->toBe(1);
});

it('exige elegir un rol: una cuenta sin rol no podría hacer nada', function () {
    $sinRol = collect($this->datos)->except('role')->all();

    $this->actingAs($this->admin)->post(route('admin.users.store'), $sinRol)
        ->assertSessionHasErrors('role');
});

it('editar el nombre no reinicia la contraseña', function () {
    $this->actingAs($this->admin)->post(route('admin.users.store'), $this->datos);
    $user = User::where('email', 'nuevo@demo.local')->firstOrFail();
    $hash = $user->password;

    $this->actingAs($this->admin)->put(route('admin.users.update', $user), [
        'name' => 'Técnico Renombrado',
        'email' => $user->email,
        'role' => 'technician',
        'password' => '',
    ])->assertRedirect();

    expect($user->fresh()->name)->toBe('Técnico Renombrado')
        ->and($user->fresh()->password)->toBe($hash);
});

it('cambiar de rol reemplaza el anterior en lugar de acumularlo', function () {
    $this->actingAs($this->admin)->post(route('admin.users.store'), $this->datos);
    $user = User::where('email', 'nuevo@demo.local')->firstOrFail();

    $this->actingAs($this->admin)->put(route('admin.users.update', $user), [
        'name' => $user->name, 'email' => $user->email, 'role' => 'researcher',
    ]);

    // Acumular roles daría permisos que nadie concedió a propósito.
    expect($user->fresh()->getRoleNames()->all())->toBe(['researcher']);
});

it('audita el cambio de rol sin registrar la contraseña', function () {
    $this->actingAs($this->admin)->post(route('admin.users.store'), $this->datos);

    $log = DB::table('audit_logs')->where('action', 'user.created')->latest('id')->first();

    // Registrar credenciales en la auditoría las convierte en un dato
    // filtrable a través de una pantalla de solo lectura.
    expect($log)->not->toBeNull()
        ->and($log->changes)->toContain('technician')
        ->and($log->changes)->not->toContain('contrasena-larga-1');
});

it('explica por qué no se borran usuarios', function () {
    $this->actingAs($this->admin)->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee('No se borran usuarios');
});
