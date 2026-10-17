<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Diagnostics\Models\DiagnosticFlow;
use App\Modules\Diagnostics\Models\DiagnosticFlowVersion;
use App\Modules\Knowledge\Jobs\ProcessDocumentVersion;
use App\Modules\Locations\Services\QrCodeService;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PilotDiagnosticsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/**
 * Lo que tiene que ser cierto para instalar el sistema en la PC de soporte
 * y abrirlo a internet.
 *
 * Cada bloque corresponde a un defecto encontrado al preparar la instalacion
 * desde cero. Ninguno se veia en desarrollo, porque en desarrollo se trabaja
 * en localhost, con usuarios demo y con los arboles ya cargados a mano.
 */

/* ------------------------------------------------------------------ */
/* Primer administrador */
/* ------------------------------------------------------------------ */

describe('usuarios:crear-admin', function () {
    beforeEach(fn () => $this->seed(RoleSeeder::class));

    it('crea una cuenta de administrador con los datos que se escriben', function () {
        $this->artisan('usuarios:crear-admin')
            ->expectsQuestion('Nombre completo', 'Persona de Soporte')
            ->expectsQuestion('Correo (con este se inicia sesión)', 'Soporte@Ejemplo.test')
            ->expectsQuestion('Contraseña (mínimo 10 caracteres)', 'una-clave-larga')
            ->expectsQuestion('Repite la contraseña', 'una-clave-larga')
            ->assertSuccessful();

        $user = User::where('email', 'soporte@ejemplo.test')->firstOrFail();

        expect($user->name)->toBe('Persona de Soporte')
            ->and($user->hasRole('admin'))->toBeTrue()
            ->and(password_verify('una-clave-larga', $user->password))->toBeTrue();
    });

    it('deja constancia en la auditoria sin guardar la contrasena', function () {
        $this->artisan('usuarios:crear-admin')
            ->expectsQuestion('Nombre completo', 'Persona de Soporte')
            ->expectsQuestion('Correo (con este se inicia sesión)', 'soporte@ejemplo.test')
            ->expectsQuestion('Contraseña (mínimo 10 caracteres)', 'una-clave-larga')
            ->expectsQuestion('Repite la contraseña', 'una-clave-larga')
            ->assertSuccessful();

        $log = DB::table('audit_logs')->where('action', 'user.created')->first();

        expect($log)->not->toBeNull()
            ->and($log->changes)->toContain('consola')
            ->and($log->changes)->not->toContain('una-clave-larga');
    });

    it('rechaza una contrasena corta', function () {
        $this->artisan('usuarios:crear-admin')
            ->expectsQuestion('Nombre completo', 'Persona de Soporte')
            ->expectsQuestion('Correo (con este se inicia sesión)', 'soporte@ejemplo.test')
            ->expectsQuestion('Contraseña (mínimo 10 caracteres)', 'corta')
            ->expectsQuestion('Repite la contraseña', 'corta')
            ->expectsOutputToContain('al menos 10 caracteres')
            ->assertFailed();

        expect(User::count())->toBe(0);
    });

    it('rechaza dos contrasenas distintas', function () {
        $this->artisan('usuarios:crear-admin')
            ->expectsQuestion('Nombre completo', 'Persona de Soporte')
            ->expectsQuestion('Correo (con este se inicia sesión)', 'soporte@ejemplo.test')
            ->expectsQuestion('Contraseña (mínimo 10 caracteres)', 'una-clave-larga')
            ->expectsQuestion('Repite la contraseña', 'otra-clave-larga')
            ->expectsOutputToContain('no coinciden')
            ->assertFailed();

        expect(User::count())->toBe(0);
    });

    it('no duplica un correo que ya tiene cuenta', function () {
        User::factory()->create(['email' => 'soporte@ejemplo.test']);

        $this->artisan('usuarios:crear-admin')
            ->expectsQuestion('Nombre completo', 'Otra Persona')
            ->expectsQuestion('Correo (con este se inicia sesión)', 'soporte@ejemplo.test')
            ->expectsQuestion('Contraseña (mínimo 10 caracteres)', 'una-clave-larga')
            ->expectsQuestion('Repite la contraseña', 'una-clave-larga')
            ->expectsOutputToContain('Ya existe una cuenta')
            ->assertFailed();

        expect(User::count())->toBe(1);
    });

    it('no corre sin una persona delante', function () {
        // Sin modo interactivo no hay forma segura de pedir la contrasena:
        // aceptarla como argumento la dejaria en el historial de la consola.
        $this->artisan('usuarios:crear-admin', ['--no-interaction' => true])
            ->assertFailed();

        expect(User::count())->toBe(0);
    });
});

