<?php

declare(strict_types=1);

use App\Modules\Diagnostics\Models\DiagnosticStep;
use App\Modules\Media\Models\MediaAsset;
use App\Modules\Media\Services\MediaResolver;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\DemoDiagnosticsSeeder;
use Database\Seeders\DemoMediaSeeder;
use Illuminate\Support\Facades\Storage;

/**
 * Integridad del banco visual (plan 17.10).
 */
beforeEach(function () {
    $this->seed(CatalogSeeder::class);
    $this->seed(DemoMediaSeeder::class);
    $this->seed(DemoDiagnosticsSeeder::class);

    $this->resolver = app(MediaResolver::class);
});

// ------------------------------------------------------------ integridad

it('ninguna imagen publicada carece de texto alternativo, fuente ni licencia', function () {
    // No es burocracia: sin alt_text la imagen es inaccesible y ademas
    // inutil si no carga; sin procedencia no se puede publicar.
    foreach (MediaAsset::where('is_active', true)->get() as $asset) {
        expect($asset->alt_text)->not->toBeEmpty("Sin alt_text: {$asset->code}")
            ->and($asset->source)->not->toBeEmpty("Sin fuente: {$asset->code}")
            ->and($asset->license)->not->toBeEmpty("Sin licencia: {$asset->code}");
    }
});

it('ninguna imagen apunta a un archivo que no existe', function () {
    $disk = Storage::disk(config('incidencias.media.disk'));

    foreach (MediaAsset::where('is_active', true)->get() as $asset) {
        expect($disk->exists($asset->file_path))->toBeTrue("Archivo perdido: {$asset->code}");
    }
});

it('ninguna imagen supera el presupuesto de peso', function () {
    // Se sirve a un celular, en un aula, con una clase esperando.
    foreach (MediaAsset::where('is_active', true)->get() as $asset) {
        expect($asset->exceedsBudget())->toBeFalse(
            "Pesa {$asset->sizeKb()} KB y el tope son ".round(config('incidencias.media.max_bytes') / 1024).' KB: '.$asset->code
        );
    }
});

// -------------------------------------------------------------- cascada

it('resuelve la imagen del paso por su componente', function () {
    $step = DiagnosticStep::where('component_key', 'hdmi_port')->first();

    expect($step)->not->toBeNull();

    $primary = $this->resolver->primaryFor($step->media, $step->component_key);

    expect($primary)->not->toBeNull()
        ->and($primary->scope_key)->toBe('hdmi_port');
});

it('DEGRADA a solo texto cuando no hay imagen para el componente', function () {
    // El escalon mas importante de la cascada: el sistema nunca falla ni
    // muestra un hueco roto por ausencia de imagen.
    $primary = $this->resolver->primaryFor(collect(), 'componente_inexistente');

    expect($primary)->toBeNull();
});

it('no usa una imagen despublicada aunque este asignada al paso', function () {
    $step = DiagnosticStep::where('component_key', 'hdmi_port')->first();

    MediaAsset::where('scope_key', 'hdmi_port')->update(['is_active' => false]);

    $primary = $this->resolver->primaryFor(
        $step->media()->where('is_active', true)->get(),
        $step->component_key
    );

    expect($primary)->toBeNull();
});

// ------------------------------------------------------- cobertura visual

it('todo paso que menciona una pieza fisica tiene imagen', function () {
    // Un paso que dice "revisa el cable HDMI" sin imagen es un defecto, no
    // una omision menor: es justo el docente que no sabe que es un HDMI
    // quien mas necesita el sistema.
    $sinImagen = [];

    foreach (DiagnosticStep::whereNotNull('component_key')->with('media')->get() as $step) {
        if ($this->resolver->primaryFor($step->media, $step->component_key) === null) {
            $sinImagen[] = $step->step_key;
        }
    }

    expect($sinImagen)->toBeEmpty('Pasos sin apoyo visual: '.implode(', ', $sinImagen));
});

// ------------------------------------------------------------- servicio

it('sirve la imagen sin exigir autenticacion', function () {
    // El docente no esta autenticado y necesita ver las imagenes.
    $asset = MediaAsset::first();

    $this->get(route('media.show', ['asset' => $asset->id]))
        ->assertOk()
        ->assertHeader('Content-Type', $asset->mime_type)
        // El navegador no debe adivinar el tipo: con un SVG del mismo
        // origen, adivinar mal es un XSS.
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('NO sirve una imagen despublicada', function () {
    // Despublicar la retira de verdad, no solo de las pantallas.
    $asset = MediaAsset::first();
    $asset->update(['is_active' => false]);

    $this->get(route('media.show', ['asset' => $asset->id]))->assertNotFound();
});

it('devuelve 404 para una imagen inexistente', function () {
    $this->get(route('media.show', ['asset' => 999999]))->assertNotFound();
});

// ---------------------------------------------------------- privacidad

it('las fotos demo no contienen personas ni datos', function () {
    // Los activos iniciales son diagramas dibujados por el equipo, no
    // fotografias: no hay rostros, ni pantallas con datos, ni metadatos de
    // geolocalizacion que retirar.
    foreach (MediaAsset::where('is_demo', true)->get() as $asset) {
        expect($asset->type)->toBe('diagram')
            ->and($asset->source)->toContain('equipo del proyecto');
    }
});
