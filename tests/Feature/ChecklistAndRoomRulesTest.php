<?php

declare(strict_types=1);

use App\Modules\Equipment\Models\Equipment;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Incidents\Services\RoomCatalogFilter;
use App\Modules\Locations\Models\Room;
use App\Modules\Locations\Models\Site;
use App\Modules\Locations\Services\ChecklistImporter;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;

/**
 * Carga del checklist real y las tres reglas que salen de sus celdas vacías.
 *
 * El checklist marca «OK» lo que existe y soporte atiende. Una celda vacía no
 * es un dato que falte: es información, y las tres reglas de abajo son su
 * consecuencia directa.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(CatalogSeeder::class);

    $this->csv = function (array $filas): string {
        $ruta = tempnam(sys_get_temp_dir(), 'chk').'.csv';

        $contenido = ",AMBIENTE,,,EQUIPO DOCENTE,,,,EQUIPO MULTIMEDIA,,,,\n"
            .",PABELLÓN,AMBIENTE,TIPO DE AMBIENTE,PC\\AIO\\NUC,MOUSE,TECLADO,PARLANTE ,PROYECTOR,CONTROL DEL PROYECTOR,TIPO DE ECRAM,HDMI,OBSERVACIONES\n";

        foreach ($filas as $f) {
            $contenido .= ','.implode(',', $f)."\n";
        }

        file_put_contents($ruta, $contenido);

        return $ruta;
    };
});

/* ------------------------------------------------------------------ */
/* Carga del checklist */
/* ------------------------------------------------------------------ */

