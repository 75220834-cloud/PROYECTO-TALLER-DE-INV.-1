<?php

declare(strict_types=1);

use App\Modules\Locations\Models\Building;
use App\Modules\Locations\Models\Floor;
use App\Modules\Locations\Models\Room;
use App\Modules\Locations\Models\Site;
use App\Modules\Locations\Services\LocationCatalogService;

/**
 * Catalogo de ubicaciones: lo que la cascada puede OFRECER al docente.
 *
 * El hilo conductor de estas pruebas es que el catalogo nunca debe exponer
 * mas de lo necesario: ni opciones de otro padre, ni elementos inactivos,
 * ni el listado completo de aulas a quien escriba dos letras en el buscador.
 */
beforeEach(function () {
    $this->service = new LocationCatalogService;

    $this->site = Site::create(['code' => 'S1', 'name' => 'Sede 1', 'is_active' => true]);
    $this->otherSite = Site::create(['code' => 'S2', 'name' => 'Sede 2', 'is_active' => true]);

    $this->buildingC = Building::create(['site_id' => $this->site->id, 'code' => 'C', 'name' => 'Pab C', 'sort_order' => 1, 'is_active' => true]);
    $this->buildingD = Building::create(['site_id' => $this->otherSite->id, 'code' => 'D', 'name' => 'Pab D', 'sort_order' => 1, 'is_active' => true]);

    $this->floor3 = Floor::create(['building_id' => $this->buildingC->id, 'number' => 3, 'label' => 'Piso 3', 'is_active' => true]);

    foreach (['C301', 'C302', 'C305'] as $code) {
        Room::create(['floor_id' => $this->floor3->id, 'code' => $code, 'criticality' => 1, 'is_active' => true]);
    }
});

it('solo ofrece pabellones de la sede elegida', function () {
    $buildings = $this->service->buildingsOf($this->site->id);

    expect($buildings)->toHaveCount(1)
        ->and($buildings->first()->code)->toBe('C');
});

it('nunca ofrece elementos desactivados', function () {
    Building::create(['site_id' => $this->site->id, 'code' => 'X', 'name' => 'Pab X', 'is_active' => false]);

    $codes = $this->service->buildingsOf($this->site->id)->pluck('code');

    expect($codes)->not->toContain('X');
});

it('omite el nivel cuando solo hay una opcion', function () {
    // Con una sola sede en el piloto esa pantalla no llega a mostrarse:
    // el docente se ahorra un toque sin que exista un caso especial en
    // el codigo (plan OS-1).
    Site::query()->where('id', $this->otherSite->id)->delete();

    $only = $this->service->autoSelect($this->service->sites());

    expect($only)->not->toBeNull()
        ->and($only->code)->toBe('S1');
});

it('no omite el nivel cuando hay varias opciones', function () {
    expect($this->service->autoSelect($this->service->sites()))->toBeNull();
});

it('encuentra el aula por su codigo exacto', function () {
    $results = $this->service->searchByCode('C305');

    expect($results)->toHaveCount(1)
        ->and($results->first()->code)->toBe('C305');
});

it('sugiere aulas por prefijo', function () {
    $codes = $this->service->searchByCode('C30')->pluck('code')->all();

    expect($codes)->toContain('C301', 'C302', 'C305');
});

it('no permite volcar el catalogo con una busqueda demasiado corta', function () {
    // Si "C" devolviera resultados, el buscador seria una via comoda para
    // enumerar todas las aulas de la universidad.
    expect($this->service->searchByCode('C'))->toBeEmpty()
        ->and($this->service->searchByCode(''))->toBeEmpty();
});

it('no permite usar comodines de LIKE en la busqueda', function () {
    // Sin escapado, "%" devolveria TODAS las aulas.
    expect($this->service->searchByCode('%'))->toBeEmpty()
        ->and($this->service->searchByCode('%%'))->toBeEmpty()
        ->and($this->service->searchByCode('C%'))->toBeEmpty();
});

it('no encuentra aulas desactivadas por codigo', function () {
    Room::query()->where('code', 'C305')->update(['is_active' => false]);

    expect($this->service->searchByCode('C305'))->toBeEmpty();
});
