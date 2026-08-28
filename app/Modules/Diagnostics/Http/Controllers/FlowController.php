<?php

declare(strict_types=1);

namespace App\Modules\Diagnostics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Diagnostics\Models\DiagnosticFlow;
use App\Modules\Diagnostics\Models\DiagnosticFlowVersion;
use App\Modules\Incidents\Models\IncidentCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Árboles de diagnóstico: creación y versionado (plan CU-A-10).
 *
 * POR QUÉ SE VERSIONA Y NO SE EDITA EN CALIENTE
 *
 * Es un requisito de validez de la investigación, no una comodidad. Cada
 * incidencia guarda LA VERSIÓN del árbol que ejecutó. Si un procedimiento se
 * pudiera editar sobre la marcha, un cambio de hoy alteraría la
 * interpretación de los datos de la semana pasada: se leería «el docente
 * respondió que sí al paso 3» sin saber que el paso 3 preguntaba entonces
 * otra cosa. Con versiones, cada dato histórico sigue significando lo mismo
 * para siempre.
 *
 * De ahí que una versión PUBLICADA sea inmutable. Para cambiar un
 * procedimiento se duplica en un borrador, se edita ahí y se publica: la
 * anterior queda intacta sosteniendo su historia.
 *
 * EL CONTENIDO ES RESPONSABILIDAD DE SOPORTE, no del programador. Por eso
 * esta pantalla existe: hasta ahora los árboles solo entraban por un seeder,
 * y quien sabe cómo se arregla un proyector no debería tener que pedirle a
 * nadie que toque código para escribirlo.
 */
class FlowController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.flows.index', [
            'categories' => IncidentCategory::active()->orderBy('sort_order')->get(),
            'flows' => DiagnosticFlow::with(['category', 'versions'])->get()->keyBy('category_id'),
        ]);
    }

    public function show(DiagnosticFlowVersion $version): View
    {
        return view('admin.flows.show', [
            'version' => $version->load('flow.category'),
            'steps' => $version->steps()->orderBy('sort_order')->get(),
        ]);
    }

    /**
     * Crea un borrador para una categoría que aún no tiene procedimiento.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:incident_categories,id'],
        ]);

        $category = IncidentCategory::findOrFail($data['category_id']);

        $flow = DiagnosticFlow::firstOrCreate(
            ['category_id' => $category->id],
            ['name' => "Diagnóstico de {$category->name}", 'is_active' => true],
        );

        $version = $this->nextDraft($flow);

        $this->audit->record('flow.draft_created', $version, ['category' => $category->code]);

        return redirect()->route('admin.flows.show', $version)
            ->with('status', 'Borrador creado. Añade los pasos y publícalo cuando esté listo.');
    }

    /**
     * Duplica la versión publicada en un borrador editable.
     *
     * Es la única forma de cambiar un procedimiento que ya está en uso, y
     * copia los pasos para no obligar a reescribirlos: casi siempre se
     * retoca una pregunta, no se empieza de cero.
     */
    public function duplicate(DiagnosticFlowVersion $version): RedirectResponse
    {
        $draft = DB::transaction(function () use ($version) {
            $nuevo = $this->nextDraft(DiagnosticFlow::findOrFail($version->flow_id));

            foreach ($version->steps()->orderBy('sort_order')->get() as $step) {
                $copia = $step->replicate(['id', 'flow_version_id', 'created_at', 'updated_at']);
                $copia->flow_version_id = $nuevo->id;
                $copia->save();
            }

            return $nuevo;
        });

        $this->audit->record('flow.duplicated', $draft, ['desde_version' => $version->version]);

        return redirect()->route('admin.flows.show', $draft)
            ->with('status', "Borrador v{$draft->version} creado a partir de la v{$version->version}.");
    }

    public function publish(DiagnosticFlowVersion $version): RedirectResponse
    {
        if ($version->published_at !== null) {
            return back()->with('error', 'Esa versión ya está publicada.');
        }

        $problemas = $this->validateTree($version);

        if ($problemas !== []) {
            // Publicar un árbol roto deja al docente atascado en mitad del
            // diagnóstico, y lo descubre él, en el aula, con la clase
            // esperando. Se comprueba antes.
            return back()->with('error', 'No se puede publicar: '.implode(' · ', $problemas));
        }

        DB::transaction(function () use ($version): void {
            // Solo una versión publicada por procedimiento: el motor toma la
            // vigente, y dos a la vez lo dejarían eligiendo al azar.
            DiagnosticFlowVersion::where('flow_id', $version->flow_id)
                ->whereNotNull('published_at')
                ->update(['published_at' => null]);

            $version->update([
                'published_at' => now(),
                'published_by' => request()->user()?->id,
            ]);
        });

        $this->audit->record('flow.published', $version, ['version' => $version->version]);

        return redirect()->route('admin.flows.index')
            ->with('status', "Versión {$version->version} publicada. Los docentes ya la ven.");
    }

    /**
     * Comprobaciones antes de publicar.
     *
     * Todas responden a la misma pregunta: ¿puede este árbol dejar tirado a
     * un docente? Un salto a un paso inexistente, un árbol sin punto de
     * partida o uno del que no se sale nunca son exactamente eso.
     *
     * @return list<string>
     */
    private function validateTree(DiagnosticFlowVersion $version): array
    {
        $steps = $version->steps()->orderBy('sort_order')->get();

        if ($steps->isEmpty()) {
            return ['el procedimiento no tiene ningún paso.'];
        }

        $claves = $steps->pluck('step_key')->all();
        $problemas = [];

        foreach ($steps as $step) {
            /** @var array<string, string> $mapa */
            $mapa = $step->next_step_map ?? [];

            foreach ($mapa as $destino) {
                if (! in_array($destino, $claves, true)) {
                    $problemas[] = "el paso «{$step->step_key}» salta a «{$destino}», que no existe";
                }
            }
        }

        // Sin salida terminal, el docente recorre pasos y nunca llega a la
        // pantalla de "¿se solucionó?".
        if ($steps->where('is_terminal', true)->isEmpty()) {
            $problemas[] = 'ningún paso cierra el procedimiento (marca al menos uno como final)';
        }

        return array_slice(array_unique($problemas), 0, 5);
    }

    private function nextDraft(DiagnosticFlow $flow): DiagnosticFlowVersion
    {
        $siguiente = ((int) $flow->versions()->max('version')) + 1;

        return DiagnosticFlowVersion::create([
            'flow_id' => $flow->id,
            'version' => $siguiente,
        ]);
    }
}