it('deduce el piso del código del aula', function () {
    $ruta = ($this->csv)([
        ['G', 'G802', 'AULA', 'AIO', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', ''],
        ['H', 'H901', 'AULA', 'AIO', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', ''],
        ['C', 'C201-A', 'AULA', 'AIO', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', ''],
    ]);

    // El checklist no trae columna de piso. Es la única deducción que hace el
    // importador, y de ella depende toda la cascada del docente.
    app(ChecklistImporter::class)->import($ruta, dryRun: false);

    expect(Room::where('code', 'G802')->first()->floor->number)->toBe(8)
        ->and(Room::where('code', 'H901')->first()->floor->number)->toBe(9)
        ->and(Room::where('code', 'C201-A')->first()->floor->number)->toBe(2);
});

it('entiende que la S del código significa sótano', function () {
    $ruta = ($this->csv)([['G', 'GS03', 'AULA', 'AIO', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', '']]);

    app(ChecklistImporter::class)->import($ruta, dryRun: false);

    $piso = Room::where('code', 'GS03')->first()->floor;

    // Número 0 y no negativo: así se lista antes del piso 1 sin ordenar al
    // revés en ningún sitio.
    expect($piso->number)->toBe(0)
        ->and($piso->label)->toBe('Sótano');
});

it('rechaza un código del que no puede deducir el piso, en vez de adivinar', function () {
    $ruta = ($this->csv)([['C', 'PATIO', 'AULA', 'AIO', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', '']]);

    $reporte = app(ChecklistImporter::class)->import($ruta, dryRun: false);

    expect($reporte->hasErrors())->toBeTrue()
        ->and(Room::count())->toBe(0);
});

it('la vista previa no escribe nada', function () {
    $ruta = ($this->csv)([['C', 'C101', 'AULA', 'AIO', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', '']]);

    $reporte = app(ChecklistImporter::class)->import($ruta, dryRun: true);

    // Cuenta lo que ocurriría, y no ocurre.
    expect($reporte->roomsCreated())->toBe(1)
        ->and(Room::count())->toBe(0);
});

it('volver a importar el mismo archivo actualiza, no duplica', function () {
    $ruta = ($this->csv)([['C', 'C101', 'AULA', 'AIO', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', '']]);

    app(ChecklistImporter::class)->import($ruta, dryRun: false);
    $segundo = app(ChecklistImporter::class)->import($ruta, dryRun: false);

    // Es lo que permite corregir el checklist y reintentar sin limpiar antes,
    // que es como se trabaja de verdad con datos reales.
    expect(Room::count())->toBe(1)
        ->and($segundo->roomsUpdated())->toBe(1)
        ->and($segundo->roomsCreated())->toBe(0)
        ->and(Equipment::count())->toBe(8);
});

it('una celda vacía significa que ese equipo no está', function () {
    $ruta = ($this->csv)([
        ['C', 'C201-A', 'AULA', 'AIO', 'OK', 'OK', '', 'OK', 'OK', 'OK', 'OK', ''],
    ]);

    app(ChecklistImporter::class)->import($ruta, dryRun: false);

    $codigos = Equipment::query()
        ->join('equipment_types as t', 't.id', '=', 'equipment.equipment_type_id')
        ->pluck('t.code')->all();

    expect($codigos)->not->toContain('SPEAKER')
        ->and($codigos)->toHaveCount(7);
});

it('guarda el tipo de computadora, que sí varía entre aulas', function () {
    $ruta = ($this->csv)([
        ['E', 'E201', 'LABORATORIO', 'PC', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', ''],
    ]);

    app(ChecklistImporter::class)->import($ruta, dryRun: false);

    // AIO, PC, NUC o LAPTOP cambian dónde está el botón de encendido, y eso
    // decide qué foto se le muestra al docente.
    $pc = Equipment::query()
        ->join('equipment_types as t', 't.id', '=', 'equipment.equipment_type_id')
        ->where('t.code', 'COMPUTER')->first();

    expect($pc->model)->toBe('PC');
});

it('los laboratorios pesan más que las aulas en la prioridad', function () {
    $ruta = ($this->csv)([
        ['E', 'E201', 'LABORATORIO', 'PC', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', ''],
        ['C', 'C101', 'AULA', 'AIO', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', ''],
    ]);

    app(ChecklistImporter::class)->import($ruta, dryRun: false);

    // La criticidad pondera CONSECUENCIAS: un laboratorio detenido deja sin
    // práctica a un grupo entero.
    expect(Room::where('code', 'E201')->first()->criticality)->toBe(2)
        ->and(Room::where('code', 'C101')->first()->criticality)->toBe(1);
});

/* ------------------------------------------------------------------ */
/* Aulas HYFLEX */
/* ------------------------------------------------------------------ */

it('las aulas HYFLEX existen pero no se autoatienden', function () {
    $ruta = ($this->csv)([['C', 'C305', 'HYFLEX', '', '', '', '', '', '', '', '', '']]);

    app(ChecklistImporter::class)->import($ruta, dryRun: false);

    $aula = Room::where('code', 'C305')->first();

    // Si no existieran, el docente de C305 no encontraría su aula,
    // concluiría que el sistema está roto y volvería al canal informal —
    // que es justo lo que el piloto quiere medir que desaparece.
    expect($aula)->not->toBeNull()
        ->and($aula->is_active)->toBeTrue()
        ->and($aula->self_service)->toBeFalse()
        ->and($aula->support_only_reason)->not->toBeNull()
        ->and(Equipment::where('room_id', $aula->id)->count())->toBe(0);
});

it('el docente de un aula HYFLEX va directo a soporte, sin elegir problema', function () {
    $ruta = ($this->csv)([['C', 'C305', 'HYFLEX', '', '', '', '', '', '', '', '', '']]);
    app(ChecklistImporter::class)->import($ruta, dryRun: false);

    $aula = Room::where('code', 'C305')->first();

    $this->get(route('teacher.start'));
    $this->post(route('teacher.confirm.store'), [
        'site_id' => (string) $aula->floor->building->site_id,
        'building_id' => (string) $aula->floor->building_id,
        'floor_id' => (string) $aula->floor_id,
        'room_id' => (string) $aula->id,
    ])->assertRedirect(route('teacher.escalate'));

    // Y se le dice por qué, para que no parezca que la pantalla se saltó sola.
    $this->get(route('teacher.escalate'))
        ->assertOk()
        ->assertSee('Esta aula la atiende soporte técnico');
});

/* ------------------------------------------------------------------ */
/* Qué problemas se le ofrecen a cada aula */
/* ------------------------------------------------------------------ */

it('solo ofrece los equipos que esa aula tiene', function () {
    $ruta = ($this->csv)([
        ['C', 'C201-A', 'AULA', 'AIO', 'OK', 'OK', '', 'OK', 'OK', 'OK', 'OK', ''],
        ['G', 'G802', 'AULA', 'AIO', 'OK', 'OK', 'OK', 'OK', 'OK', '', 'OK', ''],
    ]);
    app(ChecklistImporter::class)->import($ruta, dryRun: false);

    $filtro = app(RoomCatalogFilter::class);

    $sinParlante = $filtro->categoriesFor(Room::where('code', 'C201-A')->first())->pluck('code');
    $sinEcran = $filtro->categoriesFor(Room::where('code', 'G802')->first())->pluck('code');

    // Ofrecer un botón de parlante en un aula sin parlante hace perder el
    // tiempo al docente y mete en la investigación una incidencia contra un
    // equipo inexistente.
    expect($sinParlante)->not->toContain('SPEAKER')
        ->and($sinParlante)->toContain('PROJECTOR')
        ->and($sinEcran)->not->toContain('SCREEN')
        ->and($sinEcran)->toContain('SPEAKER');
});

it('«Otro problema» se ofrece siempre, tenga la aula lo que tenga', function () {
    $ruta = ($this->csv)([['C', 'C201-A', 'AULA', 'AIO', '', '', '', '', '', '', '', '']]);
    app(ChecklistImporter::class)->import($ruta, dryRun: false);

    $codigos = app(RoomCatalogFilter::class)
        ->categoriesFor(Room::where('code', 'C201-A')->first())
        ->pluck('code');

    // Sin esta salida, el docente cuyo problema no encaja en ningún botón se
    // queda sin canal y vuelve al camino informal.
    expect($codigos)->toContain('OTHER');
});

it('un aula sin inventario cargado muestra todo, en vez de una pantalla vacía', function () {
    // Se carga el aula y luego se le quita el inventario, que es como llega
    // un aula que el checklist todavía no cubrió.
    $ruta = ($this->csv)([['C', 'C101', 'AULA', 'AIO', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', '']]);
    app(ChecklistImporter::class)->import($ruta, dryRun: false);

    $aula = Room::where('code', 'C101')->first();
    Equipment::where('room_id', $aula->id)->delete();

    // Ninguna categoría no significa «no tiene nada»: casi siempre significa
    // que el inventario todavía no llegó. Ocultarle todos los botones al
    // docente lo dejaría mirando una pantalla vacía sin saber qué hacer.
    expect(app(RoomCatalogFilter::class)->categoriesFor($aula->fresh()))->toHaveCount(9);
});

it('la lista del docente respeta lo que su aula tiene', function () {
    $ruta = ($this->csv)([['C', 'C201-A', 'AULA', 'AIO', 'OK', 'OK', '', 'OK', 'OK', 'OK', 'OK', '']]);
    app(ChecklistImporter::class)->import($ruta, dryRun: false);

    $aula = Room::where('code', 'C201-A')->first();

    $this->get(route('teacher.start'));
    $this->post(route('teacher.confirm.store'), [
        'site_id' => (string) $aula->floor->building->site_id,
        'building_id' => (string) $aula->floor->building_id,
        'floor_id' => (string) $aula->floor_id,
        'room_id' => (string) $aula->id,
    ]);

    $this->get(route('teacher.category'))
        ->assertOk()
        ->assertSee('El proyector no prende')
        ->assertDontSee('No se escucha el audio');
});

/* ------------------------------------------------------------------ */
/* Ubicación en una sola pantalla */
/* ------------------------------------------------------------------ */

it('la pantalla única lleva dentro todas las opciones', function () {
    $ruta = ($this->csv)([
        ['C', 'C101', 'AULA', 'AIO', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', ''],
        ['G', 'GS03', 'AULA', 'AIO', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', ''],
    ]);
    app(ChecklistImporter::class)->import($ruta, dryRun: false);

    // Van todas de una vez y el navegador filtra: pedirle los pisos al
    // servidor al elegir el pabellón añadiría una ida y vuelta por pregunta
    // en la red de un aula que puede estar saturada.
    $this->get(route('teacher.start'))
        ->assertOk()
        ->assertSee('Pabellón C')
        ->assertSee('Pabellón G')
        ->assertSee('Sótano')
        ->assertSee('C101')
        ->assertSee('GS03');
});

it('el servidor rechaza una cadena de ubicación incoherente', function () {
    $ruta = ($this->csv)([
        ['C', 'C101', 'AULA', 'AIO', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', ''],
        ['G', 'G802', 'AULA', 'AIO', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', ''],
    ]);
    app(ChecklistImporter::class)->import($ruta, dryRun: false);

    $c101 = Room::where('code', 'C101')->first();
    $g802 = Room::where('code', 'G802')->first();

    // El navegador filtra; quien decide si la ubicación existe es el
    // servidor. Este POST es alcanzable sin pasar por la pantalla.
    $this->post(route('teacher.locate'), [
        'site_id' => (string) $c101->floor->building->site_id,
        'building_id' => (string) $c101->floor->building_id,
        'floor_id' => (string) $g802->floor_id,
        'room_id' => (string) $g802->id,
    ])->assertRedirect(route('teacher.start'));

    expect(Incident::count())->toBe(0);
});

/* ------------------------------------------------------------------ */
/* Mas de una sede */
/* ------------------------------------------------------------------ */

it('sin indicar sede, carga en la del piloto como siempre', function () {
    $ruta = ($this->csv)([['C', 'C101', 'AULA', 'AIO', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', '']]);

    app(ChecklistImporter::class)->import($ruta, dryRun: false);

    $sede = Room::where('code', 'C101')->first()->floor->building->site;

    expect($sede->code)->toBe('HYO')
        ->and($sede->name)->toBe('Huancayo');
});

it('carga otra sede sin mezclar sus pabellones con los de Huancayo', function () {
    // Mismo pabellon y misma aula en las dos sedes: tienen que quedar como
    // dos aulas distintas, cada una en su sede.
    $ruta = ($this->csv)([['A', 'A101', 'AULA', 'AIO', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', '']]);

    app(ChecklistImporter::class)->import($ruta, dryRun: false);
    app(ChecklistImporter::class)->import($ruta, dryRun: false, siteCode: 'LIM', siteName: 'Lima');

    $sedes = Room::where('code', 'A101')->get()
        ->map(fn (Room $r) => $r->floor->building->site->code)
        ->sort()->values()->all();

    expect($sedes)->toBe(['HYO', 'LIM'])
        ->and(Site::where('code', 'LIM')->first()->name)->toBe('Lima');

    // Los codigos de activo no chocan: los de Huancayo quedan como siempre
    // y los de la otra sede llevan su codigo delante.
    expect(Equipment::where('asset_code', 'A101-COMPUTER')->exists())->toBeTrue()
        ->and(Equipment::where('asset_code', 'LIM-A101-COMPUTER')->exists())->toBeTrue();
});

it('no inventa el nombre de una sede nueva', function () {
    // El nombre es lo que el docente ve al elegir donde esta: si falta, se
    // pide en lugar de poner el codigo o un texto cualquiera.
    $ruta = ($this->csv)([['A', 'A101', 'AULA', 'AIO', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', '']]);

    expect(fn () => app(ChecklistImporter::class)->import($ruta, dryRun: false, siteCode: 'LIM'))
        ->toThrow(InvalidArgumentException::class, '--nombre-sede');

    expect(Site::where('code', 'LIM')->exists())->toBeFalse()
        ->and(Room::count())->toBe(0);
});

it('una sede ya cargada no necesita repetir el nombre', function () {
    $ruta = ($this->csv)([['A', 'A101', 'AULA', 'AIO', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', '']]);

    app(ChecklistImporter::class)->import($ruta, dryRun: false, siteCode: 'LIM', siteName: 'Lima');
    app(ChecklistImporter::class)->import($ruta, dryRun: false, siteCode: 'lim');

    expect(Site::where('code', 'LIM')->count())->toBe(1)
        ->and(Site::where('code', 'LIM')->first()->name)->toBe('Lima');
});

it('el comando acepta la sede por opcion', function () {
    $ruta = ($this->csv)([['A', 'A101', 'AULA', 'AIO', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', '']]);

    $this->artisan('import:checklist', [
        'archivo' => $ruta,
        '--confirmar' => true,
        '--sede' => 'AQP',
        '--nombre-sede' => 'Arequipa',
    ])->expectsOutputToContain('Sede: AQP')->assertSuccessful();

    expect(Site::where('code', 'AQP')->first()?->name)->toBe('Arequipa');
});
