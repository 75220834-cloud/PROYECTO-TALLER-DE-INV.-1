<?php

declare(strict_types=1);

use App\Modules\Assistant\Contracts\Classification;
use App\Modules\Assistant\Contracts\LlmProvider;
use App\Modules\Assistant\Providers\FakeLlmProvider;
use App\Modules\Assistant\Providers\NullLlmProvider;
use App\Modules\Assistant\Services\IntentClassifier;
use App\Modules\Diagnostics\Engine\DiagnosticEngine;
use App\Modules\Diagnostics\Models\DiagnosticStep;
use App\Modules\Incidents\Models\IncidentCategory;
use App\Modules\Media\Models\MediaAsset;
use App\Modules\Media\Services\MediaResolver;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\DemoDiagnosticsSeeder;
use Database\Seeders\DemoMediaSeeder;

/**
 * Pruebas de ALUCINACION (plan 17.6).
 *
 * Criterio de aceptacion duro del plan: CERO alucinaciones. Una sola
 * invencion de un procedimiento, una categoria o una imagen bloquea el paso
 * a piloto. No es un criterio de calidad, es uno de seguridad: un docente
 * siguiendo una instruccion inventada puede acabar manipulando el
 * componente equivocado.
 *
 * La defensa NO se confia al prompt. Se comprueba que el codigo descarta
 * cualquier salida del modelo que no exista en el catalogo real.
 */
beforeEach(function () {
    $this->seed(CatalogSeeder::class);
    $this->seed(DemoMediaSeeder::class);
    $this->seed(DemoDiagnosticsSeeder::class);
});

/** Proveedor que devuelve exactamente lo que se le diga. */
function lyingProvider(?string $label, float $confidence = 0.99): LlmProvider
{
    return new class($label, $confidence) implements LlmProvider
    {
        public function __construct(private ?string $label, private float $confidence) {}

        public function classify(string $text, array $allowedLabels): Classification
        {
            // Miente a proposito: devuelve la etiqueta SIN validarla contra
            // la lista permitida, que es exactamente lo que haria un modelo
            // que se inventa una categoria.
            return new Classification($this->label, $this->confidence, [], 'liar');
        }

        public function rephrase(string $text, string $context = ''): ?string
        {
            return null;
        }

        public function isAvailable(): bool
        {
            return true;
        }

        public function identifier(): string
        {
            return 'liar';
        }
    };
}

// ------------------------------------------------- categorias inventadas

it('DESCARTA una categoria que el modelo se invento', function () {
    // El modelo devuelve "PROYECTOR_HOLOGRAFICO", que no existe en ningun
    // catalogo. El sistema no puede aceptarla bajo ninguna circunstancia.
    $classifier = new IntentClassifier(lyingProvider('PROYECTOR_HOLOGRAFICO'));

    $result = $classifier->classify('algo raro pasa con el equipo');

    expect($result->label)->not->toBe('PROYECTOR_HOLOGRAFICO');

    if ($result->label !== null) {
        expect(IncidentCategory::where('code', $result->label)->exists())->toBeTrue();
    }
});

it('la etiqueta devuelta SIEMPRE existe en el catalogo', function () {
    $classifier = app(IntentClassifier::class);

    $frases = [
        'no se ve nada en la pantalla',
        'el proyector no prende',
        'no sale sonido de los parlantes',
        'la compu no jala',
        'no hay internet',
        'se rompió la ventana del aula',
        '¿a qué hora es el examen?',
        'asdkjhaskjdh',
        '',
    ];

    foreach ($frases as $frase) {
        $result = $classifier->classify($frase);

        if ($result->label !== null) {
            expect(IncidentCategory::where('code', $result->label)->exists())
                ->toBeTrue("Categoría inventada para: «{$frase}»");
        }
    }
});

it('no clasifica lo que no es una incidencia tecnologica', function () {
    $classifier = app(IntentClassifier::class);

    // Sin coincidencias no hay etiqueta. Preferimos que el docente elija
    // con botones antes que asignarle una categoria al azar.
    expect($classifier->classify('¿a qué hora es el examen final?')->label)->toBeNull()
        ->and($classifier->classify('necesito tiza para la pizarra')->label)->toBeNull();
});

// ---------------------------------------------------- imagenes inventadas

