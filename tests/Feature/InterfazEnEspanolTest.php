<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Diagnostics\Models\DiagnosticFlowVersion;
use App\Modules\Diagnostics\Models\DiagnosticStep;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Incidents\Models\IncidentCategory;
use App\Modules\Incidents\Models\IncidentPriority as PriorityModel;
use App\Modules\Incidents\Models\IncidentStatus as StatusModel;
use App\Modules\Knowledge\Models\KnowledgeDocument;
use App\Modules\Locations\Models\Building;
use App\Modules\Locations\Models\Floor;
use App\Modules\Locations\Models\Room;
use App\Modules\Locations\Models\Site;
use App\Modules\Media\Models\MediaAsset;
use App\Shared\Enums\IncidentPriority;
use App\Shared\Enums\IncidentStatus as S;
use App\Shared\Enums\ResolutionType;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\DemoLocationsSeeder;
use Database\Seeders\DemoMediaSeeder;
use Database\Seeders\PilotDiagnosticsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Todo lo que se lee en pantalla está en español.
 *
 * El código interno sigue en inglés (roles, acciones, claves): lo que no
 * puede pasar es que se vea. Antes se veían los roles («technician»), las
 * acciones de la auditoría («knowledge.version_uploaded»), la paginación de
 * Laravel («Showing 1 to 25 of 212 results») y, peor, las claves sin
 * traducir de los mensajes de error («validation.required»).
 *
 * Se recorre CADA pantalla del panel con datos, como administrador, para que
 * una pantalla nueva quede vigilada sin tener que acordarse de ella.
 */
function textoVisible(string $html): string
{
    $html = preg_replace('#<(script|style)\b.*?</\1>#si', ' ', $html);
    preg_match_all('#(?:placeholder|title|aria-label|alt)="([^"]*)"#i', $html, $atributos);
    // Los íconos se escriben con su nombre en inglés («close») y la fuente
    // los dibuja: nadie lee esa palabra.
    $html = preg_replace('#<span[^>]*material-symbols[^>]*>[^<]*</span>#i', ' ', $html);

    $texto = html_entity_decode(strip_tags(str_replace('<', ' <', $html)).' '.implode(' ', $atributos[1]));

    return preg_replace('/\s+/u', ' ', $texto);
}

beforeEach(function () {
    Storage::fake(config('incidencias.media.disk'));
    $this->seed(RoleSeeder::class);
    $this->seed(CatalogSeeder::class);
    $this->seed(DemoLocationsSeeder::class);
    $this->seed(DemoMediaSeeder::class);
    $this->seed(PilotDiagnosticsSeeder::class);
    $this->artisan('imagenes:provisionales');

    $this->admin = User::factory()->create(['name' => 'Persona Administradora']);
    $this->admin->assignRole('admin');
    foreach (['coordinator', 'technician', 'knowledge_manager', 'researcher'] as $rol) {
        User::factory()->create()->assignRole($rol);
    }
});

