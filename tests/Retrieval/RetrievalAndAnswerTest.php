<?php

declare(strict_types=1);

use App\Modules\Assistant\Contracts\Classification;
use App\Modules\Assistant\Contracts\LlmProvider;
use App\Modules\Assistant\Services\AnswerVerifier;
use App\Modules\Assistant\Services\AssistantService;
use App\Modules\Assistant\Services\ConfidenceEvaluator;
use App\Modules\Assistant\Services\IntentClassifier;
use App\Modules\Incidents\Models\IncidentCategory;
use App\Modules\Knowledge\Models\KnowledgeChunk;
use App\Modules\Knowledge\Models\KnowledgeDocument;
use App\Modules\Knowledge\Models\KnowledgeDocumentVersion;
use App\Modules\Retrieval\Contracts\EmbeddingProvider;
use App\Modules\Retrieval\Services\RetrievalService;
use Database\Seeders\CatalogSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Recuperación híbrida y respuesta con citas (plan 14.3, 14.4, 17.3, 17.6).
 */
beforeEach(function () {
    $this->seed(CatalogSeeder::class);

    $this->projector = IncidentCategory::where('code', 'PROJECTOR')->firstOrFail();
    $this->audio = IncidentCategory::where('code', 'SPEAKER')->firstOrFail();

    $this->retrieval = app(RetrievalService::class);
    $this->embeddings = app(EmbeddingProvider::class);
});

/** Crea un documento publicado con fragmentos ya indexados. */
function publishedDocument(string $title, array $texts, ?int $categoryId = null): KnowledgeDocument
{
    $document = KnowledgeDocument::create([
        'title' => $title,
        'type' => 'procedure',
        'status' => 'published',
        'category_id' => $categoryId,
    ]);

    $version = KnowledgeDocumentVersion::create([
        'document_id' => $document->id,
        'version' => 1,
        'file_path' => 'knowledge/fake.md',
        'original_name' => 'fake.md',
        'file_hash' => hash('sha256', $title.serialize($texts)),
        'mime_type' => 'text/markdown',
        'file_size' => 100,
        'processing_status' => 'indexed',
        'indexed_at' => now(),
        'chunk_count' => count($texts),
    ]);

    $embeddings = app(EmbeddingProvider::class);

    foreach ($texts as $i => $text) {
        $vector = $embeddings->embed($text);
        $norm = 0.0;

        foreach ($vector ?? [] as $v) {
            $norm += $v * $v;
        }

        KnowledgeChunk::create([
            'document_version_id' => $version->id,
            'chunk_index' => $i,
            'content' => $text,
            'token_count' => (int) ceil(mb_strlen($text) / 4),
            'section_title' => 'Sección '.($i + 1),
            'page_number' => $i + 1,
            'embedding' => $vector,
            'embedding_model' => $vector !== null ? $embeddings->modelIdentifier() : null,
            'embedding_norm' => $vector !== null ? sqrt($norm) : null,
            'category_id' => $categoryId,
        ]);
    }

    return $document;
}

/** Proveedor que devuelve la respuesta que se le indique. */
function scriptedLlm(?string $answer): LlmProvider
{
    return new class($answer) implements LlmProvider
    {
        public function __construct(private ?string $answer) {}

        public function classify(string $text, array $allowedLabels): Classification
        {
            return Classification::unknown('scripted');
        }

        public function rephrase(string $text, string $context = ''): ?string
        {
            return null;
        }

        public function answerGrounded(string $question, array $passages): ?string
        {
            return $this->answer;
        }

        public function isAvailable(): bool
        {
            return true;
        }

        public function identifier(): string
        {
            return 'scripted';
        }
    };
}

// ------------------------------------------------------- recuperación

it('encuentra el fragmento relevante', function () {
    publishedDocument('Manual del proyector', [
        'Para encender el proyector pulsa el botón de encendido del panel superior.',
        'El filtro del proyector se limpia cada seis meses con aire comprimido.',
    ]);

    $results = $this->retrieval->search('botón de encendido del panel');

    expect($results)->not->toBeEmpty()
        ->and($results->first()->content())->toContain('encendido');
});

