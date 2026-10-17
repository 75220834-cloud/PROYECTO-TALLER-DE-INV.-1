<?php

declare(strict_types=1);

use App\Modules\Diagnostics\Models\DiagnosticFlow;
use App\Modules\Diagnostics\Models\DiagnosticStep;
use App\Modules\Media\Models\MediaAsset;
use App\Modules\Media\Services\MediaResolver;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\PilotDiagnosticsSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Los arboles de diagnostico reales: que cada uno sea un recorrido sano, y
 * que las imagenes salgan donde deben.
 *
 * Se recorre el arbol publicado, no la definicion del sembrado: es lo que de
 * verdad ve el docente.
 */
beforeEach(function () {
    $this->seed(CatalogSeeder::class);
    $this->seed(PilotDiagnosticsSeeder::class);

    /** @return Collection<string, DiagnosticStep> */
    $this->pasos = function (DiagnosticFlow $flow): Collection {
        $version = $flow->versions()->orderByDesc('version')->first();

        return DiagnosticStep::where('flow_version_id', $version->id)
            ->orderBy('sort_order')->get()->keyBy('step_key');
    };
});

dataset('arboles', ['COMPUTER', 'PROJECTOR', 'HDMI', 'PROJECTOR_REMOTE', 'SCREEN', 'SPEAKER', 'MOUSE', 'KEYBOARD']);

function arbolDe(string $codigo): DiagnosticFlow
{
    return DiagnosticFlow::whereHas('category', fn ($q) => $q->where('code', $codigo))->firstOrFail();
}

it('ofrece entre cuatro y cinco soluciones en cada camino', function (string $codigo) {
    // El pedido: cuatro o cinco soluciones reales, no dos y a soporte. Se
    // cuenta cada camino desde la primera pregunta hasta llamar a soporte.
    $pasos = ($this->pasos)(arbolDe($codigo));
    $primero = $pasos->first();

    $caminos = [];
    $recorrer = function (string $key, int $acciones, array $vistos) use (&$recorrer, $pasos, &$caminos) {
        $paso = $pasos[$key];
        expect($vistos)->not->toContain($key);   // sin ciclos

        if ($paso->is_terminal) {
            if ($paso->terminal_outcome === 'escalate') {
                $caminos[] = $acciones;
            }

            return;
        }

        $esAccion = collect($paso->answer_options)->contains('value', 'funciona');

        foreach (array_unique(array_values($paso->next_step_map)) as $siguiente) {
            $recorrer($siguiente, $acciones + ($esAccion ? 1 : 0), [...$vistos, $key]);
        }
    };
    $recorrer($primero->step_key, 0, []);

    expect($caminos)->not->toBeEmpty();
    // Los caminos cortos son los que llegan por una pregunta que ya
    // descarto otras causas; ninguno baja de tres.
    expect(min($caminos))->toBeGreaterThanOrEqual(3)
        ->and(max($caminos))->toBeLessThanOrEqual(5)
        ->and(collect($pasos)->filter(fn ($p) => collect($p->answer_options)->contains('value', 'funciona'))->count())
        ->toBeGreaterThanOrEqual(4);
})->with('arboles');

it('deja terminar en cualquier solucion con «ya funciona»', function (string $codigo) {
    $acciones = ($this->pasos)(arbolDe($codigo))
        ->reject(fn ($p) => $p->is_terminal)
        ->filter(fn ($p) => collect($p->answer_options)->contains('value', 'funciona'));

    foreach ($acciones as $paso) {
        expect($paso->next_step_map['funciona'])->toBe('END_RESOLVED');
    }
})->with('arboles');

it('toda rama lleva a un paso que existe', function (string $codigo) {
    $pasos = ($this->pasos)(arbolDe($codigo));

    foreach ($pasos->reject(fn ($p) => $p->is_terminal) as $paso) {
        foreach ($paso->next_step_map as $destino) {
            expect($pasos->has($destino))->toBeTrue("{$paso->step_key} lleva a «{$destino}», que no existe");
        }

        // Cada opcion tiene su destino y cada destino su opcion.
        expect(collect($paso->answer_options)->pluck('value')->sort()->values()->all())
            ->toBe(collect(array_keys($paso->next_step_map))->sort()->values()->all());
    }
})->with('arboles');

it('ningun recorrido supera el limite de pasos del sistema', function (string $codigo) {
    $pasos = ($this->pasos)(arbolDe($codigo));
    $limite = (int) config('incidencias.assistant.max_diagnostic_steps');

    $profundidad = function (string $key) use (&$profundidad, $pasos): int {
        $paso = $pasos[$key];

        return $paso->is_terminal ? 0 : 1 + max(array_map($profundidad, array_values($paso->next_step_map)));
    };

    expect($profundidad($pasos->first()->step_key))->toBeLessThanOrEqual($limite);
})->with('arboles');