it('avisa si se intenta crear el administrador antes de los roles', function () {
    $this->artisan('usuarios:crear-admin')
        ->expectsOutputToContain('migrate --seed')
        ->assertFailed();
});

/* ------------------------------------------------------------------ */
/* Arboles de diagnostico en el servidor */
/* ------------------------------------------------------------------ */

it('carga los arboles reales tambien en produccion', function () {
    // El defecto: en produccion el sembrado se detenia antes de cualquier
    // arbol, y el docente recibia «no hay procedimiento» para todo.
    app()->detectEnvironment(fn () => 'production');

    // --force: en produccion db:seed pide confirmacion, igual que en el servidor.
    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])
        ->assertSuccessful();

    expect(DiagnosticFlow::where('is_active', true)->count())->toBe(8)
        ->and(User::count())->toBe(0);
});

it('no publica una version nueva si el arbol no cambio', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(CatalogSeeder::class);

    $this->seed(PilotDiagnosticsSeeder::class);
    $antes = DiagnosticFlowVersion::count();

    $this->seed(PilotDiagnosticsSeeder::class);
    $this->seed(PilotDiagnosticsSeeder::class);

    expect(DiagnosticFlowVersion::count())->toBe($antes)
        ->and($antes)->toBe(8);
});

/* ------------------------------------------------------------------ */
/* Copias de seguridad */
/* ------------------------------------------------------------------ */

describe('respaldo:crear', function () {
    beforeEach(function () {
        $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'respaldos-'.uniqid();
        config([
            'incidencias.respaldo.carpeta' => $this->dir,
            'incidencias.respaldo.conservar' => 3,
            'incidencias.respaldo.mysqldump' => 'mysqldump',
        ]);
    });

    afterEach(fn () => File::deleteDirectory($this->dir));

    it('llama a mysqldump sin poner la contrasena en la linea de ordenes', function () {
        Process::fake();

        $this->artisan('respaldo:crear')->assertSuccessful();

        Process::assertRan(function ($process) {
            $args = (array) $process->command;

            $sinClave = collect($args)->every(
                fn (string $a) => ! str_starts_with($a, '--password') && ! str_starts_with($a, '-p')
            );

            return $args[0] === 'mysqldump'
                && str_starts_with($args[1], '--defaults-extra-file=')
                && in_array('--single-transaction', $args, true)
                && end($args) === config('database.connections.mysql.database')
                && $sinClave;
        });
    });

    it('borra el archivo temporal con las credenciales aunque falle', function () {
        $credenciales = null;
        $existiaDurante = false;

        Process::fake(function ($process) use (&$credenciales, &$existiaDurante) {
            $credenciales = substr($process->command[1], strlen('--defaults-extra-file='));
            $existiaDurante = is_file($credenciales);

            return Process::result(errorOutput: 'Access denied', exitCode: 2);
        });

        $this->artisan('respaldo:crear')
            ->expectsOutputToContain('Access denied')
            ->assertFailed();

        expect($existiaDurante)->toBeTrue()
            ->and(is_file($credenciales))->toBeFalse();
    });

    it('conserva solo las copias mas recientes', function () {
        Process::fake();
        File::ensureDirectoryExists($this->dir);

        foreach (['2026-01-01', '2026-01-02', '2026-01-03', '2026-01-04', '2026-01-05'] as $dia) {
            File::put("{$this->dir}/incidencias-{$dia}_010000.sql", '-- copia');
        }

        $this->artisan('respaldo:crear')->assertSuccessful();

        expect(collect(File::glob("{$this->dir}/incidencias-*.sql"))->map(fn ($f) => basename($f))->all())
            ->toBe([
                'incidencias-2026-01-03_010000.sql',
                'incidencias-2026-01-04_010000.sql',
                'incidencias-2026-01-05_010000.sql',
            ]);
    });
});

