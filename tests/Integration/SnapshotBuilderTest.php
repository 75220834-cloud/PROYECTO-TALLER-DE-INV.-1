<?php

declare(strict_types=1);

use App\Modules\Analytics\Services\SnapshotBuilder;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Incidents\Models\IncidentCategory;
use App\Modules\Incidents\Models\IncidentStatus as StatusModel;
use App\Modules\Locations\Models\Building;
use App\Modules\Locations\Models\Floor;
use App\Modules\Locations\Models\Room;
use App\Modules\Locations\Models\Site;
use App\Shared\Enums\IncidentStatus as S;
use App\Shared\Enums\ResolutionType;
use Database\Seeders\CatalogSeeder;

/**
 * Agregados diarios (plan 11.1).
 */
beforeEach(function () {
    $this->seed(CatalogSeeder::class);

    $site = Site::create(['code' => 'HYO', 'name' => 'Huancayo', 'is_active' => true]);
    $building = Building::create(['site_id' => $site->id, 'code' => 'C', 'name' => 'Pabellón C', 'is_active' => true]);
    $floor = Floor::create(['building_id' => $building->id, 'number' => 3, 'label' => 'Piso 3', 'is_active' => true]);
    $this->room = Room::create(['floor_id' => $floor->id, 'code' => 'C305', 'criticality' => 1, 'is_active' => true]);

    $this->category = IncidentCategory::where('code', 'PROJECTOR')->firstOrFail();
});

it('agrega el día por aula y categoría', function () {
    foreach ([[10, ResolutionType::Assistant], [30, ResolutionType::Onsite], [50, ResolutionType::Assistant]] as [$minutes, $type]) {
        Incident::create([
            'room_id' => $this->room->id,
            'category_id' => $this->category->id,
            'status_id' => StatusModel::idFor(S::Closed),
            'is_draft' => false,
            'confirmed_at' => today()->addHours(8),
            'reported_at' => today()->addHours(8),
            'resolved_at' => today()->addHours(8)->addMinutes($minutes),
            'resolution_type' => $type->value,
        ]);
    }

    app(SnapshotBuilder::class)->buildDay(today());

    $this->assertDatabaseHas('metric_snapshots', [
        'day' => today()->toDateString(),
        'room_id' => $this->room->id,
        'category_id' => $this->category->id,
        'incidents_total' => 3,
        'resolved_by_assistant' => 2,
        'resolved_onsite' => 1,
        // Mediana de 10, 30 y 50, no media: un caso olvidado no debe
        // desplazar el dato que describe la atención típica.
        'median_resolution_minutes' => 30,
    ]);
});

it('es idempotente: recalcular el mismo día no duplica filas', function () {
    Incident::create([
        'room_id' => $this->room->id,
        'category_id' => $this->category->id,
        'status_id' => StatusModel::idFor(S::New),
        'is_draft' => false,
        'confirmed_at' => today()->addHours(9),
        'reported_at' => today()->addHours(9),
    ]);

    $builder = app(SnapshotBuilder::class);
    $builder->buildDay(today());
    $builder->buildDay(today());

    // La tabla es caché reconstruible: reprocesar un día debe poder hacerse
    // sin miedo cuando se corrige un dato histórico.
    expect(DB::table('metric_snapshots')->count())->toBe(1);
});

it('cuenta los borradores abandonados aparte, sin inventarles categoría', function () {
    Incident::create([
        'room_id' => $this->room->id,
        'status_id' => StatusModel::idFor(S::Draft),
        'is_draft' => true,
        'confirmed_at' => today()->addHours(10),
    ]);

    app(SnapshotBuilder::class)->buildDay(today());

    $this->assertDatabaseHas('metric_snapshots', [
        'day' => today()->toDateString(),
        'room_id' => $this->room->id,
        'category_id' => null,
        'abandoned_drafts' => 1,
    ]);
});

it('el comando reprocesa varios días hacia atrás', function () {
    // Una incidencia puede cerrarse días después de reportarse, y el agregado
    // de aquel día cambia entonces. Recalcular solo ayer lo dejaría congelado.
    $this->artisan('metrics:snapshot', ['--days' => 3])->assertSuccessful();
});
