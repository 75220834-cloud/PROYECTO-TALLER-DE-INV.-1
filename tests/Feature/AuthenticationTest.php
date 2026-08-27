<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Autenticacion del personal interno (plan 16.2).
 *
 * El docente NO pasa por aqui: escanea el QR y entra directo.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    RateLimiter::clear('correcto@demo.local|127.0.0.1');

    $this->user = User::factory()->create([
        'email' => 'correcto@demo.local',
        'password' => Hash::make('clave-de-prueba-123'),
    ]);
    $this->user->assignRole('technician');
});

it('muestra el formulario de acceso', function () {
    $this->get(route('login'))->assertOk()->assertSee('Entrar');
});

it('deja entrar con credenciales correctas', function () {
    $this->post(route('login.store'), [
        'email' => 'correcto@demo.local',
        'password' => 'clave-de-prueba-123',
    ])->assertRedirect(route('support.dashboard'));

    $this->assertAuthenticatedAs($this->user);
});

it('rechaza una contrasena incorrecta', function () {
    $this->post(route('login.store'), [
        'email' => 'correcto@demo.local',
        'password' => 'equivocada',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('NO revela si el correo existe', function () {
    // Distinguir "usuario no encontrado" de "contrasena incorrecta"
    // permitiria enumerar las cuentas del personal de soporte.
    $this->post(route('login.store'), ['email' => 'correcto@demo.local', 'password' => 'mala']);
    $mensajeConCuentaReal = session('errors')->first('email');

    session()->forget('errors');
    RateLimiter::clear('correcto@demo.local|127.0.0.1');
    RateLimiter::clear('inexistente@demo.local|127.0.0.1');

    $this->post(route('login.store'), ['email' => 'inexistente@demo.local', 'password' => 'mala']);
    $mensajeConCuentaFalsa = session('errors')->first('email');

    expect($mensajeConCuentaReal)->toBe($mensajeConCuentaFalsa);
});

it('bloquea tras varios intentos fallidos', function () {
    foreach (range(1, 5) as $ignored) {
        $this->post(route('login.store'), [
            'email' => 'correcto@demo.local',
            'password' => 'equivocada',
        ]);
    }

    $this->post(route('login.store'), [
        'email' => 'correcto@demo.local',
        'password' => 'clave-de-prueba-123',
    ])->assertSessionHasErrors('email');

    // Ni siquiera la contrasena CORRECTA entra durante el bloqueo.
    $this->assertGuest();
    expect(session('errors')->first('email'))->toContain('Demasiados intentos');
});

it('cuenta el bloqueo por correo mas IP, no solo por IP', function () {
    // En la red institucional muchos comparten IP de salida. Contar solo
    // por IP dejaria que un atacante bloqueara a todo el equipo de soporte
    // fallando adrede unas cuantas veces (mismo razonamiento que R18).
    foreach (range(1, 6) as $ignored) {
        $this->post(route('login.store'), ['email' => 'victima@demo.local', 'password' => 'x']);
    }

    // Otra cuenta desde la misma IP sigue pudiendo entrar.
    $this->post(route('login.store'), [
        'email' => 'correcto@demo.local',
        'password' => 'clave-de-prueba-123',
    ])->assertRedirect(route('support.dashboard'));

    $this->assertAuthenticatedAs($this->user);
});

it('permite cerrar sesion', function () {
    $this->actingAs($this->user)
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('redirige al login cuando se entra al panel sin sesion', function () {
    $this->get(route('support.dashboard'))->assertRedirect(route('login'));
});
