<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Locations\Models\Building;
use App\Modules\Locations\Models\Floor;
use App\Modules\Locations\Models\Room;
use App\Modules\Locations\Models\Site;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\File;

/**
 * Endurecimiento (plan 16.2, fase 9).
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(CatalogSeeder::class);

    $site = Site::create(['code' => 'HYO', 'name' => 'Huancayo', 'is_active' => true]);
    $building = Building::create(['site_id' => $site->id, 'code' => 'C', 'name' => 'Pabellón C', 'is_active' => true]);
    $floor = Floor::create(['building_id' => $building->id, 'number' => 3, 'label' => 'Piso 3', 'is_active' => true]);
    Room::create(['floor_id' => $floor->id, 'code' => 'C305', 'criticality' => 1, 'is_active' => true]);
});

it('envía las cabeceras de seguridad también en la zona pública del docente', function () {
    // La parte pública es justamente la que recibe entrada de alguien sin
    // autenticar: si algún sitio necesita estas cabeceras, es este.
    // Con una sola sede la pantalla se omite y hay redireccion: se sigue
    // hasta la pagina real, que es la que el docente ve de verdad.
    $response = $this->followingRedirects()->get(route('teacher.start'))->assertOk();

    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
        ->and($response->headers->get('Content-Security-Policy'))->not->toBeNull();
});

it('la política de contenido no permite JavaScript incrustado', function () {
    $csp = $this->followingRedirects()->get(route('teacher.start'))->headers->get('Content-Security-Policy');

    // Con 'unsafe-inline' el navegador no puede distinguir nuestro script del
    // que inyecte un atacante, y la CSP dejaría de proteger de nada.
    $scriptSrc = collect(explode(';', (string) $csp))
        ->map(fn (string $d): string => trim($d))
        ->first(fn (string $d): bool => str_starts_with($d, 'script-src'));

    expect($scriptSrc)->not->toContain("'unsafe-inline'")
        ->and($scriptSrc)->not->toContain("'unsafe-eval'")
        ->and($csp)->toContain("object-src 'none'")
        ->and($csp)->toContain("frame-ancestors 'none'")
        ->and($csp)->toContain("form-action 'self'");
});

it('ninguna vista trae manejadores de eventos incrustados', function () {
    // Esta prueba es la que mantiene honesta a la CSP: en cuanto alguien
    // añada un onclick="", la política tendría que abrirse y dejaría de
    // servir. Falla aquí, no en producción.
    $offenders = [];

    foreach (File::allFiles(resource_path('views')) as $file) {
        if (preg_match_all('/\son(click|change|submit|load|error|focus|blur|input)\s*=/i', $file->getContents(), $m)) {
            $offenders[] = $file->getRelativePathname().' ('.implode(', ', $m[0]).')';
        }
    }

    expect($offenders)->toBe([]);
});

it('bloquea el login tras varios intentos fallidos', function () {
    $user = User::factory()->create(['email' => 'tecnico@demo.local']);
    $user->assignRole('technician');

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('login.store'), ['email' => 'tecnico@demo.local', 'password' => 'incorrecta']);
    }

    $this->post(route('login.store'), ['email' => 'tecnico@demo.local', 'password' => 'incorrecta'])
        ->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toContain('Demasiados intentos');
});

it('no revela si un correo existe o no', function () {
    User::factory()->create(['email' => 'existe@demo.local']);

    $this->post(route('login.store'), ['email' => 'existe@demo.local', 'password' => 'incorrecta']);
    $conCuenta = session('errors')->first('email');

    session()->flush();

    $this->post(route('login.store'), ['email' => 'noexiste@demo.local', 'password' => 'incorrecta']);
    $sinCuenta = session('errors')->first('email');

    // Distinguir los dos casos permitiría enumerar las cuentas del personal.
    expect($conCuenta)->toBe($sinCuenta);
});

it('no guarda ninguna dirección IP en claro', function () {
    // El antiabuso necesita agrupar por origen, no saber quién es. Se guarda
    // un HMAC; una IP legible sería un dato personal que el sistema no
    // necesita retener (plan 16.4).
    foreach (['incidents', 'incident_abuse_rejections', 'audit_logs'] as $tabla) {
        expect(Schema::hasColumn($tabla, 'ip_hash'))->toBeTrue()
            ->and(Schema::hasColumn($tabla, 'ip_address'))->toBeFalse();
    }
});

it('guarda hasheada también la IP de la auditoría administrativa', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->post(route('admin.sites.store'), [
        'code' => 'NUEVA', 'name' => 'Sede nueva', 'is_active' => '1',
    ]);

    $log = DB::table('audit_logs')->latest('id')->first();

    expect($log)->not->toBeNull()
        // Un HMAC-SHA256 en hexadecimal: 64 caracteres y ni rastro de puntos.
        ->and($log->ip_hash)->toHaveLength(64)
        ->and($log->ip_hash)->not->toContain('.')
        ->and($log->ip_hash)->not->toBe(request()->ip());
});

it('en desarrollo permite a Vite, y NUNCA con un literal IPv6', function () {
    // Un literal IPv6 entre corchetes no es una fuente válida en CSP: el
    // navegador descarta la directiva ENTERA por inválida y la aplicación se
    // queda sin estilos. Se arregla fijando Vite a IPv4 (vite.config.js), no
    // metiendo [::1] aquí.
    app()->detectEnvironment(fn () => 'local');

    $csp = (string) $this->followingRedirects()->get(route('teacher.start'))
        ->headers->get('Content-Security-Policy');

    expect($csp)->toContain('http://127.0.0.1:5173')
        ->and($csp)->not->toContain('[::1]');
});

it('vite está fijado a IPv4 para que ese origen no vuelva a aparecer', function () {
    // Sin esto, en Windows Vite anuncia sus assets por IPv6 y vuelve el bug.
    expect(file_get_contents(base_path('vite.config.js')))->toContain("host: '127.0.0.1'");
});

it('en producción NO abre la política para el servidor de desarrollo', function () {
    app()->detectEnvironment(fn () => 'production');

    $csp = (string) $this->followingRedirects()->get(route('teacher.start'))
        ->headers->get('Content-Security-Policy');

    expect($csp)->not->toContain('5173');
});
