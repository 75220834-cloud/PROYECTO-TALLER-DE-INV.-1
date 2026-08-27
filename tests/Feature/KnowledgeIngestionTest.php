<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Knowledge\Jobs\ProcessDocumentVersion;
use App\Modules\Knowledge\Models\KnowledgeChunk;
use App\Modules\Knowledge\Models\KnowledgeDocument;
use App\Modules\Knowledge\Models\KnowledgeDocumentVersion;
use App\Modules\Knowledge\Pipeline\Chunker;
use App\Modules\Knowledge\Pipeline\DocumentIngestionPipeline;
use App\Modules\Knowledge\Services\KnowledgeService;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * Base de conocimiento: ingesta, versionado y estado (plan 14.1 y 41).
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(CatalogSeeder::class);

    $this->gestor = User::factory()->create();
    $this->gestor->assignRole('knowledge_manager');

    $this->tecnico = User::factory()->create();
    $this->tecnico->assignRole('technician');
});

/** Documento Markdown con secciones y un procedimiento numerado. */
function markdownFile(string $name = 'procedimiento.md'): UploadedFile
{
    $content = <<<'MD'
    # Procedimiento del proyector

    Este documento describe qué hacer cuando el proyector no muestra imagen.

    ## Revisión inicial

    Antes de nada hay que comprobar que el equipo tenga corriente y que la
    luz indicadora esté encendida. Si la luz está en naranja, el proyector
    está en espera y basta con pulsar el botón de encendido.

    ## Pasos a seguir

    1. Comprobar que el proyector esté encendido y con luz verde.
    2. Revisar que el cable HDMI esté firme en la computadora.
    3. Revisar que el cable HDMI esté firme en el proyector.
    4. Pulsar el botón Source del control hasta ver la imagen.
    5. En la computadora, pulsar Windows + P y elegir Duplicar.
    6. Esperar unos segundos a que la señal se estabilice.
    7. Si sigue sin verse, probar con otro cable HDMI.
    8. Si nada funciona, registrar la incidencia para soporte.

    ## Notas

    El proyector puede tardar hasta un minuto en dar imagen tras encenderse.
    MD;

    return UploadedFile::fake()->createWithContent($name, $content);
}

// -------------------------------------------------------------- permisos

it('el tecnico puede consultar pero NO cargar documentos', function () {
    // Un documento mal publicado cambia lo que el asistente le dice a TODOS
    // los docentes: cargarlo es decisión del gestor de conocimiento.
    $this->actingAs($this->tecnico)->get(route('admin.knowledge.index'))->assertOk();
    $this->actingAs($this->tecnico)->get(route('admin.knowledge.create'))->assertForbidden();
});

it('exige autenticacion', function () {
    $this->get(route('admin.knowledge.index'))->assertRedirect(route('login'));
});

// ---------------------------------------------------------------- carga

it('carga un documento y lo manda a la cola', function () {
    Queue::fake();

    $this->actingAs($this->gestor)
        ->post(route('admin.knowledge.store'), [
            'title' => 'Procedimiento del proyector',
            'type' => 'procedure',
            'document' => markdownFile(),
        ])
        ->assertRedirect();

    $document = KnowledgeDocument::first();

    expect($document)->not->toBeNull()
        ->and($document->status)->toBe('draft')
        ->and($document->currentVersion()->version)->toBe(1)
        ->and($document->currentVersion()->processing_status)->toBe('pending');

    // A la cola, no en la petición: vectorizar un manual tarda minutos.
    Queue::assertPushed(ProcessDocumentVersion::class);
});

it('RECHAZA un archivo de formato no permitido', function () {
    Queue::fake();

    $this->actingAs($this->gestor)
        ->post(route('admin.knowledge.store'), [
            'title' => 'Malicioso',
            'type' => 'other',
            'document' => UploadedFile::fake()->create('script.exe', 10),
        ])
        ->assertSessionHasErrors('document');

    expect(KnowledgeDocument::count())->toBe(0);
});

it('rechaza subir DOS VECES el mismo archivo', function () {
    Queue::fake();

    $document = KnowledgeDocument::create([
        'title' => 'Manual', 'type' => 'manual', 'status' => 'draft',
    ]);

    $service = app(KnowledgeService::class);
    $service->addVersion($document, markdownFile());

    // Reprocesar un archivo idéntico gastaría minutos de vectorización para
    // acabar con un índice exactamente igual.
    expect(fn () => $service->addVersion($document, markdownFile()))
        ->toThrow(RuntimeException::class);
});

