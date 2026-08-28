<?php

declare(strict_types=1);

use App\Modules\Equipment\Models\Equipment;
use App\Modules\Locations\Models\Room;
use Database\Seeders\CatalogSeeder;

/**
 * Importación del catálogo real desde CSV (plan 22.2).
 *
 * Es el camino por el que entrará TODO el catálogo institucional. Un fallo
 * aquí no rompe una pantalla: deja la base de la investigación mal cargada.
 */
beforeEach(function () {
    $this->seed(CatalogSeeder::class);

    $this->csv = function (string $contenido): string {
        $path = tempnam(sys_get_temp_dir(), 'imp').'.csv';
        file_put_contents($path, $contenido);
        $this->archivos[] = $path;

        return $path;
    };

    $this->archivos = [];
});

afterEach(function () {
    foreach ($this->archivos ?? [] as $path) {
        @unlink($path);
    }
});

it('por defecto solo simula: no escribe nada sin --confirmar', function () {
    $archivo = ($this->csv)("sede;pabellon;piso;aula\nHYO;C;3;C305\n");

    $this->artisan('import:catalogo', ['tipo' => 'aulas', 'archivo' => $archivo])
        ->expectsOutputToContain('VISTA PREVIA')
        ->assertSuccessful();

    // Quien carga el catálogo lo hace una vez, con un archivo hecho a mano.
    // Descubrir los errores después de escribir obliga a limpiar y reintentar.
    expect(Room::count())->toBe(0);
});

it('carga de verdad con --confirmar y crea la jerarquía completa', function () {
    $archivo = ($this->csv)("sede;pabellon;piso;aula;nombre;capacidad;criticidad\nHYO;C;3;C305;Aula C305;35;2\n");

    $this->artisan('import:catalogo', ['tipo' => 'aulas', 'archivo' => $archivo, '--confirmar' => true])->assertSuccessful();

    $room = Room::where('code', 'C305')->firstOrFail();

    expect($room->capacity)->toBe(35)
        ->and($room->criticality)->toBe(2)
        ->and($room->floor->number)->toBe(3)
        ->and($room->floor->building->code)->toBe('C')
        // Los datos reales NO son demo: no llevan el aviso ni los borra
        // `demo:purge`.
        ->and($room->is_demo)->toBeFalse();
});

it('es idempotente: reimportar actualiza en lugar de duplicar', function () {
    $archivo = ($this->csv)("sede;pabellon;piso;aula;capacidad\nHYO;C;3;C305;30\n");
    $this->artisan('import:catalogo', ['tipo' => 'aulas', 'archivo' => $archivo, '--confirmar' => true]);

    // Se corrige el Excel y se vuelve a cargar, que es como se trabaja de
    // verdad con datos reales.
    $corregido = ($this->csv)("sede;pabellon;piso;aula;capacidad\nHYO;C;3;C305;45\n");
    $this->artisan('import:catalogo', ['tipo' => 'aulas', 'archivo' => $corregido, '--confirmar' => true])->assertSuccessful();

    expect(Room::count())->toBe(1)
        ->and(Room::first()->capacity)->toBe(45);
});

it('es todo o nada: una fila mala impide cargar las buenas', function () {
    $archivo = ($this->csv)(
        "sede;pabellon;piso;aula\n".
        "HYO;C;3;C305\n".
        "HYO;C;;C306\n".   // sin piso
        "HYO;C;3;C307\n"
    );

    $this->artisan('import:catalogo', ['tipo' => 'aulas', 'archivo' => $archivo, '--confirmar' => true])->assertFailed();

    // Media carga es peor que ninguna: deja un catálogo incompleto que
    // parece completo y nadie sabe por dónde iba.
    expect(Room::count())->toBe(0);
});

it('dice EN QUÉ LÍNEA está el error, no solo cuántos hay', function () {
    $archivo = ($this->csv)("sede;pabellon;piso;aula\nHYO;C;3;C305\nHYO;C;;C306\n");

    // Un informe que dice "1 error" obliga a revisar el Excel entero a mano.
    $this->artisan('import:catalogo', ['tipo' => 'aulas', 'archivo' => $archivo, '--confirmar' => true])
        ->expectsOutputToContain('línea 3')
        ->assertFailed();
});

it('rechaza el mismo código de aula en dos pisos de la misma sede', function () {
    $archivo = ($this->csv)("sede;pabellon;piso;aula\nHYO;C;3;C305\nHYO;C;4;C305\n");

    // Casi siempre es una errata de transcripción, y descubrirla aquí es
    // mucho más barato que cuando dos docentes reportan sobre la misma fila.
    $this->artisan('import:catalogo', ['tipo' => 'aulas', 'archivo' => $archivo, '--confirmar' => true])
        ->expectsOutputToContain('ya existe en otro piso')
        ->assertFailed();
});

