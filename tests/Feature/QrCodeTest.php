<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Locations\Models\Building;
use App\Modules\Locations\Models\Floor;
use App\Modules\Locations\Models\Room;
use App\Modules\Locations\Models\Site;
use App\Modules\Locations\Services\QrCodeService;
use Database\Seeders\RoleSeeder;

/**
 * Integridad del QR (plan 17.4, D-3).
 *
 * Lo que se prueba NO es una firma: el QR no la lleva. Se prueba que es
 * GENERICO de verdad, es decir, que no depende del aula ni la identifica.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->qr = app(QrCodeService::class);
});

it('apunta a la entrada del docente y no a un aula', function () {
    $url = $this->qr->targetUrl();

    expect($url)->toBe(route('teacher.start'))
        ->and($url)->not->toContain('room')
        ->and($url)->not->toContain('aula');
});

it('no lleva firma, token ni parametros', function () {
    // Una URL firmada fija no seria un control de acceso: quien escanea el
    // cartel una vez conserva el enlace y puede compartirlo. La proteccion
    // vive en la creacion del ticket, no aqui (plan 16.1).
    $url = $this->qr->targetUrl();

    expect($url)->not->toContain('?')
        ->and($url)->not->toContain('signature')
        ->and($url)->not->toContain('token');
});

it('genera el MISMO codigo siempre', function () {
    // Si dos llamadas produjeran codigos distintos, el cartel impreso ayer
    // dejaria de coincidir con el sistema de hoy.
    expect($this->qr->svg())->toBe($this->qr->svg());
});

it('produce un SVG valido', function () {
    expect($this->qr->svg())
        ->toContain('<svg')
        ->toContain('</svg>');
});

it('produce un PNG valido', function () {
    // Firma de archivo PNG: los primeros bytes del formato.
    expect(substr($this->qr->png(), 0, 8))->toBe("\x89PNG\r\n\x1a\n");
});

it('el destino del QR lleva a una pantalla usable', function () {
    // Prueba de extremo a extremo del cartel: lo que hay detras del codigo
    // tiene que funcionar sin autenticacion. Un QR que apunta a un 404 o al
    // login seria inutil pegado en un aula.
    $site = Site::create(['code' => 'HYO', 'name' => 'Huancayo', 'is_active' => true]);
    $building = Building::create(['site_id' => $site->id, 'code' => 'C', 'name' => 'Pabellón C', 'is_active' => true]);
    $floor = Floor::create(['building_id' => $building->id, 'number' => 3, 'label' => 'Piso 3', 'is_active' => true]);
    Room::create(['floor_id' => $floor->id, 'code' => 'C305', 'criticality' => 1, 'is_active' => true]);

    $this->followingRedirects()
        ->get($this->qr->targetUrl())
        ->assertOk()
        ->assertSee('C305');
});

it('se comporta igual desde cualquier aula', function () {
    // El QR no guarda estado: la segunda lectura, hecha supuestamente en
    // otra aula, ofrece exactamente lo mismo que la primera.
    $site = Site::create(['code' => 'HYO', 'name' => 'Huancayo', 'is_active' => true]);
    $building = Building::create(['site_id' => $site->id, 'code' => 'A', 'name' => 'Pabellón A', 'is_active' => true]);
    $f1 = Floor::create(['building_id' => $building->id, 'number' => 1, 'label' => 'Piso 1', 'is_active' => true]);
    $f2 = Floor::create(['building_id' => $building->id, 'number' => 2, 'label' => 'Piso 2', 'is_active' => true]);
    Room::create(['floor_id' => $f1->id, 'code' => 'A101', 'criticality' => 1, 'is_active' => true]);
    Room::create(['floor_id' => $f2->id, 'code' => 'A201', 'criticality' => 1, 'is_active' => true]);

    $primera = $this->followingRedirects()->get($this->qr->targetUrl())->getContent();
    $segunda = $this->followingRedirects()->get($this->qr->targetUrl())->getContent();

    expect($primera)->toBe($segunda)
        ->and($primera)->toContain('Piso 1')
        ->and($primera)->toContain('Piso 2');
});

it('la pantalla del QR exige permisos de administracion', function () {
    $tecnico = User::factory()->create();
    $tecnico->assignRole('technician');

    $this->actingAs($tecnico)->get(route('admin.qr.show'))->assertForbidden();
});

it('el administrador ve el QR y el cartel imprimible', function () {
    $this->actingAs($this->admin)->get(route('admin.qr.show'))
        ->assertOk()
        ->assertSee('mismo código para todas las aulas', escape: false);

    $this->actingAs($this->admin)->get(route('admin.qr.poster'))
        ->assertOk()
        ->assertSee('Escanea este código');
});

it('descarga el QR como SVG y no como PNG', function () {
    // El cartel puede imprimirse en A4 o A3: un PNG de 400 px sale dentado
    // a tamano grande y algunos lectores fallan.
    $this->actingAs($this->admin)->get(route('admin.qr.download'))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml');
});
