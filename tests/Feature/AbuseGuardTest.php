<?php

declare(strict_types=1);

use App\Modules\Incidents\Models\AbuseRejection;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Incidents\Models\IncidentCategory;
use App\Modules\Incidents\Models\IncidentStatus as StatusModel;
use App\Modules\Incidents\Services\AbuseContext;
use App\Modules\Incidents\Services\AbuseGuard;
use App\Modules\Incidents\Services\IncidentService;
use App\Modules\Locations\Models\Building;
use App\Modules\Locations\Models\Floor;
use App\Modules\Locations\Models\Room;
use App\Modules\Locations\Models\Site;
use App\Shared\Enums\AbuseReason;
use App\Shared\Enums\IncidentStatus as S;
use App\Shared\Support\IpHasher;
use Database\Seeders\CatalogSeeder;

/**
 * Pila antiabuso (plan 16.4 y 17.2).
 *
 * Cada uno de los siete controles con su prueba, mas dos que importan
 * tanto como los controles en si:
 *   - que 15 dispositivos tras la MISMA IP no se bloqueen entre si;
 *   - que un docente legitimo no se tope con ningun control.
 */
beforeEach(function () {
    $this->seed(CatalogSeeder::class);

    $this->guard = new AbuseGuard;
    $this->service = app(IncidentService::class);

    $site = Site::create(['code' => 'HYO', 'name' => 'Huancayo', 'is_active' => true]);
    $building = Building::create(['site_id' => $site->id, 'code' => 'C', 'name' => 'Pab C', 'is_active' => true]);
    $floor = Floor::create(['building_id' => $building->id, 'number' => 3, 'label' => 'Piso 3', 'is_active' => true]);

    $this->room = Room::create(['floor_id' => $floor->id, 'code' => 'C305', 'criticality' => 1, 'is_active' => true]);
    $this->otherRoom = Room::create(['floor_id' => $floor->id, 'code' => 'C306', 'criticality' => 1, 'is_active' => true]);

    $this->category = IncidentCategory::where('code', 'PROJECTOR')->first();
    $this->otherCategory = IncidentCategory::where('code', 'AUDIO')->first();
});

/**
 * Crea un TICKET abierto (no borrador) en el aula indicada.
 *
 * `created_at` se aplica con forceFill despues de crear: no esta en
 * $fillable (y no debe estarlo), asi que la asignacion masiva lo ignoraria
 * en silencio y las pruebas de ventana temporal pasarian por casualidad.
 */
function openTicket(Room $room, ?IncidentCategory $category, array $attributes = []): Incident
{
    $createdAt = $attributes['created_at'] ?? null;
    unset($attributes['created_at']);

    $incident = Incident::create(array_merge([
        'room_id' => $room->id,
        'category_id' => $category?->id,
        'status_id' => StatusModel::idFor(S::New),
        'ticket_number' => str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
        'is_draft' => false,
    ], $attributes));

    if ($createdAt !== null) {
        $incident->forceFill(['created_at' => $createdAt])->saveQuietly();
    }

    return $incident;
}

/** Aula nueva, para aislar controles que no queremos que se pisen. */
function extraRoom(int $floorId, string $code): Room
{
    return Room::create(['floor_id' => $floorId, 'code' => $code, 'criticality' => 1, 'is_active' => true]);
}

function contextFor(Room $room, ?IncidentCategory $category, array $overrides = []): AbuseContext
{
    return new AbuseContext(
        room: $room,
        category: $category,
        deviceKey: $overrides['deviceKey'] ?? 'device-a',
        ipHash: $overrides['ipHash'] ?? 'ip-a',
        description: $overrides['description'] ?? null,
        confirmed: $overrides['confirmed'] ?? true,
        formElapsedSeconds: $overrides['formElapsedSeconds'] ?? 30,
        honeypot: $overrides['honeypot'] ?? null,
    );
}

// ------------------------------------------- 1. confirmacion explicita

it('rechaza si no se confirmo explicitamente', function () {
    $verdict = $this->guard->check(contextFor($this->room, $this->category, ['confirmed' => false]));

    expect($verdict->allowed)->toBeFalse()
        ->and($verdict->reason)->toBe(AbuseReason::Unconfirmed);
});

// --------------------------------------------- 2. senales de no-humano

