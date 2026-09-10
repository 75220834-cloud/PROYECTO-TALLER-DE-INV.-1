<?php

declare(strict_types=1);

use App\Modules\Locations\Models\Building;
use App\Modules\Locations\Models\Floor;
use App\Modules\Locations\Models\Room;
use App\Modules\Locations\Models\Site;
use Database\Seeders\CatalogSeeder;

/**
 * Flujo del docente tras escanear el QR generico (plan 7.1, 17.4).
 *
 * Sin autenticacion en ningun punto: el docente escanea y empieza.
 */
beforeEach(function () {
    // Confirmar una ubicacion crea un borrador de incidencia, y eso exige
    // que exista el catalogo de estados.
    $this->seed(CatalogSeeder::class);

    $this->site = Site::create(['code' => 'HYO', 'name' => 'Huancayo', 'is_active' => true]);

    $this->pabA = Building::create(['site_id' => $this->site->id, 'code' => 'A', 'name' => 'Pabellón A', 'sort_order' => 1, 'is_active' => true]);
    $this->pabC = Building::create(['site_id' => $this->site->id, 'code' => 'C', 'name' => 'Pabellón C', 'sort_order' => 2, 'is_active' => true]);

    $this->pisoA1 = Floor::create(['building_id' => $this->pabA->id, 'number' => 1, 'label' => 'Piso 1', 'is_active' => true]);
    $this->pisoC3 = Floor::create(['building_id' => $this->pabC->id, 'number' => 3, 'label' => 'Piso 3', 'is_active' => true]);

    $this->aulaA101 = Room::create(['floor_id' => $this->pisoA1->id, 'code' => 'A101', 'criticality' => 1, 'is_active' => true]);
    $this->aulaC305 = Room::create(['floor_id' => $this->pisoC3->id, 'code' => 'C305', 'criticality' => 1, 'is_active' => true]);
});

it('permite entrar sin autenticacion', function () {
    // Siguiendo las redirecciones se llega a una pantalla real, no al login.
    // Con una sola sede la primera peticion redirige (omision de nivel), asi
    // que assertOk() sobre la respuesta cruda seria una prueba mal escrita.
    $response = $this->followingRedirects()->get('/reportar');

    $response->assertOk();
    expect($response->baseResponse->getContent())->not->toContain('Iniciar sesión');
});

it('pregunta las cuatro cosas en una sola pantalla', function () {
    // Cuatro paginas costaban cuatro cargas y cuatro esperas a alguien de pie
    // con una clase mirandolo. Cada carga es una oportunidad de abandonar, y
    // el abandono es precisamente lo que el piloto mide.
    $this->get('/reportar')
        ->assertOk()
        ->assertSee('¿En qué sede estás?')
        ->assertSee('¿En qué pabellón estás?')
        ->assertSee('¿En qué piso estás?')
        ->assertSee('¿En qué aula estás?');
});

it('pregunta la sede aunque solo haya una', function () {
    // Cuesta un toque y hace que el dia que exista una segunda sede la
    // pantalla ya funcione sin tocar nada. Es una decision explicita: la
    // alternativa —darla por elegida— habria dejado el sistema atado a una
    // sola sede sin que nadie lo notara hasta ampliarlo.
    $this->get('/reportar')
        ->assertOk()
        ->assertSee('Huancayo');
});

it('ofrece todas las sedes cuando hay mas de una', function () {
    Site::create(['code' => 'OTRA', 'name' => 'Otra sede', 'is_active' => true]);

    $this->get('/reportar')
        ->assertOk()
        ->assertSee('Huancayo')
        ->assertSee('Otra sede');
});

it('rechaza una ubicación que no encaja entre sí', function () {
    // La pantalla unica filtra en el navegador, pero quien decide si la
    // cadena existe es el servidor: este POST es alcanzable directamente.
    $otra = Site::create(['code' => 'OTRA', 'name' => 'Otra sede', 'is_active' => true]);

    $this->post(route('teacher.locate'), [
        'site_id' => (string) $otra->id,
        'building_id' => (string) $this->aulaC305->floor->building_id,
        'floor_id' => (string) $this->aulaC305->floor_id,
        'room_id' => (string) $this->aulaC305->id,
    ])->assertRedirect(route('teacher.start'));
});

