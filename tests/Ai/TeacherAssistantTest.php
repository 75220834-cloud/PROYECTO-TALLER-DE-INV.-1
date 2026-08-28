<?php

declare(strict_types=1);

use App\Modules\Incidents\Models\Incident;
use App\Modules\Incidents\Models\IncidentCategory;
use App\Modules\Locations\Models\Building;
use App\Modules\Locations\Models\Floor;
use App\Modules\Locations\Models\Room;
use App\Modules\Locations\Models\Site;
use Database\Seeders\CatalogSeeder;

/**
 * La IA, conectada al flujo real del docente (plan 13, CU-D-06 y CU-D-13).
 *
 * Los módulos del asistente estaban escritos y probados, pero no llegaban a
 * ninguna pantalla: un docente no podía alcanzarlos. Estas pruebas cubren
 * justamente la costura que faltaba, que es donde el fallo era invisible.
 */
beforeEach(function () {
    $this->seed(CatalogSeeder::class);

    $site = Site::create(['code' => 'HYO', 'name' => 'Huancayo', 'is_active' => true]);
    $building = Building::create(['site_id' => $site->id, 'code' => 'C', 'name' => 'Pabellón C', 'is_active' => true]);
    $floor = Floor::create(['building_id' => $building->id, 'number' => 3, 'label' => 'Piso 3', 'is_active' => true]);
    $this->room = Room::create(['floor_id' => $floor->id, 'code' => 'C305', 'criticality' => 1, 'is_active' => true]);

    // Deja al docente con un borrador en curso, como tras confirmar el aula.
    $this->empezar = function (): Incident {
        $this->get(route('teacher.start'));
        $this->post(route('teacher.confirm.store'), [
            'site_id' => (string) $this->room->floor->building->site_id,
            'building_id' => (string) $this->room->floor->building_id,
            'floor_id' => (string) $this->room->floor_id,
            'room_id' => (string) $this->room->id,
        ]);

        return Incident::where('room_id', $this->room->id)->latest('id')->firstOrFail();
    };
});

it('el docente puede describir el problema con sus palabras y recibir una propuesta', function () {
    ($this->empezar)();

    $this->post(route('teacher.classify'), ['description' => 'el proyector no enciende'])
        ->assertOk()
        // Le devuelve su propio texto: así confirma que el sistema leyó lo
        // suyo y no otra cosa.
        ->assertSee('el proyector no enciende');
});

it('NUNCA da por buena su propia clasificación: siempre pregunta', function () {
    ($this->empezar)();

    $response = $this->post(route('teacher.classify'), ['description' => 'el proyector no enciende'])->assertOk();

    // Si el sistema acertara mal y siguiera solo, el docente acabaría en un
    // árbol de diagnóstico ajeno a su problema y abandonaría. La prueba de
    // que no lo hace: la incidencia sigue SIN categoría después de clasificar.
    expect(Incident::first()->category_id)->toBeNull();

    // Y en pantalla hay una pregunta, no un hecho consumado.
    $response->assertSee('¿Es esto lo que pasa?', false)
        ->assertSee('No, es otra cosa', false);
});

it('cuando no lo tiene claro muestra el catálogo entero en lugar de adivinar', function () {
    ($this->empezar)();

    // Sin modelo de lenguaje la clasificación cae a palabras clave, y un
    // texto sin ninguna reconocible no debe dejar al docente atascado.
    config()->set('incidencias.llm.provider', 'null');

    $this->post(route('teacher.classify'), ['description' => 'algo raro pasa aquí'])
        ->assertOk()
        ->assertSee('Elígelo tú', false);
});

it('la categoría elegida tras la propuesta se guarda con la descripción', function () {
    ($this->empezar)();
    $categoria = IncidentCategory::where('code', 'PROJECTOR')->firstOrFail();

    $this->post(route('teacher.classify'), ['description' => 'no se ve nada']);

    $this->post(route('teacher.category.store'), [
        'category_id' => (string) $categoria->id,
        'description' => 'no se ve nada',
    ])->assertRedirect();

    $incident = Incident::first();
    expect($incident->category_id)->toBe($categoria->id)
        ->and($incident->reported_description)->toBe('no se ve nada');
});

it('exige un texto mínimo antes de molestar al modelo', function () {
    ($this->empezar)();

    $this->post(route('teacher.classify'), ['description' => 'x'])
        ->assertSessionHasErrors('description');
});

it('manda al inicio si no hay un reporte en curso', function () {
    $this->post(route('teacher.classify'), ['description' => 'el proyector no enciende'])
        ->assertRedirect(route('teacher.start'));
});

/* ------------------------------------------------------------------ */
/* Preguntas abiertas (CU-D-13) */
/* ------------------------------------------------------------------ */

it('responde una pregunta abierta sin necesidad de tener un reporte abierto', function () {
    // La duda puede surgir sin que haya una avería que reportar.
    $this->get(route('teacher.ask.form'))->assertOk()->assertSee('Pregunta lo que necesites');
});

it('con la base de conocimiento vacía NO inventa: escala', function () {
    ($this->empezar)();

    $this->post(route('teacher.ask'), ['question' => '¿Cuál es el procedimiento oficial de la universidad?'])
        ->assertOk()
        ->assertSee('No tengo suficiente información')
        // Nunca deja al docente sin salida: le ofrece el camino que sí resuelve.
        ->assertSee('Solicitar soporte técnico');
});

it('trata la respuesta del modelo como texto, nunca como HTML', function () {
    ($this->empezar)();

    // La salida del modelo es entrada NO confiable (plan 16.2). Si se
    // renderizara como HTML, un documento envenenado en la base de
    // conocimiento podría inyectar script en el navegador del docente.
    $vista = file_get_contents(resource_path('views/teacher/ask.blade.php'));

    expect($vista)->not->toContain('{!!');
});

it('la pregunta vacía no llega al modelo', function () {
    $this->post(route('teacher.ask'), ['question' => ''])->assertSessionHasErrors('question');
});