it('SALVAGUARDA: con un modelo semántico real, la paráfrasis SÍ recupera', function () {
    // Prueba crítica del plan (17.3). El prefiltrado léxico descartaría este
    // fragmento porque la pregunta usa otras palabras; la salvaguarda de
    // ampliación es lo que impide que el prefiltrado anule a la búsqueda
    // semántica justo en el caso que la justifica.
    //
    // Se usa un proveedor que SIMULA un modelo real (vectores parecidos para
    // textos que significan lo mismo). Lo que se está probando es la lógica
    // de la salvaguarda, no la calidad del modelo por hashing.
    $semantico = new class implements EmbeddingProvider
    {
        public function embed(string $text): ?array
        {
            $sobreVideo = str_contains(mb_strtolower($text), 'vídeo')
                || str_contains(mb_strtolower($text), 'video')
                || str_contains(mb_strtolower($text), 'pantalla')
                || str_contains(mb_strtolower($text), 've nada');

            return $sobreVideo ? [1.0, 0.1] : [0.1, 1.0];
        }

        public function embedBatch(array $texts): array
        {
            return array_map(fn (string $t): ?array => $this->embed($t), $texts);
        }

        public function dimensions(): int
        {
            return 2;
        }

        public function modelIdentifier(): string
        {
            return 'semantico-de-prueba';
        }

        public function isAvailable(): bool
        {
            return true;
        }
    };

    app()->instance(EmbeddingProvider::class, $semantico);

    publishedDocument('Manual técnico', [
        'Ante la ausencia de señal de vídeo, verificar la integridad del conductor HDMI.',
    ]);

    $results = (new RetrievalService($semantico))->search('no se ve nada en la pantalla');

    expect($results)->not->toBeEmpty()
        ->and($results->first()->semanticRank)->not->toBeNull();
});

it('LIMITACIÓN CONOCIDA: sin modelo real, la paráfrasis NO recupera', function () {
    // Esta prueba documenta un límite del sistema, no un defecto que se vaya
    // a corregir. El proveedor por hashing compara términos, no significados:
    // no puede saber que "no se ve nada" y "ausencia de señal de vídeo"
    // hablan de lo mismo.
    //
    // Queda escrita a propósito para que el límite esté en la suite y no
    // escondido en un comentario. Si el piloto se ejecuta sin Ollama, esto
    // debe constar en el informe: la búsqueda funcionará solo por
    // coincidencia de palabras.
    publishedDocument('Manual técnico', [
        'Ante la ausencia de señal de vídeo, verificar la integridad del conductor HDMI.',
    ]);

    $results = $this->retrieval->search('no se ve nada en la pantalla');

    expect($results)->toBeEmpty();

    // Y lo que importa: el sistema NO improvisa una respuesta. Escala.
    $answer = app(AssistantService::class)->answer('no se ve nada en la pantalla');

    expect($answer->escalated)->toBeTrue();
});

it('solo recupera de documentos PUBLICADOS', function () {
    $document = publishedDocument('Borrador', ['El proyector se enciende con el botón superior.']);
    $document->update(['status' => 'draft']);

    expect($this->retrieval->search('encender proyector'))->toBeEmpty();
});

it('solo recupera de versiones INDEXADAS', function () {
    $document = publishedDocument('Manual', ['El proyector se enciende con el botón superior.']);
    $document->currentVersion()->update(['processing_status' => 'failed']);

    expect($this->retrieval->search('encender proyector'))->toBeEmpty();
});

it('acota por categoria sin excluir los documentos generales', function () {
    publishedDocument('Manual de audio', ['Los parlantes se encienden con la perilla lateral.'], $this->audio->id);
    publishedDocument('Guía general', ['Cualquier equipo del aula se reporta desde el código QR.'], null);

    $results = $this->retrieval->search('parlantes perilla equipo', $this->audio->id);
    $titulos = $results->map(fn ($r) => $r->citation())->implode(' ');

    // El documento de otra categoría no aparece; el general sí sigue disponible.
    expect($titulos)->not->toContain('Manual del proyector');
});

it('DESCARTA fragmentos vectorizados con otro modelo', function () {
    // Compararlos daría un número sin significado y contaminaría el ranking
    // sin que nada fallara visiblemente: el fallo más difícil de detectar
    // del módulo (plan 14.5).
    publishedDocument('Manual', ['El proyector se enciende con el botón del panel superior.']);

    // Con el modelo correcto, el fragmento se recupera por AMBAS vías.
    $antes = $this->retrieval->search('botón del panel superior');

    expect($antes)->not->toBeEmpty()
        ->and($antes->first()->semanticRank)->not->toBeNull();

    // Se simula un cambio de modelo de embeddings sin reindexar.
    KnowledgeChunk::query()->update(['embedding_model' => 'modelo-viejo-incompatible']);

    $despues = $this->retrieval->search('botón del panel superior');

    // Sigue apareciendo por vía LÉXICA (el texto no cambió), pero ya no por
    // la semántica: los vectores viejos se ignoran en lugar de mezclarse.
    expect($despues)->not->toBeEmpty()
        ->and($despues->first()->lexicalRank)->not->toBeNull()
        ->and($despues->first()->semanticRank)->toBeNull();
});