it('DESCARTA un codigo de imagen que el modelo se invento', function () {
    // Es la version visual de una alucinacion, y la mas peligrosa: una
    // imagen plausible pero incorrecta lleva al docente a manipular el
    // conector equivocado, y no "suena" mal como suena un texto falso.
    $resolver = app(MediaResolver::class);

    expect($resolver->fromSuggestedCode('FOTO-QUE-NO-EXISTE'))->toBeNull()
        ->and($resolver->fromSuggestedCode('../../etc/passwd'))->toBeNull()
        ->and($resolver->fromSuggestedCode(''))->toBeNull()
        ->and($resolver->fromSuggestedCode(null))->toBeNull();
});

it('solo acepta codigos de imagen que existen de verdad', function () {
    $resolver = app(MediaResolver::class);
    $real = MediaAsset::first();

    expect($resolver->fromSuggestedCode($real->code)?->id)->toBe($real->id);
});

it('no devuelve una imagen despublicada aunque el codigo exista', function () {
    $resolver = app(MediaResolver::class);
    $asset = MediaAsset::first();
    $asset->update(['is_active' => false]);

    expect($resolver->fromSuggestedCode($asset->code))->toBeNull();
});

// -------------------------------------------- procedimientos inventados

it('NO inventa pasos para una categoria sin procedimiento cargado', function () {
    // Es la alucinacion mas grave posible: fabricar un procedimiento
    // institucional. El sistema debe reconocer que no lo tiene.
    $engine = app(DiagnosticEngine::class);

    foreach (['KEYBOARD', 'MOUSE', 'SOFTWARE', 'OTHER'] as $code) {
        $category = IncidentCategory::where('code', $code)->firstOrFail();

        expect($engine->flowFor($category->id))
            ->toBeNull("Se inventó un procedimiento para {$code}");
    }
});

it('los pasos que se muestran vienen SIEMPRE del arbol guardado', function () {
    // No hay generacion de texto en el camino: lo que ve el docente esta
    // literalmente en la base de datos y alguien lo aprobó.
    $engine = app(DiagnosticEngine::class);
    $projector = IncidentCategory::where('code', 'PROJECTOR')->firstOrFail();

    $version = $engine->flowFor($projector->id);

    foreach ($version->steps as $step) {
        expect($step->prompt_text)->not->toBeEmpty()
            ->and(DiagnosticStep::where('id', $step->id)->exists())
            ->toBeTrue();
    }
});

// ----------------------------------------- el sistema funciona sin IA

it('clasifica por palabras clave cuando NO hay modelo', function () {
    // Requisito no negociable del plan: sin modelo el sistema conserva toda
    // su funcionalidad. Aqui pierde comodidad, no capacidad.
    $classifier = new IntentClassifier(new NullLlmProvider);

    $result = $classifier->classify('el proyector no muestra nada');

    expect($result->label)->toBe('PROJECTOR')
        ->and($result->method)->toBe('keywords');
});

it('sin modelo NUNCA asume una categoria por su cuenta', function () {
    // Con los umbrales sin calibrar todavia, la politica es conservadora:
    // se propone, nunca se decide (plan 13.5).
    $classifier = new IntentClassifier(new NullLlmProvider);

    $result = $classifier->classify('el proyector no muestra nada');

    expect($classifier->decide($result))->toBe('ask');
});

it('cae a modo manual cuando no reconoce nada', function () {
    $classifier = new IntentClassifier(new NullLlmProvider);

    expect($classifier->decide($classifier->classify('xyz abc')))->toBe('manual');
});

// -------------------------------------------------------- ambiguedad

it('marca como AMBIGUO lo que puede ser varias cosas', function () {
    // "No se ve nada" puede ser el proyector, la pantalla o la PC. Asumir
    // una y arrancar el arbol equivocado hace perder mas tiempo que
    // preguntar (plan 17.5).
    $classifier = new IntentClassifier(new FakeLlmProvider);

    $result = $classifier->classify('no se ve nada en la pantalla del proyector');

    expect($result->alternatives)->not->toBeEmpty()
        ->and($classifier->decide($result))->not->toBe('accept');
});

it('tolera errores ortograficos y lenguaje coloquial', function () {
    $classifier = app(IntentClassifier::class);

    expect($classifier->classify('el cañon no prende')->label)->toBe('PROJECTOR')
        ->and($classifier->classify('la compu no enciende')->label)->toBe('COMPUTER')
        ->and($classifier->classify('no hay wifi')->label)->toBe('INTERNET');
});