it('acepta un código de aula fuera de nomenclatura', function () {
    $archivo = ($this->csv)("sede;pabellon;piso;aula;nombre\nHYO;H;3;LAB-SIS-2;Laboratorio de Sistemas 2\n");

    $this->artisan('import:catalogo', ['tipo' => 'aulas', 'archivo' => $archivo, '--confirmar' => true])->assertSuccessful();

    // El catálogo real tiene laboratorios y auditorios que no siguen ninguna
    // convención. Exigirla dejaría fuera justo las aulas más críticas.
    expect(Room::where('code', 'LAB-SIS-2')->exists())->toBeTrue();
});

it('lee archivos guardados por Excel con BOM y con coma', function () {
    // Es la causa número uno de que una importación "no lea nada".
    $archivo = ($this->csv)("\xEF\xBB\xBFsede,pabellon,piso,aula\nHYO,C,3,C305\n");

    $this->artisan('import:catalogo', ['tipo' => 'aulas', 'archivo' => $archivo, '--confirmar' => true])->assertSuccessful();

    expect(Room::where('code', 'C305')->exists())->toBeTrue();
});

it('avisa cuando falta una columna obligatoria', function () {
    $archivo = ($this->csv)("sede;pabellon;aula\nHYO;C;C305\n");

    $this->artisan('import:catalogo', ['tipo' => 'aulas', 'archivo' => $archivo, '--confirmar' => true])
        ->expectsOutputToContain('Falta la columna')
        ->assertFailed();
});

/* ------------------------------------------------------------------ */
/* Equipos */
/* ------------------------------------------------------------------ */

it('importa equipos sobre aulas ya cargadas', function () {
    $aulas = ($this->csv)("sede;pabellon;piso;aula\nHYO;C;1;C101\n");
    $this->artisan('import:catalogo', ['tipo' => 'aulas', 'archivo' => $aulas, '--confirmar' => true]);

    $equipos = ($this->csv)("aula;tipo;codigo_activo;marca\nC101;PROJECTOR;C101-PRY;Marca X\n");
    $this->artisan('import:catalogo', ['tipo' => 'equipos', 'archivo' => $equipos, '--confirmar' => true])->assertSuccessful();

    $equipo = Equipment::where('asset_code', 'C101-PRY')->firstOrFail();
    expect($equipo->brand)->toBe('Marca X')
        ->and($equipo->room->code)->toBe('C101');
});

it('NO crea aulas al vuelo desde el inventario de equipos', function () {
    $equipos = ($this->csv)("aula;tipo;codigo_activo\nNO-EXISTE;PROJECTOR;X-PRY\n");

    // Crear ubicaciones desde el inventario llenaría el catálogo de aulas
    // fantasma nacidas de erratas.
    $this->artisan('import:catalogo', ['tipo' => 'equipos', 'archivo' => $equipos, '--confirmar' => true])
        ->expectsOutputToContain('no existe')
        ->assertFailed();

    expect(Room::count())->toBe(0)->and(Equipment::count())->toBe(0);
});

it('avisa qué tipos de equipo son válidos cuando el CSV trae uno inventado', function () {
    $aulas = ($this->csv)("sede;pabellon;piso;aula\nHYO;C;1;C101\n");
    $this->artisan('import:catalogo', ['tipo' => 'aulas', 'archivo' => $aulas, '--confirmar' => true]);

    $equipos = ($this->csv)("aula;tipo;codigo_activo\nC101;TELETRANSPORTADOR;C101-TP\n");

    $this->artisan('import:catalogo', ['tipo' => 'equipos', 'archivo' => $equipos, '--confirmar' => true])
        ->expectsOutputToContain('Válidos:')
        ->assertFailed();
});

it('rechaza un tipo que no existe pero acepta un estado escrito a mano', function () {
    $aulas = ($this->csv)("sede;pabellon;piso;aula\nHYO;C;1;C101\n");
    $this->artisan('import:catalogo', ['tipo' => 'aulas', 'archivo' => $aulas, '--confirmar' => true]);

    // "malogrado" es como se dice de verdad en el inventario. Rechazar el
    // archivo entero por el matiz de una celda sería desproporcionado.
    $equipos = ($this->csv)("aula;tipo;codigo_activo;estado\nC101;PROJECTOR;C101-PRY;malogrado\n");
    $this->artisan('import:catalogo', ['tipo' => 'equipos', 'archivo' => $equipos, '--confirmar' => true])->assertSuccessful();

    expect(Equipment::first()->status)->toBe('out_of_service');
});