it('rechaza cuando se rellena el campo trampa', function () {
    $verdict = $this->guard->check(contextFor($this->room, $this->category, ['honeypot' => 'http://spam.example']));

    expect($verdict->allowed)->toBeFalse()
        ->and($verdict->reason)->toBe(AbuseReason::BotSignal);
});

it('rechaza un envio instantaneo', function () {
    $verdict = $this->guard->check(contextFor($this->room, $this->category, ['formElapsedSeconds' => 0]));

    expect($verdict->reason)->toBe(AbuseReason::BotSignal);
});

it('NO penaliza cuando no se pudo medir el tiempo', function () {
    // Preferimos dejar pasar un bot antes que bloquear a un docente porque
    // su navegador no envio el campo.
    $verdict = $this->guard->check(contextFor($this->room, $this->category, ['formElapsedSeconds' => null]));

    expect($verdict->allowed)->toBeTrue();
});

// ---------------------------------------------------- 3. antiduplicados

it('rechaza un duplicado y OFRECE sumarse al ticket existente', function () {
    $existing = openTicket($this->room, $this->category);

    $verdict = $this->guard->check(contextFor($this->room, $this->category));

    expect($verdict->allowed)->toBeFalse()
        ->and($verdict->reason)->toBe(AbuseReason::Duplicate)
        ->and($verdict->canJoinExisting())->toBeTrue()
        ->and($verdict->relatedIncident->id)->toBe($existing->id);
});

it('permite reportar OTRA categoria en la misma aula', function () {
    openTicket($this->room, $this->category);

    expect($this->guard->check(contextFor($this->room, $this->otherCategory))->allowed)->toBeTrue();
});

it('permite reportar la misma categoria en OTRA aula', function () {
    openTicket($this->room, $this->category);

    expect($this->guard->check(contextFor($this->otherRoom, $this->category))->allowed)->toBeTrue();
});

it('no considera duplicado un ticket ya cerrado', function () {
    openTicket($this->room, $this->category, ['status_id' => StatusModel::idFor(S::Closed)]);

    expect($this->guard->check(contextFor($this->room, $this->category))->allowed)->toBeTrue();
});

it('no considera duplicado un BORRADOR abandonado', function () {
    // Un docente que entro y no completo no debe bloquear a quien si tiene
    // un problema real.
    Incident::create([
        'room_id' => $this->room->id,
        'category_id' => $this->category->id,
        'status_id' => StatusModel::idFor(S::Draft),
        'is_draft' => true,
    ]);

    expect($this->guard->check(contextFor($this->room, $this->category))->allowed)->toBeTrue();
});

// ------------------------------------------------- 4. tope por aula

it('rechaza cuando el aula llego a su tope de tickets abiertos', function () {
    $max = (int) config('incidencias.abuse.max_active_per_room');
    $categories = IncidentCategory::limit($max)->get();

    foreach ($categories as $category) {
        openTicket($this->room, $category);
    }

    $verdict = $this->guard->check(contextFor($this->room, IncidentCategory::orderByDesc('id')->first()));

    expect($verdict->allowed)->toBeFalse()
        ->and($verdict->reason)->toBe(AbuseReason::RoomActiveLimit)
        ->and($verdict->canJoinExisting())->toBeTrue();
});

// ------------------------------------------------ 5. limite dispositivo

it('rechaza cuando el mismo dispositivo supera su limite por hora', function () {
    $max = (int) config('incidencias.abuse.max_tickets_per_device_hour');

    for ($i = 0; $i < $max; $i++) {
        openTicket($this->otherRoom, null, ['device_key' => 'device-a']);
    }

    $verdict = $this->guard->check(contextFor($this->room, $this->category, ['deviceKey' => 'device-a']));

    expect($verdict->reason)->toBe(AbuseReason::DeviceRate);
});

it('no cuenta tickets de hace mas de una hora', function () {
    $max = (int) config('incidencias.abuse.max_tickets_per_device_hour');

    for ($i = 0; $i < $max + 2; $i++) {
        openTicket($this->otherRoom, null, [
            'device_key' => 'device-a',
            'created_at' => now()->subHours(3),
        ]);
    }

    expect($this->guard->check(contextFor($this->room, $this->category))->allowed)->toBeTrue();
});

// -------------------------------------------------------- 6. limite IP

