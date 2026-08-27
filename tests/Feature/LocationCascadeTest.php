<?php

declare(strict_types=1);

use App\Modules\Locations\Models\Building;
use App\Modules\Locations\Models\Floor;
use App\Modules\Locations\Models\Room;
use App\Modules\Locations\Models\Site;
use App\Modules\Locations\Services\CascadeValidator;
use App\Shared\Exceptions\InvalidLocationException;

/**
 * Pruebas de la cascada sede -> pabellon -> piso -> aula (plan 17.4).
 *
 * Estas pruebas NO pasan por la interfaz a proposito. Atacan directamente
 * al validador con combinaciones que la pantalla nunca ofreceria, porque
 * lo que hay que demostrar es que el servidor rechaza una peticion HTTP
 * manipulada a mano. Que la interfaz limite las opciones es comodidad para
 * el docente; la garantia esta aqui.
 */
beforeEach(function () {
    $this->validator = new CascadeValidator;

    // Dos sedes con estructura paralela: sin la segunda, las pruebas de
    // "pertenece a otro padre" no tendrian con que confundirse y pasarian
    // por casualidad.
    $this->siteA = Site::create(['code' => 'S-A', 'name' => 'Sede A', 'is_active' => true]);
    $this->siteB = Site::create(['code' => 'S-B', 'name' => 'Sede B', 'is_active' => true]);

    $this->buildingA = Building::create(['site_id' => $this->siteA->id, 'code' => 'A', 'name' => 'Pab A', 'is_active' => true]);
    $this->buildingB = Building::create(['site_id' => $this->siteB->id, 'code' => 'B', 'name' => 'Pab B', 'is_active' => true]);

    $this->floorA3 = Floor::create(['building_id' => $this->buildingA->id, 'number' => 3, 'label' => 'Piso 3', 'is_active' => true]);
    $this->floorB1 = Floor::create(['building_id' => $this->buildingB->id, 'number' => 1, 'label' => 'Piso 1', 'is_active' => true]);

    $this->roomA305 = Room::create(['floor_id' => $this->floorA3->id, 'code' => 'A305', 'criticality' => 1, 'is_active' => true]);
    $this->roomB101 = Room::create(['floor_id' => $this->floorB1->id, 'code' => 'B101', 'criticality' => 1, 'is_active' => true]);
});

it('acepta una combinacion completamente valida', function () {
    $room = $this->validator->validate(
        $this->siteA->id,
        $this->buildingA->id,
        $this->floorA3->id,
        $this->roomA305->id,
    );

    expect($room->id)->toBe($this->roomA305->id)
        ->and($room->code)->toBe('A305');
});

it('rechaza un aula que no pertenece al piso enviado', function () {
    // Peticion manipulada: aula de la sede B con el piso de la sede A.
    $this->validator->validate(
        $this->siteA->id,
        $this->buildingA->id,
        $this->floorA3->id,
        $this->roomB101->id,
    );
})->throws(InvalidLocationException::class);

it('rechaza un piso que no pertenece al pabellon enviado', function () {
    $this->validator->validate(
        $this->siteA->id,
        $this->buildingA->id,
        $this->floorB1->id,
        $this->roomB101->id,
    );
})->throws(InvalidLocationException::class);

it('rechaza un pabellon que no pertenece a la sede enviada', function () {
    $this->validator->validate(
        $this->siteA->id,
        $this->buildingB->id,
        $this->floorB1->id,
        $this->roomB101->id,
    );
})->throws(InvalidLocationException::class);

it('rechaza un aula inexistente', function () {
    $this->validator->validate(
        $this->siteA->id,
        $this->buildingA->id,
        $this->floorA3->id,
        999999,
    );
})->throws(InvalidLocationException::class);

it('rechaza un aula desactivada', function () {
    $this->roomA305->update(['is_active' => false]);

    $this->validator->validate(
        $this->siteA->id,
        $this->buildingA->id,
        $this->floorA3->id,
        $this->roomA305->id,
    );
})->throws(InvalidLocationException::class);

it('rechaza un aula activa cuyo pabellon esta desactivado', function () {
    // Caso facil de pasar por alto: el aula esta bien, pero todo el
    // pabellon esta fuera de servicio. Aceptarlo generaria un ticket que
    // nadie deberia atender.
    $this->buildingA->update(['is_active' => false]);

    $this->validator->validate(
        $this->siteA->id,
        $this->buildingA->id,
        $this->floorA3->id,
        $this->roomA305->id,
    );
})->throws(InvalidLocationException::class);

it('rechaza un aula activa cuyo piso esta desactivado', function () {
    $this->floorA3->update(['is_active' => false]);

    $this->validator->validate(
        $this->siteA->id,
        $this->buildingA->id,
        $this->floorA3->id,
        $this->roomA305->id,
    );
})->throws(InvalidLocationException::class);

it('resuelve la ubicacion por relaciones y no concatenando codigos', function () {
    // Aula con codigo fuera de toda nomenclatura, en el piso 3 del
    // pabellon A. Si el codigo derivara el codigo del aula a partir de
    // pabellon + piso + numero, esta aula seria irresoluble. Es la prueba
    // que justifica que rooms.code se ALMACENE (plan 11.3).
    $lab = Room::create([
        'floor_id' => $this->floorA3->id,
        'code' => 'LAB-2',
        'name' => 'Laboratorio 2',
        'criticality' => 3,
        'is_active' => true,
    ]);

    $room = $this->validator->validate(
        $this->siteA->id,
        $this->buildingA->id,
        $this->floorA3->id,
        $lab->id,
    );

    expect($room->code)->toBe('LAB-2')
        ->and($room->fullPath())->toContain('LAB-2')
        ->and($room->fullPath())->toContain('Piso 3');
});

it('entrega al docente un mensaje comprensible, no el detalle tecnico', function () {
    // El mensaje tecnico va al log; el docente recibe una salida. Mostrarle
    // "El aula no pertenece al piso indicado" no le dice que hacer y ademas
    // revela como esta estructurado el catalogo por dentro.
    try {
        $this->validator->validate(
            $this->siteA->id,
            $this->buildingA->id,
            $this->floorA3->id,
            $this->roomB101->id,
        );
        $this->fail('Deberia haber lanzado InvalidLocationException.');
    } catch (InvalidLocationException $e) {
        expect($e->teacherMessage())->not->toBe($e->getMessage())
            ->and($e->teacherMessage())->toContain('Vuelve a elegir')
            ->and($e->reason())->toBe('cascade_mismatch');
    }
});