it('cada carga crea una version NUEVA, no sustituye la anterior', function () {
    Queue::fake();

    $document = KnowledgeDocument::create(['title' => 'Manual', 'type' => 'manual', 'status' => 'draft']);
    $service = app(KnowledgeService::class);

    $service->addVersion($document, markdownFile('v1.md'));
    $service->addVersion($document, UploadedFile::fake()->createWithContent('v2.md', '# Otro contenido distinto del primero'));

    expect($document->versions()->count())->toBe(2)
        ->and($document->currentVersion()->version)->toBe(2);
});

// ------------------------------------------------------------- ingesta

it('procesa un documento de principio a fin', function () {
    $document = KnowledgeDocument::create(['title' => 'Procedimiento del proyector', 'type' => 'procedure', 'status' => 'draft']);
    $service = app(KnowledgeService::class);

    Queue::fake();
    $version = $service->addVersion($document, markdownFile());

    // Se ejecuta la tubería directamente para observar el resultado.
    app(DocumentIngestionPipeline::class)->process($version->fresh());

    $version->refresh();

    expect($version->processing_status)->toBe('indexed')
        ->and($version->chunk_count)->toBeGreaterThan(0)
        ->and($version->indexed_at)->not->toBeNull()
        ->and($version->processing_error)->toBeNull();
});

it('los fragmentos conservan su seccion para poder citar', function () {
    $document = KnowledgeDocument::create(['title' => 'Procedimiento del proyector', 'type' => 'procedure', 'status' => 'draft']);
    $service = app(KnowledgeService::class);

    Queue::fake();
    $version = $service->addVersion($document, markdownFile());
    app(DocumentIngestionPipeline::class)->process($version->fresh());

    $secciones = KnowledgeChunk::pluck('section_title')->filter()->unique()->values()->all();

    expect($secciones)->toContain('Revisión inicial', 'Pasos a seguir');

    // Sin procedencia, una respuesta del asistente no se puede verificar,
    // y lo que no se puede verificar no se distingue de lo inventado.
    $chunk = KnowledgeChunk::whereNotNull('section_title')->first();
    expect($chunk->citation())->toContain('Procedimiento del proyector');
});

it('NO parte un procedimiento numerado por la mitad', function () {
    // Regla que manda sobre el tamaño: entregar "los pasos 1 al 4" como si
    // fueran el procedimiento completo hace que el docente crea que terminó
    // cuando va por la mitad (plan 14.2).
    $chunker = new Chunker;

    $procedimiento = "Pasos:\n\n".implode("\n", array_map(
        static fn (int $i): string => "{$i}. Este es el paso número {$i} del procedimiento, con texto suficiente para ocupar espacio y forzar el corte por tamaño si no estuviera protegido.",
        range(1, 12)
    ));

    $chunks = $chunker->chunk([['page' => null, 'section' => 'Pasos', 'text' => $procedimiento]], 'Manual');

    expect($chunks)->toHaveCount(1)
        ->and($chunks[0]['content'])->toContain('paso número 1')
        ->and($chunks[0]['content'])->toContain('paso número 12');
});

it('cada fragmento lleva el titulo del documento y su seccion', function () {
    // Va DENTRO del contenido, no solo en los metadatos: al generar el
    // vector, saber a qué documento y sección pertenece un texto cambia por
    // completo lo que ese vector representa.
    $chunker = new Chunker;

    $chunks = $chunker->chunk(
        [['page' => 3, 'section' => 'Encendido', 'text' => str_repeat('Texto del procedimiento de encendido del equipo. ', 10)]],
        'Manual del proyector'
    );

    expect($chunks[0]['content'])->toContain('Manual del proyector — Encendido')
        ->and($chunks[0]['page'])->toBe(3);
});

it('genera vectores con su modelo y su norma', function () {
    $document = KnowledgeDocument::create(['title' => 'Manual', 'type' => 'manual', 'status' => 'draft']);
    $service = app(KnowledgeService::class);

    Queue::fake();
    $version = $service->addVersion($document, markdownFile());
    app(DocumentIngestionPipeline::class)->process($version->fresh());

    $chunk = KnowledgeChunk::first();

    expect($chunk->embedding)->toBeArray()->not->toBeEmpty()
        // El identificador del modelo NO es informativo: cambiar de modelo
        // invalida los vectores, y mezclarlos daría resultados
        // silenciosamente incorrectos (plan 14.5).
        ->and($chunk->embedding_model)->not->toBeNull()
        ->and($chunk->embedding_norm)->toBeGreaterThan(0);
});

// ------------------------------------------------------------- fallos