it('reindexa todas las guias de una vez al activar la IA', function () {
    // Sin esto, activar la IA en un sistema que empezo sin ella deja al
    // asistente sin fuentes: los fragmentos del modelo anterior se descartan
    // al buscar y nada avisa.
    $this->seed(CatalogSeeder::class);
    Queue::fake();
    Storage::fake(config('incidencias.media.disk'));

    $this->artisan('conocimiento:cargar')->assertSuccessful();
    Queue::assertPushed(ProcessDocumentVersion::class, 8);

    $this->artisan('conocimiento:reindexar')
        ->expectsOutputToContain('8 documentos enviados a indexar')
        ->assertSuccessful();

    Queue::assertPushed(ProcessDocumentVersion::class, 16);
});

/* ------------------------------------------------------------------ */
/* Direccion del QR */
/* ------------------------------------------------------------------ */

describe('QR con direccion publica', function () {
    it('usa PUBLIC_URL y no la direccion desde la que se abre el panel', function () {
        config(['incidencias.public_url' => 'https://incidencias.ejemplo.test/']);

        expect(app(QrCodeService::class)->targetUrl())
            ->toBe('https://incidencias.ejemplo.test/reportar');
    });

    it('avisa que un QR a localhost no sirve para imprimir', function () {
        config(['incidencias.public_url' => 'http://localhost:8080']);

        expect(app(QrCodeService::class)->printWarning())->toContain('localhost');
    });

    it('avisa que un QR a una IP de la red interna no abre con datos moviles', function () {
        config(['incidencias.public_url' => 'http://192.168.1.50:8080']);

        expect(app(QrCodeService::class)->printWarning())->toContain('192.168.1.50');
    });

    it('avisa que un tunel temporal no sirve para carteles', function () {
        config(['incidencias.public_url' => 'https://tres-palabras-azar.trycloudflare.com']);

        expect(app(QrCodeService::class)->printWarning())->toContain('temporal');
    });

    it('no pone pegas a una direccion publica estable', function () {
        config(['incidencias.public_url' => 'https://incidencias.ejemplo.test']);

        expect(app(QrCodeService::class)->printWarning())->toBeNull();
    });

    it('el cartel impreso sale marcado si la direccion no sirve', function () {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        config(['incidencias.public_url' => null]);

        $this->actingAs($admin)
            ->get(route('admin.qr.poster'))
            ->assertOk()
            ->assertSee('NO PEGAR ESTE CARTEL');
    });

    it('qr:generar dice a donde apunta y deja los archivos', function () {
        config(['incidencias.public_url' => 'https://incidencias.ejemplo.test']);

        $this->artisan('qr:generar')
            ->expectsOutputToContain('https://incidencias.ejemplo.test/reportar')
            ->assertSuccessful();

        expect(storage_path('app/qr/qr-acceso.png'))->toBeFile()
            ->and(storage_path('app/qr/qr-acceso.svg'))->toBeFile();
    });
});

/* ------------------------------------------------------------------ */
/* Panel solo desde la red interna */
/* ------------------------------------------------------------------ */