it('ofrece solo los pabellones de la sede', function () {
    $this->get(route('teacher.buildings', ['site' => $this->site->id]))
        ->assertOk()
        ->assertSee('Pabellón A')
        ->assertSee('Pabellón C');
});

it('ofrece solo los pisos del pabellon elegido', function () {
    // Se anade un segundo piso a proposito: con uno solo, el sistema omite
    // la pantalla (comportamiento correcto) y no habria nada que comprobar.
    Floor::create(['building_id' => $this->pabC->id, 'number' => 4, 'label' => 'Piso 4', 'is_active' => true]);

    $this->get(route('teacher.floors', ['site' => $this->site->id, 'building' => $this->pabC->id]))
        ->assertOk()
        ->assertSee('Piso 3')
        ->assertSee('Piso 4')
        ->assertDontSee('Piso 1');
});

it('omite la pantalla de piso cuando el pabellon tiene uno solo', function () {
    $this->get(route('teacher.floors', ['site' => $this->site->id, 'building' => $this->pabC->id]))
        ->assertRedirect(route('teacher.rooms', [
            'site' => $this->site->id, 'building' => $this->pabC->id, 'floor' => $this->pisoC3->id,
        ]));
});

it('ofrece solo las aulas de ese pabellon y piso', function () {
    $this->get(route('teacher.rooms', [
        'site' => $this->site->id, 'building' => $this->pabC->id, 'floor' => $this->pisoC3->id,
    ]))
        ->assertOk()
        ->assertSee('C305')
        ->assertDontSee('A101');
});

it('nunca ofrece aulas desactivadas', function () {
    $this->aulaC305->update(['is_active' => false]);

    $this->get(route('teacher.rooms', [
        'site' => $this->site->id, 'building' => $this->pabC->id, 'floor' => $this->pisoC3->id,
    ]))
        ->assertOk()
        ->assertDontSee('C305');
});

it('muestra la confirmacion explicita con la ubicacion completa', function () {
    $this->get(route('teacher.confirm', [
        'site' => $this->site->id, 'building' => $this->pabC->id,
        'floor' => $this->pisoC3->id, 'room' => $this->aulaC305->id,
    ]))
        ->assertOk()
        ->assertSee('Estás solicitando asistencia desde')
        ->assertSee('C305')
        ->assertSee('Pabellón C')
        ->assertSee('Piso 3');
});

it('rechaza una cadena manipulada al mostrar la confirmacion', function () {
    // Aula del pabellon C con el piso del pabellon A.
    $this->get(route('teacher.confirm', [
        'site' => $this->site->id, 'building' => $this->pabA->id,
        'floor' => $this->pisoA1->id, 'room' => $this->aulaC305->id,
    ]))
        ->assertRedirect(route('teacher.start'))
        ->assertSessionHas('error');
});

it('confirma una ubicacion valida y avanza al reporte', function () {
    // Los ids se envian como TEXTO, que es lo que manda un formulario HTML
    // de verdad. Enviarlos como enteros de PHP haria pasar la prueba y
    // dejaria escapar el fallo: la regla 'integer' de Laravel comprueba que
    // el valor sea numerico pero NO lo convierte, y con strict_types eso
    // revienta al llegar al validador de cascada.
    //
    // Es exactamente el fallo que estas pruebas no cazaron y que aparecio al
    // recorrer el flujo con un cliente HTTP real.
    $this->post(route('teacher.confirm.store'), [
        'site_id' => (string) $this->site->id,
        'building_id' => (string) $this->pabC->id,
        'floor_id' => (string) $this->pisoC3->id,
        'room_id' => (string) $this->aulaC305->id,
    ])
        ->assertRedirect(route('teacher.category'))
        ->assertSessionHas('teacher.last_room_id', $this->aulaC305->id);
});