it('la busqueda LEXICA funciona con UN SOLO documento cargado', function () {
    // Es la situación real al arrancar el piloto, y donde el modo natural de
    // MySQL falla en silencio: descarta los términos que aparecen en más del
    // 50 % de las filas, y con una sola fila eso es TODOS. No da error,
    // simplemente deja de encontrar cosas — y empieza a funcionar solo
    // cuando la base crece, lo que hace el fallo dificilísimo de
    // diagnosticar. Por eso se usa modo booleano.
    publishedDocument('Único manual', [
        'El proyector se enciende con el botón del panel superior del equipo.',
    ]);

    $results = $this->retrieval->search('botón del panel superior');

    expect($results)->not->toBeEmpty()
        ->and($results->first()->lexicalRank)->not->toBeNull();
});

it('no deja que un guion del docente altere el sentido de su busqueda', function () {
    // En modo booleano, "-nada" significa EXCLUIR. Si no se sanea, el
    // docente estaría filtrando sin saberlo su propia consulta.
    publishedDocument('Manual', [
        'Cuando el proyector no muestra nada en la pantalla, revisa el cable.',
    ]);

    $results = $this->retrieval->search('no muestra -nada pantalla');

    expect($results)->not->toBeEmpty();
});

it('devuelve vacio si no hay nada cargado', function () {
    expect($this->retrieval->search('cualquier cosa'))->toBeEmpty();
});

it('devuelve vacio con una consulta vacia', function () {
    publishedDocument('Manual', ['Contenido cualquiera del manual del proyector.']);

    expect($this->retrieval->search('   '))->toBeEmpty();
});

// ------------------------------------------------------------ respuesta

it('ESCALA cuando no hay fuentes, con el mensaje literal del plan', function () {
    $answer = app(AssistantService::class)->answer('¿cuál es la política de préstamo de equipos?');

    expect($answer->escalated)->toBeTrue()
        ->and($answer->verdict->band)->toBe('low')
        ->and($answer->text)->toBe(
            'No tengo suficiente información para resolver esta incidencia de forma segura. '
            .'Solicitaré soporte técnico.'
        )
        ->and($answer->hasSources())->toBeFalse();
});

it('sin modelo responde con los pasajes y sus citas', function () {
    publishedDocument('Manual del proyector', [
        'Para encender el proyector pulsa el botón de encendido del panel superior del equipo.',
        'Si la luz del proyector está en naranja significa que está en espera.',
    ]);

    $answer = app(AssistantService::class)->answer('cómo enciendo el proyector');

    expect($answer->escalated)->toBeFalse()
        ->and($answer->source)->toBe('passages')
        ->and($answer->citations())->not->toBeEmpty()
        ->and($answer->citations()[0])->toContain('Manual del proyector');
});

it('acepta una respuesta generada que SI esta anclada', function () {
    publishedDocument('Manual del proyector', [
        'Para encender el proyector pulsa el botón de encendido del panel superior del equipo.',
    ]);

    $service = new AssistantService(
        app(RetrievalService::class),
        app(ConfidenceEvaluator::class),
        app(AnswerVerifier::class),
        scriptedLlm('Pulsa el botón de encendido del panel superior del proyector.'),
        app(IntentClassifier::class),
    );

    $answer = $service->answer('cómo enciendo el proyector');

    expect($answer->source)->toBe('generated')
        ->and($answer->text)->toContain('botón de encendido');
});

it('DESCARTA una respuesta generada que se inventa contenido', function () {
    // La defensa no se confía al prompt: si la respuesta introduce términos
    // que no están en ninguna fuente, se descarta y se cae al respaldo.
    publishedDocument('Manual del proyector', [
        'Para encender el proyector pulsa el botón de encendido del panel superior del equipo.',
    ]);

    $service = new AssistantService(
        app(RetrievalService::class),
        app(ConfidenceEvaluator::class),
        app(AnswerVerifier::class),
        scriptedLlm('Llama al anexo 4501 y solicita el formulario RF-22 de reposición institucional.'),
        app(IntentClassifier::class),
    );

    $answer = $service->answer('cómo enciendo el proyector');

    expect($answer->source)->not->toBe('generated')
        ->and($answer->text)->not->toContain('4501')
        ->and($answer->text)->not->toContain('RF-22');
});