it('marca el fallo con un motivo COMPRENSIBLE si no hay texto', function () {
    $document = KnowledgeDocument::create(['title' => 'Escaneo', 'type' => 'manual', 'status' => 'draft']);
    $service = app(KnowledgeService::class);

    Queue::fake();
    $version = $service->addVersion($document, UploadedFile::fake()->createWithContent('vacio.txt', '   '));

    app(DocumentIngestionPipeline::class)->process($version->fresh());
    $version->refresh();

    expect($version->processing_status)->toBe('failed')
        // "Error" a secas obligaría a abrir los logs.
        ->and($version->processing_error)->not->toBeEmpty();
});

it('marca el fallo si el archivo desaparecio del almacenamiento', function () {
    $document = KnowledgeDocument::create(['title' => 'Perdido', 'type' => 'manual', 'status' => 'draft']);
    $service = app(KnowledgeService::class);

    Queue::fake();
    $version = $service->addVersion($document, markdownFile());

    Storage::disk(config('incidencias.media.disk'))->delete($version->file_path);

    app(DocumentIngestionPipeline::class)->process($version->fresh());

    expect($version->fresh()->processing_status)->toBe('failed');
});

it('reindexar SUSTITUYE los fragmentos, no los duplica', function () {
    // Si quedaran los anteriores, el asistente respondería con contenido de
    // dos versiones a la vez.
    $document = KnowledgeDocument::create(['title' => 'Manual', 'type' => 'manual', 'status' => 'draft']);
    $service = app(KnowledgeService::class);

    Queue::fake();
    $version = $service->addVersion($document, markdownFile());

    $pipeline = app(DocumentIngestionPipeline::class);
    $pipeline->process($version->fresh());
    $primeraVez = KnowledgeChunk::count();

    $pipeline->process($version->fresh());

    expect(KnowledgeChunk::count())->toBe($primeraVez);
});

// -------------------------------------------------------- publicacion

it('IMPIDE publicar un documento que no esta indexado', function () {
    Queue::fake();

    $document = KnowledgeDocument::create(['title' => 'Manual', 'type' => 'manual', 'status' => 'draft']);
    app(KnowledgeService::class)->addVersion($document, markdownFile());

    $this->actingAs($this->gestor)
        ->post(route('admin.knowledge.publish', $document))
        ->assertSessionHas('error');

    expect($document->fresh()->status)->toBe('draft');
});

it('publica un documento ya indexado', function () {
    Queue::fake();

    $document = KnowledgeDocument::create(['title' => 'Manual', 'type' => 'manual', 'status' => 'draft']);
    $version = app(KnowledgeService::class)->addVersion($document, markdownFile());
    app(DocumentIngestionPipeline::class)->process($version->fresh());

    $this->actingAs($this->gestor)
        ->post(route('admin.knowledge.publish', $document))
        ->assertSessionHas('status');

    expect($document->fresh()->isUsable())->toBeTrue();
});

it('un documento publicado pero NO indexado no esta disponible', function () {
    // Las dos condiciones son necesarias: publicado e indexado.
    Queue::fake();

    $document = KnowledgeDocument::create(['title' => 'Manual', 'type' => 'manual', 'status' => 'published']);
    app(KnowledgeService::class)->addVersion($document, markdownFile());

    expect($document->fresh()->isUsable())->toBeFalse();
});

it('archivar conserva el documento', function () {
    // Un documento archivado sigue explicando POR QUÉ una incidencia pasada
    // se resolvió como se resolvió. Borrarlo dejaría esas respuestas sin
    // fuente verificable.
    Queue::fake();

    $document = KnowledgeDocument::create(['title' => 'Viejo', 'type' => 'manual', 'status' => 'published']);

    $this->actingAs($this->gestor)->post(route('admin.knowledge.archive', $document));

    expect($document->fresh()->status)->toBe('archived')
        ->and(KnowledgeDocument::find($document->id))->not->toBeNull();
});

// ----------------------------------------------------------- descarga

it('la descarga del original exige permiso', function () {
    Queue::fake();

    $document = KnowledgeDocument::create(['title' => 'Interno', 'type' => 'procedure', 'status' => 'draft']);
    $version = app(KnowledgeService::class)->addVersion($document, markdownFile());

    // Sin sesión no se descarga: los documentos de soporte pueden contener
    // información interna.
    $this->get(route('admin.knowledge.download', $version))->assertRedirect(route('login'));

    $this->actingAs($this->gestor)->get(route('admin.knowledge.download', $version))->assertOk();
});

it('el estado de procesamiento se ve en el panel', function () {
    Queue::fake();

    $document = KnowledgeDocument::create(['title' => 'En proceso', 'type' => 'manual', 'status' => 'draft']);
    app(KnowledgeService::class)->addVersion($document, markdownFile());

    $this->actingAs($this->gestor)
        ->get(route('admin.knowledge.show', $document))
        ->assertOk()
        ->assertSee(KnowledgeDocumentVersion::statusLabels()['pending']);
});