it('ningun paso pide abrir equipos, subirse a algo ni tocar instalaciones', function (string $codigo) {
    $prohibido = '/destornillador|tornillo|carcasa|desarm|tablero el[eé]ctrico|s[uú]bete|ab[rí](e|ir) (el|la) (computadora|proyector|caja|parlante)/iu';

    foreach (($this->pasos)(arbolDe($codigo)) as $paso) {
        expect($paso->prompt_text.' '.$paso->help_text)->not->toMatch($prohibido);
    }
})->with('arboles');

it('pregunta por el tipo de equipo en vez de suponerlo', function () {
    // El checklist no dice si el ecran es electrico o manual, ni si el mouse
    // tiene cable. Dar instrucciones para un equipo que no esta seria peor
    // que no darlas.
    expect(($this->pasos)(arbolDe('SCREEN'))->first()->step_key)->toBe('tipo_ecran')
        ->and(($this->pasos)(arbolDe('MOUSE'))->first()->step_key)->toBe('tipo_mouse');
});

/* ------------------------------------------------------------------ */
/* Imagenes */
/* ------------------------------------------------------------------ */

describe('imagenes provisionales', function () {
    beforeEach(fn () => Storage::fake(config('incidencias.media.disk')));

    it('carga una ilustracion por cada clave que usan los pasos', function () {
        $claves = (new PilotDiagnosticsSeeder)->componentKeys();

        $this->artisan('imagenes:provisionales')->assertSuccessful();
        $this->artisan('imagenes:provisionales')->assertSuccessful();   // dos veces: no duplica

        expect(MediaAsset::where('code', 'like', 'PROV-%')->count())->toBe(count($claves))
            ->and(MediaAsset::where('code', 'like', 'PROV-%')->pluck('scope_key')->sort()->values()->all())
            ->toBe(collect($claves)->sort()->values()->all())
            ->and(MediaAsset::where('code', 'like', 'PROV-%')->where('is_demo', false)->count())->toBe(0);
    });

    it('cada ilustracion dice que es provisional', function () {
        foreach (glob(base_path('database/imagenes-provisionales/*.svg')) as $archivo) {
            expect(file_get_contents($archivo))->toContain('PROVISIONAL')
                ->and(file_get_contents($archivo))->not->toContain('<script');
        }
    });

    it('se quitan con --quitar', function () {
        $this->artisan('imagenes:provisionales')->assertSuccessful();
        $this->artisan('imagenes:provisionales', ['--quitar' => true])->assertSuccessful();

        expect(MediaAsset::withTrashed()->where('code', 'like', 'PROV-%')->count())->toBe(0);
    });

    it('la foto real gana a la provisional sin quitar nada', function () {
        // El defecto: la busqueda tomaba la primera fila, que era la
        // provisional, y subir la foto real no cambiaba nada.
        $this->artisan('imagenes:provisionales')->assertSuccessful();

        $real = MediaAsset::create([
            'code' => 'FOTO-HDMI-REAL', 'title' => 'Puerto HDMI', 'alt_text' => 'Foto del puerto HDMI',
            'type' => 'photo', 'file_path' => 'media/x.webp', 'source' => 'Soporte', 'license' => 'Interna',
            'scope_type' => 'component', 'scope_key' => 'hdmi_port', 'is_active' => true, 'is_demo' => false,
        ]);

        $resolver = app(MediaResolver::class);

        expect($resolver->primaryFor(collect(), 'hdmi_port')?->id)->toBe($real->id)
            ->and($resolver->componentReference('hdmi_port')?->id)->toBe($real->id)
            // Con foto real, la provisional no aparece ni como otra vista.
            ->and($resolver->alternatesFor(collect(), $real, 'hdmi_port')->pluck('code')->all())->not->toContain('PROV-HDMI-PORT');
    });

    it('otras fotos reales del mismo componente salen como «otra vista»', function () {
        // El boton de encendido de una todo en uno, de una torre y de un NUC
        // se suben con la misma clave: el docente ve la de su equipo.
        $foto = fn (string $code) => MediaAsset::create([
            'code' => $code, 'title' => $code, 'alt_text' => $code, 'type' => 'photo', 'file_path' => "media/{$code}.webp",
            'source' => 'Soporte', 'license' => 'Interna', 'scope_type' => 'component',
            'scope_key' => 'computer_power_button', 'is_active' => true, 'is_demo' => false,
        ]);
        $aio = $foto('BOTON-AIO');
        $torre = $foto('BOTON-TORRE');

        $resolver = app(MediaResolver::class);
        $primera = $resolver->primaryFor(collect(), 'computer_power_button');

        expect($primera->id)->toBe($torre->id)   // la mas reciente
            ->and($resolver->alternatesFor(collect(), $primera, 'computer_power_button')->pluck('id')->all())->toBe([$aio->id]);
    });
});