it('rechaza cuando una IP supera su limite por hora', function () {
    $max = (int) config('incidencias.abuse.max_tickets_per_ip_hour');

    for ($i = 0; $i < $max; $i++) {
        openTicket($this->otherRoom, null, ['ip_hash' => 'ip-compartida', 'device_key' => 'd'.$i]);
    }

    $verdict = $this->guard->check(contextFor($this->room, $this->category, [
        'ipHash' => 'ip-compartida',
        'deviceKey' => 'device-nuevo',
    ]));

    expect($verdict->reason)->toBe(AbuseReason::IpRate);
});

it('NO bloquea a 15 dispositivos distintos tras la misma IP', function () {
    // Falso positivo mas probable y mas danino del sistema (riesgo R18):
    // en la WiFi institucional muchos docentes comparten IP de salida. Si
    // esta prueba falla, el sistema esta bloqueando a gente legitima.
    //
    // Cada docente reporta desde un aula DISTINTA a proposito: si todos
    // usaran la misma, saltaria antes el tope por aula y la prueba no
    // estaria midiendo el control por IP que dice medir.
    $floorId = $this->room->floor_id;

    for ($i = 0; $i < 15; $i++) {
        $room = extraRoom($floorId, 'W'.$i);

        $verdict = $this->guard->check(contextFor($room, $this->category, [
            'ipHash' => 'wifi-institucional',
            'deviceKey' => 'telefono-'.$i,
        ]));

        expect($verdict->allowed)->toBeTrue(
            "El docente {$i} fue bloqueado y no debía serlo (motivo: ".($verdict->reason?->value ?? '—').')'
        );

        openTicket($room, $this->category, [
            'ip_hash' => 'wifi-institucional',
            'device_key' => 'telefono-'.$i,
        ]);
    }
});

// ----------------------------------------------------- 7. similitud

it('MARCA descripciones casi identicas pero NO las bloquea', function () {
    openTicket($this->otherRoom, null, [
        'device_key' => 'device-a',
        'reported_description' => 'el proyector no muestra nada en la pantalla',
    ]);

    $verdict = $this->guard->check(contextFor($this->room, $this->category, [
        'description' => 'el proyector no muestra nada en la pantalla',
    ]));

    expect($verdict->allowed)->toBeTrue()
        ->and($verdict->flagged)->toBeTrue();
});

// ------------------------------------------------------- registro

it('registra TODO rechazo con su motivo', function () {
    $this->guard->check(contextFor($this->room, $this->category, ['confirmed' => false]));
    $this->guard->check(contextFor($this->room, $this->category, ['honeypot' => 'x']));

    // orderBy explicito: sin ORDER BY, el orden de las filas que devuelve
    // el motor no esta garantizado. Depender de él hace que la prueba pase
    // o falle por azar, que es peor que no tenerla.
    expect(AbuseRejection::count())->toBe(2)
        ->and(AbuseRejection::orderBy('id')->pluck('reason')->all())
        ->toBe([AbuseReason::Unconfirmed->value, AbuseReason::BotSignal->value]);
});

it('el rechazo SIEMPRE lleva un mensaje comprensible', function () {
    // Un docente con un proyector averiado y un error sin salida es un
    // fracaso del sistema, no una defensa.
    foreach (AbuseReason::cases() as $reason) {
        expect($reason->teacherMessage())->not->toBeEmpty();
    }
});

// -------------------------------------------------- camino legitimo

it('un docente normal NO se topa con ningun control', function () {
    // La prueba mas importante de todas: un antiabuso que estorba al
    // usuario legitimo es un defecto, no una proteccion.
    $verdict = $this->guard->check(contextFor($this->room, $this->category, [
        'description' => 'el proyector está encendido pero no llega la imagen',
        'formElapsedSeconds' => 45,
    ]));

    expect($verdict->allowed)->toBeTrue()
        ->and($verdict->flagged)->toBeFalse()
        ->and(AbuseRejection::count())->toBe(0);
});

// -------------------------------------------------------- privacidad

it('la IP nunca se almacena en claro', function () {
    $hasher = new IpHasher;
    $hash = $hasher->hash('192.168.1.50');

    expect($hash)->not->toContain('192.168')
        ->and($hash)->toHaveLength(64)
        // Estable: el mismo origen produce el mismo identificador.
        ->and($hasher->hash('192.168.1.50'))->toBe($hash)
        ->and($hasher->hash('192.168.1.51'))->not->toBe($hash);
});

it('no hashea una IP vacia', function () {
    expect((new IpHasher)->hash(null))->toBeNull();
});
