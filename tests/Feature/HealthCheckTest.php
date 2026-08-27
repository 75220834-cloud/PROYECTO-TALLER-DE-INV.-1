<?php

declare(strict_types=1);

/**
 * Endpoint de estado (plan 20.4).
 */
it('responde sin autenticación', function () {
    $this->get(route('health'))
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonStructure(['status', 'checks' => ['database', 'queue', 'llm', 'storage'], 'time']);
});

it('no se declara caído porque falte el modelo de lenguaje', function () {
    config()->set('incidencias.llm.provider', 'null');

    // Sin modelo el sistema funciona completo en modo determinista. Marcarlo
    // como caído haría sonar una alarma por algo que no impide a ningún
    // docente reportar nada — y una alarma que suena sin motivo se silencia
    // justo antes de la vez que sí importaba.
    $this->get(route('health'))
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('checks.llm', 'unavailable');
});

it('no revela nada sobre la instalación', function () {
    $cuerpo = $this->get(route('health'))->getContent();

    // Un endpoint público que describe versiones, rutas o nombres de base de
    // datos es un regalo para quien esté explorando el servidor.
    expect($cuerpo)->not->toContain(config('database.connections.mysql.database'))
        ->and($cuerpo)->not->toContain(base_path())
        ->and($cuerpo)->not->toContain(app()->version())
        ->and($cuerpo)->not->toContain(PHP_VERSION);
});
