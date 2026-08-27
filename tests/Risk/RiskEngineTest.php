<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Incidents\Models\IncidentCategory;
use App\Modules\Incidents\Models\IncidentStatus as StatusModel;
use App\Modules\Locations\Models\Building;
use App\Modules\Locations\Models\Floor;
use App\Modules\Locations\Models\Room;
use App\Modules\Locations\Models\Site;
use App\Modules\Risk\Features\FeatureSet;
use App\Modules\Risk\Models\RiskScore;
use App\Modules\Risk\Services\BaselineRecencyModel;
use App\Modules\Risk\Services\RiskEngine;
use App\Shared\Enums\IncidentStatus as S;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;

/**
 * Baseline N0, motor y explicabilidad (plan 15.2, 15.5 y 17.7).
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(CatalogSeeder::class);

    $site = Site::create(['code' => 'HYO', 'name' => 'Huancayo', 'is_active' => true]);
    $building = Building::create(['site_id' => $site->id, 'code' => 'C', 'name' => 'Pabellón C', 'is_active' => true]);
    $floor = Floor::create(['building_id' => $building->id, 'number' => 3, 'label' => 'Piso 3', 'is_active' => true]);
    $this->room = Room::create(['floor_id' => $floor->id, 'code' => 'C305', 'criticality' => 1, 'is_active' => true]);

    $this->category = IncidentCategory::where('code', 'PROJECTOR')->firstOrFail();

    $this->features = function (array $overrides = []): FeatureSet {
        $defaults = [
            'roomId' => 1, 'categoryId' => null, 'cutoff' => Carbon::parse('2026-06-01'),
            'incidents7d' => 0, 'incidents30d' => 0, 'incidents90d' => 0, 'incidentsTotal' => 0,
            'daysSinceLast' => null, 'trendRatio' => null, 'daysSinceMaintenance' => 10,
            'criticality' => 1, 'equipmentCount' => 3, 'assistantResolutionRatio' => null,
        ];

        return new FeatureSet(...array_merge($defaults, $overrides));
    };

    $this->reportedAt = function (string $when): Incident {
        return Incident::create([
            'room_id' => $this->room->id,
            'category_id' => $this->category->id,
            'status_id' => StatusModel::idFor(S::Closed),
            'is_draft' => false,
            'reported_at' => Carbon::parse($when),
            'confirmed_at' => Carbon::parse($when),
        ]);
    };
});

it('nunca produce un score sin factores que lo expliquen', function () {
    $model = new BaselineRecencyModel;

    // Incluso el caso vacío tiene que explicarse: una tarjeta sin motivos se
    // lee como un fallo del sistema, no como "riesgo bajo".
    $prediction = $model->predict(($this->features)());

    expect($prediction->factors)->not->toBeEmpty()
        ->and($prediction->score)->toBe(0.0)
        ->and($prediction->band)->toBe('low');
});

it('mantiene el score dentro de 0 y 1 aunque las señales se disparen', function () {
    $prediction = (new BaselineRecencyModel)->predict(($this->features)([
        'incidents7d' => 50, 'incidents30d' => 80, 'incidents90d' => 120, 'incidentsTotal' => 200,
        'daysSinceLast' => 0, 'trendRatio' => 20.0, 'daysSinceMaintenance' => null, 'criticality' => 3,
    ]));

    expect($prediction->score)->toBeGreaterThan(0.0)->toBeLessThanOrEqual(1.0);
});

it('es monótono: más incidencias recientes nunca bajan el score', function () {
    $model = new BaselineRecencyModel;

    $low = $model->predict(($this->features)(['incidents30d' => 1, 'incidents7d' => 0, 'daysSinceLast' => 25]));
    $high = $model->predict(($this->features)(['incidents30d' => 4, 'incidents7d' => 3, 'daysSinceLast' => 1]));

    expect($high->score)->toBeGreaterThan($low->score);
});

it('la suma de las contribuciones coincide con el score', function () {
    $prediction = (new BaselineRecencyModel)->predict(($this->features)([
        'incidents30d' => 3, 'incidents7d' => 1, 'daysSinceLast' => 2, 'criticality' => 2,
    ]));

    $sum = round(array_sum(array_column($prediction->factors, 'contribution')), 4);

    // Si esto falla, la explicación mostrada al técnico no corresponde al
    // número que la acompaña, que es peor que no explicar nada.
    expect($sum)->toBe($prediction->score);
});

it('asigna la banda según los umbrales de configuración', function () {
    $model = new BaselineRecencyModel;

    expect($model->predict(($this->features)())->band)->toBe('low')
        ->and($model->predict(($this->features)([
            'incidents30d' => 2, 'incidents7d' => 1, 'daysSinceLast' => 1,
        ]))->band)->toBe('medium')
        ->and($model->predict(($this->features)([
            'incidents30d' => 6, 'incidents7d' => 5, 'daysSinceLast' => 0,
            'trendRatio' => 4.0, 'daysSinceMaintenance' => null, 'criticality' => 3,
        ]))->band)->toBe('high');
});

it('el motor persiste un score por aula activa y uno por par con historial', function () {
    ($this->reportedAt)(now()->subDays(3)->toDateTimeString());

    $written = app(RiskEngine::class)->computeAll();

    expect($written)->toBe(2); // 1 aula + 1 par aula-categoría

    $roomScore = RiskScore::where('scope_type', 'room')->firstOrFail();
    expect($roomScore->factors)->not->toBeEmpty()
        ->and($roomScore->model_code)->toBe('baseline-recency')
        ->and($roomScore->horizon_days)->toBe((int) config('incidencias.risk.horizon_days'));

    $pairScore = RiskScore::where('scope_type', 'room_category')->firstOrFail();
    expect($pairScore->category_id)->toBe($this->category->id);
});

it('no genera pares para categorías sin historial reciente', function () {
    ($this->reportedAt)(now()->subDays(200)->toDateTimeString());

    app(RiskEngine::class)->computeAll();

    // Un par que no falla desde hace medio año no es una señal: es ruido
    // histórico que enterraría a las pocas filas que sí importan.
    expect(RiskScore::where('scope_type', 'room_category')->count())->toBe(0);
});

it('conserva el histórico de scores en lugar de sobrescribirlo', function () {
    $engine = app(RiskEngine::class);

    $engine->computeAll(now()->subDay());
    $engine->computeAll(now());

    // Sin la serie histórica no se puede contrastar después lo previsto con
    // lo ocurrido, y el módulo dejaría de ser evaluable.
    expect(RiskScore::where('scope_type', 'room')->count())->toBe(2)
        ->and($engine->current('room'))->toHaveCount(1);
});

it('el comando programado calcula y no revienta sin datos', function () {
    $this->artisan('risk:compute')
        ->expectsOutputToContain('señales de riesgo')
        ->assertSuccessful();
});

it('exige el permiso risk.view para ver el tablero de señales', function () {
    $tecnico = User::factory()->create();
    $tecnico->assignRole('technician');

    // El gestor de conocimiento carga procedimientos; no atiende aulas ni
    // decide rondas de revision, asi que no ve las señales de riesgo.
    $gestor = User::factory()->create();
    $gestor->assignRole('knowledge_manager');

    app(RiskEngine::class)->computeAll();

    $this->actingAs($tecnico)->get(route('support.risk.index'))->assertOk();
    $this->actingAs($gestor)->get(route('support.risk.index'))->assertForbidden();
});

it('muestra siempre los factores junto al número, y advierte de cómo leerlo', function () {
    ($this->reportedAt)(now()->subDays(2)->toDateTimeString());
    app(RiskEngine::class)->computeAll();

    $tecnico = User::factory()->create();
    $tecnico->assignRole('technician');

    $this->actingAs($tecnico)->get(route('support.risk.index'))
        ->assertOk()
        ->assertSee('C305')
        ->assertSee('Incidencia muy reciente')
        ->assertSee('probabilidades de avería', false)
        ->assertSee('ordenan por dónde conviene empezar una revisión', false);
});
