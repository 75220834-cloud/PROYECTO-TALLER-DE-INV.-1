<?php

declare(strict_types=1);

use App\Modules\Incidents\Models\Incident;
use App\Modules\Incidents\Models\IncidentCategory;
use App\Modules\Incidents\Models\IncidentStatus as StatusModel;
use App\Modules\Locations\Models\Building;
use App\Modules\Locations\Models\Floor;
use App\Modules\Locations\Models\Room;
use App\Modules\Locations\Models\Site;
use App\Shared\Enums\IncidentStatus as S;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\PilotStructureSeeder;

/**
 * Ciclo de vida de los datos de demostración (plan 21).
 *
 * Es un requisito de validez de la investigación, no una comodidad: si al
 * cargar el catálogo real quedan aulas de prueba conviviendo con las de
 * verdad, las métricas quedan contaminadas y ya no hay forma de separarlas.
 */
beforeEach(function () {
    $this->seed(CatalogSeeder::class);
});

it('siembra la estructura del piloto con una sola sede', function () {
    $this->seed(PilotStructureSeeder::class);

    // Con una sola sede el sistema omite esa pantalla y el docente empieza
    // eligiendo pabellón. Dos sedes le añadirían un paso que no existe en el
    // aula real.
    expect(Site::count())->toBe(1)
        ->and(Building::count())->toBe(7)
        ->and(Floor::count())->toBe(35); // 7 pabellones x 5 pisos
});

it('marca como demostración todo lo que siembra', function () {
    $this->seed(PilotStructureSeeder::class);

    // Los códigos no llevan prefijo DEMO- para que la prueba con docentes
    // sea realista, así que la bandera es lo único que impide presentar
    // estos datos como institucionales. No puede faltar en ninguna fila.
    expect(Room::where('is_demo', false)->count())->toBe(0)
        ->and(Building::where('is_demo', false)->count())->toBe(0)
        ->and(Site::where('is_demo', false)->count())->toBe(0);
});

it('incluye las irregularidades que las pruebas de integridad necesitan', function () {
    $this->seed(PilotStructureSeeder::class);

    // Sin un aula fuera de nomenclatura, la prueba de "no se concatena el
    // código" pasaría por casualidad.
    $lab = Room::where('code', 'LAB-SIS-2')->first();
    expect($lab)->not->toBeNull()
        ->and($lab->floor->building->code)->toBe('H');

    expect(Room::where('is_active', false)->count())->toBeGreaterThan(0);
});

it('es idempotente: sembrar dos veces no duplica nada', function () {
    $this->seed(PilotStructureSeeder::class);
    $antes = Room::count();

    $this->seed(PilotStructureSeeder::class);

    expect(Room::count())->toBe($antes);
});

it('la purga borra de verdad, no solo marca como eliminado', function () {
    $this->seed(PilotStructureSeeder::class);

    $room = Room::first();
    Incident::create([
        'room_id' => $room->id,
        'category_id' => IncidentCategory::value('id'),
        'status_id' => StatusModel::idFor(S::New),
        'is_draft' => false,
        'confirmed_at' => now(),
        'reported_at' => now(),
    ]);

    $this->artisan('demo:purge', ['--force' => true])->assertSuccessful();

    // withTrashed: una purga que dejara filas ocultas por borrado suave no
    // serviría de nada, porque seguirían apareciendo en las consultas de la
    // investigación.
    expect(Room::withTrashed()->count())->toBe(0)
        ->and(Building::count())->toBe(0)
        ->and(Site::count())->toBe(0)
        ->and(Incident::count())->toBe(0)
        ->and(DB::table('equipment')->count())->toBe(0);
});

it('la purga no revienta por las claves foráneas del catálogo', function () {
    // Las FK de la cadena de ubicaciones son RESTRICT a propósito. Si el
    // orden de borrado fuera el equivocado, esto fallaría con un error de
    // integridad en lugar de limpiar.
    $this->seed(PilotStructureSeeder::class);

    $this->artisan('demo:purge', ['--force' => true])->assertSuccessful();
    $this->artisan('demo:purge', ['--force' => true])->assertSuccessful();
});

it('no siembra la estructura del piloto en producción', function () {
    app()->detectEnvironment(fn () => 'production');

    // Sembrar aulas aproximadas en el servidor del piloto contaminaría los
    // datos reales de la investigación.
    $this->artisan('piloto:sembrar', ['--force' => true])->assertFailed();

    expect(Site::count())->toBe(0);
});