it('RECHAZA el POST con una cadena manipulada aunque la pantalla nunca lo ofrezca', function () {
    // Esta es la prueba central del plan 17.4: la interfaz limita las
    // opciones por comodidad, pero la garantia esta en el servidor. Se
    // envia directamente por HTTP una combinacion imposible.
    $this->post(route('teacher.confirm.store'), [
        'site_id' => $this->site->id,
        'building_id' => $this->pabA->id,
        'floor_id' => $this->pisoA1->id,
        'room_id' => $this->aulaC305->id,
    ])
        ->assertRedirect(route('teacher.start'))
        ->assertSessionHas('error');

    // Y no dejo rastro de ubicacion aceptada.
    expect(session('teacher.last_room_id'))->toBeNull();
});

it('el docente VE el motivo del rechazo, no solo vuelve al inicio', function () {
    // Regresion de un fallo detectado probando en el navegador y que las
    // pruebas anteriores no cazaban: comprobaban que el error quedara en
    // sesion, pero no que llegara a la pantalla. Como el inicio omite el
    // nivel de sede (solo hay una) y vuelve a redirigir, ese segundo salto
    // se comia el mensaje y el docente acababa en la primera pantalla sin
    // ninguna explicacion.
    $this->followingRedirects()
        ->get(route('teacher.confirm', [
            'site' => $this->site->id, 'building' => $this->pabA->id,
            'floor' => $this->pisoA1->id, 'room' => $this->aulaC305->id,
        ]))
        ->assertOk()
        ->assertSee('no existe', escape: false);
});

it('rechaza el POST con un aula inexistente', function () {
    $this->post(route('teacher.confirm.store'), [
        'site_id' => $this->site->id,
        'building_id' => $this->pabC->id,
        'floor_id' => $this->pisoC3->id,
        'room_id' => 999999,
    ])->assertRedirect(route('teacher.start'));
});

it('rechaza el POST hacia un aula desactivada', function () {
    $this->aulaC305->update(['is_active' => false]);

    $this->post(route('teacher.confirm.store'), [
        'site_id' => $this->site->id,
        'building_id' => $this->pabC->id,
        'floor_id' => $this->pisoC3->id,
        'room_id' => $this->aulaC305->id,
    ])->assertRedirect(route('teacher.start'));
});

it('permite buscar el aula por codigo como via alternativa', function () {
    $this->get(route('teacher.search', ['q' => 'C305']))
        ->assertOk()
        ->assertSee('C305');
});

it('no revela el catalogo con una busqueda de una sola letra', function () {
    // Se comprueba la ausencia del ENLACE al aula, no de la cadena "C305":
    // el campo de busqueda muestra "Por ejemplo: C305" como marcador, asi
    // que buscar el texto suelto daria un falso negativo.
    $linkToRoom = route('teacher.confirm', [
        'site' => $this->site->id, 'building' => $this->pabC->id,
        'floor' => $this->pisoC3->id, 'room' => $this->aulaC305->id,
    ]);

    $this->get(route('teacher.search', ['q' => 'C']))
        ->assertOk()
        ->assertDontSee($linkToRoom)
        ->assertSee('No encontramos');
});

it('si encuentra el aula con el codigo completo', function () {
    $linkToRoom = route('teacher.confirm', [
        'site' => $this->site->id, 'building' => $this->pabC->id,
        'floor' => $this->pisoC3->id, 'room' => $this->aulaC305->id,
    ]);

    $this->get(route('teacher.search', ['q' => 'C305']))
        ->assertOk()
        ->assertSee($linkToRoom, escape: false);
});

it('resuelve aulas con codigo fuera de nomenclatura', function () {
    // Si el sistema derivara el codigo del aula concatenando pabellon+piso,
    // esta aula seria irresoluble. Es la prueba que justifica almacenar
    // rooms.code (plan 11.3).
    $lab = Room::create(['floor_id' => $this->pisoC3->id, 'code' => 'LAB-2', 'criticality' => 3, 'is_active' => true]);

    $this->get(route('teacher.confirm', [
        'site' => $this->site->id, 'building' => $this->pabC->id,
        'floor' => $this->pisoC3->id, 'room' => $lab->id,
    ]))
        ->assertOk()
        ->assertSee('LAB-2');
});
