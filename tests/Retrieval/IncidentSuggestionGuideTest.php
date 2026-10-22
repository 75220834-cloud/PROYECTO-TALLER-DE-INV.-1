<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Incidents\Models\IncidentCategory;
use App\Modules\Incidents\Models\IncidentStatus as StatusModel;
use App\Modules\Knowledge\Models\KnowledgeChunk;
use App\Modules\Knowledge\Models\KnowledgeDocument;
use App\Modules\Knowledge\Models\KnowledgeDocumentVersion;
use App\Modules\Locations\Models\Building;
use App\Modules\Locations\Models\Floor;
use App\Modules\Locations\Models\Room;
use App\Modules\Locations\Models\Site;
use App\Modules\Retrieval\Contracts\EmbeddingProvider;
use App\Shared\Enums\IncidentStatus as S;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;

/**
 * La guía que la IA recomienda en la ficha de una incidencia.
 *
 * Va en esta carpeta, y no con las demás pruebas de sugerencias, porque usa
 * la búsqueda de texto completo de MariaDB, que no ve las filas de una
 * transacción sin confirmar (ver tests/Pest.php).
 */
it('recomienda la guía que habla del problema, con su cita', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(CatalogSeeder::class);

    $proyector = IncidentCategory::where('code', 'PROJECTOR')->firstOrFail();
    $site = Site::create(['code' => 'HYO', 'name' => 'Huancayo', 'is_active' => true]);
    $building = Building::create(['site_id' => $site->id, 'code' => 'C', 'name' => 'Pabellón C', 'is_active' => true]);
    $floor = Floor::create(['building_id' => $building->id, 'number' => 3, 'label' => 'Piso 3', 'is_active' => true]);
    $room = Room::create(['floor_id' => $floor->id, 'code' => 'C305', 'criticality' => 1, 'is_active' => true]);

    $documento = KnowledgeDocument::create([
        'title' => 'Guía del proyector', 'type' => 'procedure', 'status' => 'published', 'category_id' => $proyector->id,
    ]);
    $version = KnowledgeDocumentVersion::create([
        'document_id' => $documento->id, 'version' => 1, 'file_path' => 'knowledge/guia.md',
        'original_name' => 'guia.md', 'file_hash' => hash('sha256', 'guia'), 'mime_type' => 'text/markdown',
        'file_size' => 100, 'processing_status' => 'indexed', 'indexed_at' => now(), 'chunk_count' => 1,
    ]);

    $texto = 'Si el proyector no muestra imagen, revisa que el cable HDMI esté firme y pulsa Source en el control.';
    $vectores = app(EmbeddingProvider::class);
    $vector = $vectores->embed($texto);

    KnowledgeChunk::create([
        'document_version_id' => $version->id, 'chunk_index' => 0, 'content' => $texto,
        'token_count' => 25, 'section_title' => 'Sin imagen', 'page_number' => 1,
        'embedding' => $vector, 'embedding_model' => $vectores->modelIdentifier(),
        'embedding_norm' => sqrt(array_sum(array_map(fn ($v) => $v * $v, $vector ?? []))),
        'category_id' => $proyector->id,
    ]);

    $incidencia = Incident::create([
        'room_id' => $room->id, 'category_id' => $proyector->id, 'status_id' => StatusModel::idFor(S::New),
        'ticket_number' => '000123', 'is_draft' => false, 'reported_at' => now(),
        'reported_description' => 'el proyector no muestra imagen',
    ]);

    $tecnico = User::factory()->create();
    $tecnico->assignRole('technician');

    $this->actingAs($tecnico)->get(route('support.incidents.suggestions', $incidencia))
        ->assertOk()
        ->assertSee('Guía del proyector')
        ->assertSee('cable HDMI esté firme');
});