describe('acceso desde internet', function () {
    beforeEach(function () {
        $this->seed(RoleSeeder::class);
        $this->seed(CatalogSeeder::class);
        config(['incidencias.public_url' => 'https://incidencias.ejemplo.test']);
    });

    it('desde el tunel, el inicio de sesion no existe', function () {
        $this->withHeader('CF-Connecting-IP', '190.10.20.30')
            ->get('/entrar')
            ->assertNotFound();
    });

    it('desde el tunel, el panel no existe ni para una sesion iniciada', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->withHeader('CF-Connecting-IP', '190.10.20.30')
            ->get('/panel')
            ->assertNotFound();
    });

    it('desde el tunel, el docente si llega a su pantalla', function () {
        $this->withHeader('CF-Connecting-IP', '190.10.20.30')
            ->get('/reportar')
            ->assertOk();
    });

    it('desde el tunel, la raiz lleva al docente', function () {
        $this->withHeader('CF-Connecting-IP', '190.10.20.30')
            ->get('/')
            ->assertRedirect('/reportar');
    });

    it('por la direccion publica, aunque no haya cabeceras de tunel, el panel no existe', function () {
        $this->get('https://incidencias.ejemplo.test/entrar')
            ->assertNotFound();
    });

    it('desde una IP publica sin tunel, el panel no existe', function () {
        // Caso de una PC publicada con redireccion de puertos: la conexion
        // llega directamente desde la IP del visitante.
        $this->withServerVariables(['REMOTE_ADDR' => '190.10.20.30'])
            ->get('/entrar')
            ->assertNotFound();
    });

    it('desde la propia PC de soporte, el inicio de sesion se abre', function () {
        $this->get('/entrar')->assertOk();
    });

    it('desde otra PC de la red interna, el inicio de sesion se abre', function () {
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.10.25'])
            ->get('/entrar')
            ->assertOk();
    });

    it('la restriccion se puede apagar', function () {
        config(['incidencias.panel.solo_red_interna' => false]);

        $this->withHeader('CF-Connecting-IP', '190.10.20.30')
            ->get('/entrar')
            ->assertOk();
    });

    it('se puede estrechar a solo la PC del servidor', function () {
        config(['incidencias.panel.redes' => ['127.0.0.1/32', '::1/128']]);

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.10.25'])
            ->get('/entrar')
            ->assertNotFound();
    });
});

/* ------------------------------------------------------------------ */
/* Detras del tunel */
/* ------------------------------------------------------------------ */

describe('detras del tunel', function () {
    beforeEach(function () {
        // Rutas de sonda bajo /reportar para que pasen la restriccion.
        Route::get('/reportar/_sonda-ip', fn () => request()->ip());
        Route::get('/reportar/_sonda-url', fn () => url('/reportar'));
    });

    it('ve la IP real del docente y no la del tunel', function () {
        // Sin esto, todos los docentes compartirian la IP 127.0.0.1 y el
        // limite antiabuso por red bloquearia a la universidad entera tras
        // treinta reportes en una hora.
        $this->withHeader('X-Forwarded-For', '190.10.20.30')
            ->get('/reportar/_sonda-ip')
            ->assertSee('190.10.20.30');
    });

    it('genera los enlaces con https cuando el docente entro por https', function () {
        $this->withHeaders([
            'X-Forwarded-For' => '190.10.20.30',
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'incidencias.ejemplo.test',
        ])
            ->get('/reportar/_sonda-url')
            ->assertSee('https://incidencias.ejemplo.test/reportar', false);
    });

    it('no acepta cabeceras de reenvio de quien no es el tunel', function () {
        // Un visitante que se conecta directamente no puede hacerse pasar
        // por otra IP escribiendo la cabecera a mano.
        $this->withServerVariables(['REMOTE_ADDR' => '190.10.20.30'])
            ->withHeader('X-Forwarded-For', '1.2.3.4')
            ->get('/reportar/_sonda-ip')
            ->assertSee('190.10.20.30')
            ->assertDontSee('1.2.3.4');
    });
});