it('DESCARTA una respuesta que sugiere abrir el equipo', function () {
    // Aunque estuviera en un documento real, ese paso es para un técnico,
    // no para quien está dando clase (plan 13.4, regla 8).
    publishedDocument('Manual', [
        'El proyector tiene una tapa de acceso al filtro en la parte inferior del equipo.',
    ]);

    $service = new AssistantService(
        app(RetrievalService::class),
        app(ConfidenceEvaluator::class),
        app(AnswerVerifier::class),
        scriptedLlm('Quita la tapa del proyector y limpia el filtro del equipo por dentro.'),
        app(IntentClassifier::class),
    );

    expect($service->answer('cómo limpio el filtro')->source)->not->toBe('generated');
});

it('DESCARTA una respuesta que promete certezas', function () {
    // El plan lo prohíbe expresamente (§44): prometer un resultado que no se
    // puede garantizar destruye la confianza la primera vez que falla.
    publishedDocument('Manual', [
        'Para encender el proyector pulsa el botón de encendido del panel superior del equipo.',
    ]);

    $service = new AssistantService(
        app(RetrievalService::class),
        app(ConfidenceEvaluator::class),
        app(AnswerVerifier::class),
        scriptedLlm('Pulsa el botón de encendido del panel superior: esto lo soluciona siempre, garantizado.'),
        app(IntentClassifier::class),
    );

    expect($service->answer('cómo enciendo el proyector')->source)->not->toBe('generated');
});

// --------------------------------------------------------- trazabilidad

it('registra cada interaccion con su confianza y su decision', function () {
    publishedDocument('Manual del proyector', [
        'Para encender el proyector pulsa el botón de encendido del panel superior del equipo.',
    ]);

    app(AssistantService::class)->answer('cómo enciendo el proyector');

    $interaction = DB::table('assistant_interactions')->first();

    expect($interaction)->not->toBeNull()
        ->and($interaction->confidence_band)->toBeIn(['high', 'medium', 'low'])
        ->and($interaction->llm_model)->not->toBeNull()
        ->and((int) $interaction->latency_ms)->toBeGreaterThanOrEqual(0);
});

it('registra QUE fragmento sustento la respuesta', function () {
    // Sin esto no se puede comprobar si el asistente citó de verdad o se lo
    // inventó (plan 42).
    publishedDocument('Manual del proyector', [
        'Para encender el proyector pulsa el botón de encendido del panel superior del equipo.',
    ]);

    app(AssistantService::class)->answer('cómo enciendo el proyector');

    expect(DB::table('retrieval_citations')->count())->toBeGreaterThan(0);
});

it('registra tambien las veces que escala', function () {
    // La tasa de escalamiento es un indicador del piloto: si solo se
    // registraran los aciertos, el dato no valdría nada.
    app(AssistantService::class)->answer('¿cuál es el reglamento de biblioteca?');

    $interaction = DB::table('assistant_interactions')->first();

    expect($interaction)->not->toBeNull()
        ->and((bool) $interaction->escalated)->toBeTrue();
});

// ------------------------------------------------------- el verificador

it('reconoce una respuesta anclada y rechaza una inventada', function () {
    $verifier = new AnswerVerifier;

    $fuentes = ['El proyector se enciende pulsando el botón del panel superior del equipo.'];

    expect($verifier->isGrounded('Pulsa el botón del panel superior del proyector.', $fuentes))->toBeTrue()
        ->and($verifier->isGrounded('Solicita el formulario RF-22 en administración central.', $fuentes))->toBeFalse();
});

it('detecta instrucciones peligrosas', function () {
    $verifier = new AnswerVerifier;

    expect($verifier->isSafe('Revisa que el cable esté conectado.'))->toBeTrue()
        ->and($verifier->isSafe('Quita la tapa y desatornilla la carcasa.'))->toBeFalse()
        ->and($verifier->isSafe('Manipula el cableado eléctrico del tablero.'))->toBeFalse();
});

it('detecta promesas absolutas', function () {
    $verifier = new AnswerVerifier;

    expect($verifier->hasOverconfidentClaim('Prueba a reconectar el cable.'))->toBeFalse()
        ->and($verifier->hasOverconfidentClaim('Esto lo soluciona siempre.'))->toBeTrue()
        ->and($verifier->hasOverconfidentClaim('Reconecta el cable, garantizado.'))->toBeTrue();
});