it('ninguna pantalla del panel muestra códigos internos ni textos en inglés', function () {
    // Datos en cada pantalla: una incidencia atendida, un documento, una
    // señal de riesgo y registros de auditoría.
    $room = Room::firstOrFail();
    $categoria = IncidentCategory::where('code', 'PROJECTOR')->firstOrFail();
    $ticket = Incident::create([
        'room_id' => $room->id, 'category_id' => $categoria->id,
        'status_id' => StatusModel::idFor(S::New),
        'priority_id' => PriorityModel::idFor(IncidentPriority::High),
        'ticket_number' => '000001', 'is_draft' => false, 'blocks_class' => true,
        'reported_description' => 'el proyector no muestra imagen', 'reported_at' => now(),
    ]);
    $this->actingAs($this->admin)->post(route('support.incidents.take', $ticket));
    $this->actingAs($this->admin)->post(route('support.incidents.resolve', $ticket), [
        'resolution_type' => ResolutionType::Onsite->value, 'resolution_notes' => 'Se cambió el cable HDMI.',
    ]);
    $this->actingAs($this->admin)->post(route('admin.knowledge.store'), [
        'title' => 'Procedimiento del proyector', 'type' => 'procedure',
        'document' => UploadedFile::fake()->createWithContent('p.md', "# Proyector\n\nRevisa el cable HDMI y la luz del proyector antes de llamar a soporte.\n"),
    ]);
    $this->artisan('risk:compute');

    $ids = [
        'room' => $room->id, 'building' => Building::first()?->id, 'floor' => Floor::first()?->id,
        'site' => Site::first()?->id, 'category' => $categoria->id, 'document' => KnowledgeDocument::first()?->id,
        'version' => DiagnosticFlowVersion::first()?->id, 'equipment' => Equipment::first()?->id,
        'asset' => MediaAsset::first()?->id, 'incident' => $ticket->id, 'step' => DiagnosticStep::first()?->id,
        'user' => $this->admin->id,
    ];

    // Descargas y respuestas para el navegador, no pantallas.
    $noSonPantallas = ['admin.knowledge.download', 'support.incidents.photo', 'admin.qr.download',
        'support.reports.export', 'support.dashboard.export', 'support.alerts',
        'admin.flows.steps.create', 'admin.flows.steps.edit'];

    $prohibido = [
        'claves sin traducir' => '/\b(validation|pagination|auth|passwords|roles|audit|risk-models|components|media-scopes|abuse-thresholds)\.[a-z_]+/',
        'paginación en inglés' => '/\b(Showing|results|Previous|Next|Go to page)\b/',
        'código de rol' => '/\b(admin|coordinator|technician|knowledge_manager|researcher)\b/',
        'código de acción' => '/\b(category|equipment|flow|knowledge|locations|media|research|user)\.[a-z_]+\b/',
        'nombre de clase' => '/\b(KnowledgeDocument|MediaAsset|IncidentCategory|DiagnosticFlow\w*|Equipment|Building|Floor|Room|Site|User)\s+#\d/',
        'modelo de riesgo' => '/baseline-recency/',
        'ámbito de imagen' => '/\b(component|equipment_type|generic)\b/',
        'fin de procedimiento' => '/\bEND_(RESOLVED|ESCALATE)\b/',
    ];

    $vistas = 0;
    $hallazgos = [];

    foreach (Route::getRoutes() as $ruta) {
        $nombre = $ruta->getName();

        if ($nombre === null || ! in_array('GET', $ruta->methods(), true)
            || ! str_starts_with($ruta->uri(), 'panel') || in_array($nombre, $noSonPantallas, true)) {
            continue;
        }

        $params = collect($ruta->parameterNames())->mapWithKeys(fn ($p) => [$p => $ids[$p] ?? 1])->all();
        $respuesta = $this->actingAs($this->admin)->get(route($nombre, $params));

        expect($respuesta->status())->toBe(200, "{$nombre} respondió {$respuesta->status()}");
        $vistas++;

        $texto = preg_replace('#https?://\S+|\S+@\S+#', ' ', textoVisible($respuesta->getContent()));

        foreach ($prohibido as $que => $patron) {
            if (preg_match($patron, $texto, $m)) {
                $hallazgos[] = "{$nombre}: {$que} «{$m[0]}»";
            }
        }
    }

    expect($vistas)->toBeGreaterThan(40)
        ->and($hallazgos)->toBe([]);
});

it('los roles se leen en español al crear un usuario', function () {
    $this->actingAs($this->admin)->get(route('admin.users.create'))
        ->assertOk()
        ->assertSee('Administrador')
        ->assertSee('Técnico')
        ->assertSee('Gestor de conocimiento')
        ->assertSee('Investigador')
        ->assertSee('Coordinador');
});

it('los errores de los formularios salen en español', function () {
    // Sin mensaje propio, Laravel usa los suyos: ahí es donde aparecía la
    // clave «validation.required» en lugar del mensaje.
    $errores = Validator::make(
        ['description' => '', 'email' => 'no-es-correo', 'foto' => str_repeat('x', 20)],
        ['description' => 'required', 'email' => 'email', 'foto' => 'max:10'],
    )->errors();

    expect($errores->first('description'))->toBe('El campo descripción es obligatorio.')
        ->and($errores->first('email'))->toBe('El campo correo debe ser un correo electrónico válido.')
        ->and($errores->first('foto'))->toBe('El campo foto no debe tener más de 10 caracteres.');
});

it('la paginación está en español', function () {
    $this->actingAs($this->admin)->get(route('admin.rooms.index'))
        ->assertOk()
        ->assertSee('Mostrando')
        ->assertSee('resultados')
        ->assertDontSee('Showing');
});

it('las páginas de error están en español', function () {
    $this->get('/esta-pagina-no-existe')
        ->assertNotFound()
        ->assertSee('No encontrado')
        ->assertDontSee('Not Found');
});

it('las direcciones del panel están en español', function () {
    expect(route('admin.rooms.index', absolute: false))->toBe('/panel/aulas')
        ->and(route('admin.buildings.index', absolute: false))->toBe('/panel/pabellones')
        ->and(route('admin.floors.index', absolute: false))->toBe('/panel/pisos')
        ->and(route('admin.sites.index', absolute: false))->toBe('/panel/sedes');
});
