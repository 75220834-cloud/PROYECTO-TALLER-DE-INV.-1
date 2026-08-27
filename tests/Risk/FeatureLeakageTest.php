<?php

declare(strict_types=1);

use App\Modules\Incidents\Models\Incident;
use App\Modules\Incidents\Models\IncidentCategory;
use App\Modules\Incidents\Models\IncidentStatus as StatusModel;
use App\Modules\Locations\Models\Building;
use App\Modules\Locations\Models\Floor;
use App\Modules\Locations\Models\Room;
use App\Modules\Locations\Models\Site;
use App\Modules\Risk\Features\FeatureBuilder;
use App\Shared\Enums\IncidentStatus as S;
use Database\Seeders\CatalogSeeder;
use Illuminate\Support\Carbon;

/**
 * FUGA TEMPORAL — la prueba que sostiene todo el modulo predictivo (plan 15.4).
 *
 * Si las caracteristicas miran mas alla del corte, el modelo se entrena con
 * el futuro, sus metricas salen infladas y el trabajo entero deja de ser
 * defendible. Es el error mas comun y mas descalificante en un trabajo de
 * esta clase, y no se detecta mirando el codigo: se detecta con esto.
 */
beforeEach(function () {
    $this->seed(CatalogSeeder::class);

    $site = Site::create(['code' => 'HYO', 'name' => 'Huancayo', 'is_active' => true]);
    $building = Building::create(['site_id' => $site->id, 'code' => 'C', 'name' => 'Pabellón C', 'is_active' => true]);
    $floor = Floor::create(['building_id' => $building->id, 'number' => 3, 'label' => 'Piso 3', 'is_active' => true]);
    $this->room = Room::create(['floor_id' => $floor->id, 'code' => 'C305', 'criticality' => 1, 'is_active' => true]);

    $this->category = IncidentCategory::where('code', 'PROJECTOR')->firstOrFail();
    $this->cutoff = Carbon::parse('2026-06-01 00:00:00');

    $this->incidentAt = function (string $when, ?string $resolution = null): Incident {
        return Incident::create([
            'room_id' => $this->room->id,
            'category_id' => $this->category->id,
            'status_id' => StatusModel::idFor(S::Closed),
            'is_draft' => false,
            'reported_at' => Carbon::parse($when),
            'confirmed_at' => Carbon::parse($when),
            'resolution_type' => $resolution,
        ]);
    };
});

it('ignora por completo lo ocurrido después de la fecha de corte', function () {
    ($this->incidentAt)('2026-05-25 10:00:00');

    $before = app(FeatureBuilder::class)
        ->build($this->room->id, $this->category->id, $this->cutoff)
        ->toArray();

    // Se inventan incidencias DESPUES del corte. Si alguna caracteristica
    // las viera, el vector cambiaria — y eso es exactamente la fuga.
    ($this->incidentAt)('2026-06-02 10:00:00');
    ($this->incidentAt)('2026-06-10 10:00:00');
    ($this->incidentAt)('2026-07-01 10:00:00');

    $after = app(FeatureBuilder::class)
        ->build($this->room->id, $this->category->id, $this->cutoff)
        ->toArray();

    expect($after)->toBe($before);
});

it('trata el corte como instante: lo que ocurre justo en el corte es futuro', function () {
    ($this->incidentAt)('2026-06-01 00:00:00');

    $features = app(FeatureBuilder::class)->build($this->room->id, $this->category->id, $this->cutoff);

    expect($features->incidentsTotal)->toBe(0)
        ->and($features->daysSinceLast)->toBeNull();
});

it('la etiqueta sí mira hacia adelante, y solo dentro del horizonte', function () {
    $builder = app(FeatureBuilder::class);

    expect($builder->label($this->room->id, $this->category->id, $this->cutoff, 14))->toBeFalse();

    ($this->incidentAt)('2026-06-20 10:00:00'); // dia 19: fuera del horizonte de 14
    expect($builder->label($this->room->id, $this->category->id, $this->cutoff, 14))->toBeFalse();

    ($this->incidentAt)('2026-06-10 10:00:00'); // dia 9: dentro
    expect($builder->label($this->room->id, $this->category->id, $this->cutoff, 14))->toBeTrue();
});

it('cuenta las ventanas de 7, 30 y 90 días con los límites correctos', function () {
    ($this->incidentAt)('2026-05-30 00:00:00'); // exactamente 2 dias antes
    ($this->incidentAt)('2026-05-15 10:00:00'); // 17 dias
    ($this->incidentAt)('2026-04-10 10:00:00'); // 52 dias
    ($this->incidentAt)('2026-01-05 10:00:00'); // 147 dias: solo en el total

    $f = app(FeatureBuilder::class)->build($this->room->id, $this->category->id, $this->cutoff);

    expect($f->incidents7d)->toBe(1)
        ->and($f->incidents30d)->toBe(2)
        ->and($f->incidents90d)->toBe(3)
        ->and($f->incidentsTotal)->toBe(4)
        ->and($f->daysSinceLast)->toBe(2);
});

it('no cuenta los borradores abandonados como incidencias', function () {
    Incident::create([
        'room_id' => $this->room->id,
        'category_id' => $this->category->id,
        'status_id' => StatusModel::idFor(S::Draft),
        'is_draft' => true,
        'confirmed_at' => Carbon::parse('2026-05-30 10:00:00'),
        'reported_at' => Carbon::parse('2026-05-30 10:00:00'),
    ]);

    $f = app(FeatureBuilder::class)->build($this->room->id, $this->category->id, $this->cutoff);

    // Un borrador es un abandono, no una avería. Contarlo aquí inflaría el
    // riesgo de las aulas donde los docentes se pierden navegando.
    expect($f->incidentsTotal)->toBe(0);
});

it('devuelve null en la tendencia cuando no hay periodo anterior con el que comparar', function () {
    ($this->incidentAt)('2026-05-20 10:00:00');

    $f = app(FeatureBuilder::class)->build($this->room->id, $this->category->id, $this->cutoff);

    // Sin denominador no hay razón. Sustituirla por 0 o por 1 fabricaría una
    // tendencia que los datos no respaldan.
    expect($f->trendRatio)->toBeNull();
});

it('calcula la tendencia comparando los últimos 30 días con los 30 anteriores', function () {
    ($this->incidentAt)('2026-05-20 10:00:00');
    ($this->incidentAt)('2026-05-22 10:00:00');
    ($this->incidentAt)('2026-05-24 10:00:00');
    ($this->incidentAt)('2026-04-20 10:00:00');

    $f = app(FeatureBuilder::class)->build($this->room->id, $this->category->id, $this->cutoff);

    expect($f->trendRatio)->toBe(3.0);
});
