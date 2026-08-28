<?php

declare(strict_types=1);

namespace App\Modules\Diagnostics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Diagnostics\Models\DiagnosticFlowVersion;
use App\Modules\Diagnostics\Models\DiagnosticStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Pasos de un árbol de diagnóstico.
 *
 * SOLO SE EDITAN BORRADORES. Una versión publicada es inmutable porque hay
 * incidencias cerradas que la ejecutaron: cambiarla haría que sus respuestas
 * guardadas dejaran de significar lo que significaban. La comprobación está
 * en cada acción y no solo en la vista — ocultar el botón no impide una
 * petición directa.
 *
 * EL FORMULARIO PIDE LAS RESPUESTAS EN TEXTO LLANO, una por línea, en lugar
 * de en JSON. Quien escribe estos procedimientos es soporte técnico, no un
 * programador, y pedirle que teclee JSON bien formado garantiza dos cosas:
 * que se equivoque y que acabe pidiendo que se lo hagan.
 */
class StepController extends Controller
{
    public function create(DiagnosticFlowVersion $version): View|RedirectResponse
    {
        if ($version->published_at !== null) {
            return redirect()->route('admin.flows.show', $version)
                ->with('error', 'Esta versión ya está publicada y no se puede modificar. Duplícala para cambiarla.');
        }

        return view('admin.flows.step', [
            'version' => $version->load('flow.category'),
            'step' => new DiagnosticStep,
            'otros' => $version->steps()->orderBy('sort_order')->get(),
        ]);
    }

    public function edit(DiagnosticStep $step): View|RedirectResponse
    {
        $version = $step->version;

        if ($version->published_at !== null) {
            return redirect()->route('admin.flows.show', $version)
                ->with('error', 'Esta versión ya está publicada y no se puede modificar. Duplícala para cambiarla.');
        }

        return view('admin.flows.step', [
            'version' => $version->load('flow.category'),
            'step' => $step,
            'otros' => $version->steps()->where('id', '!=', $step->id)->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request, DiagnosticFlowVersion $version): RedirectResponse
    {
        $this->ensureDraft($version);

        $data = $this->validated($request, $version, null);

        DiagnosticStep::create($data + ['flow_version_id' => $version->id]);

        return redirect()->route('admin.flows.show', $version)->with('status', 'Paso añadido.');
    }

    public function update(Request $request, DiagnosticStep $step): RedirectResponse
    {
        $version = $step->version;
        $this->ensureDraft($version);

        $step->update($this->validated($request, $version, $step));

        return redirect()->route('admin.flows.show', $version)->with('status', 'Paso actualizado.');
    }

    public function destroy(DiagnosticStep $step): RedirectResponse
    {
        $version = $step->version;
        $this->ensureDraft($version);

        $clave = $step->step_key;
        $step->delete();

        // Los saltos que apuntaban aquí quedan rotos. No se corrigen solos —
        // adivinar a dónde debería ir ahora el docente sería inventar el
        // procedimiento—, pero se avisa, y la validación al publicar lo
        // impide de todos modos.
        $huerfanos = $version->steps()
            ->get()
            ->filter(fn (DiagnosticStep $s): bool => in_array($clave, array_values($s->next_step_map ?? []), true))
            ->pluck('step_key');

        return redirect()->route('admin.flows.show', $version)->with(
            $huerfanos->isEmpty() ? 'status' : 'error',
            $huerfanos->isEmpty()
                ? 'Paso eliminado.'
                : "Paso eliminado, pero estos siguen apuntando a él y hay que corregirlos: {$huerfanos->implode(', ')}",
        );
    }

    private function ensureDraft(DiagnosticFlowVersion $version): void
    {
        if ($version->published_at !== null) {
            throw ValidationException::withMessages([
                'version' => 'Esta versión está publicada y no se puede modificar.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, DiagnosticFlowVersion $version, ?DiagnosticStep $step): array
    {
        $data = $request->validate([
            'step_key' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/'],
            'prompt_text' => ['required', 'string', 'max:500'],
            'help_text' => ['nullable', 'string', 'max:500'],
            'component_key' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_terminal' => ['nullable', 'boolean'],
            'terminal_outcome' => ['nullable', 'in:resolved,escalate'],

            // Una respuesta por línea, con el formato «valor|texto|destino».
            'respuestas' => ['nullable', 'string', 'max:2000'],
        ], [
            'step_key.regex' => 'La clave solo admite minúsculas, números y guion bajo. Por ejemplo: revisar_cable',
            'component_key.regex' => 'La clave del componente solo admite minúsculas, números y guion bajo.',
            'prompt_text.required' => 'Escribe la pregunta que verá el docente.',
        ]);

        // Clave única dentro de la versión: dos pasos con la misma clave
        // harían que los saltos apuntaran a cualquiera de los dos.
        $repetida = $version->steps()
            ->where('step_key', $data['step_key'])
            ->when($step !== null, fn ($q) => $q->where('id', '!=', $step->id))
            ->exists();

        if ($repetida) {
            throw ValidationException::withMessages([
                'step_key' => "Ya hay un paso con la clave «{$data['step_key']}» en esta versión.",
            ]);
        }

        [$opciones, $mapa] = $this->parseAnswers($data['respuestas'] ?? '');

        $terminal = (bool) ($data['is_terminal'] ?? false);

        if (! $terminal && $opciones === []) {
            throw ValidationException::withMessages([
                'respuestas' => 'Un paso que no cierra el procedimiento necesita al menos una respuesta.',
            ]);
        }

        return [
            'step_key' => $data['step_key'],
            'prompt_text' => $data['prompt_text'],
            'help_text' => $data['help_text'] ?? null,
            'component_key' => $data['component_key'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_terminal' => $terminal,
            'terminal_outcome' => $terminal ? ($data['terminal_outcome'] ?? 'escalate') : null,
            'answer_options' => $opciones,
            'next_step_map' => $mapa,
        ];
    }

    /**
     * Convierte «si|Sí, está conectado|revisar_imagen» en las dos
     * estructuras que el motor necesita.
     *
     * Se acepta también sin destino: una respuesta sin flecha lleva a la
     * pantalla de «¿se solucionó?», que es lo que se quiere después de pedir
     * una acción correctiva.
     *
     * @return array{0: list<array{value: string, label: string}>, 1: array<string, string>}
     */
    private function parseAnswers(string $texto): array
    {
        $opciones = [];
        $mapa = [];

        foreach (preg_split('/\r?\n/', trim($texto)) ?: [] as $linea) {
            $linea = trim($linea);

            if ($linea === '') {
                continue;
            }

            $partes = array_map('trim', explode('|', $linea));
            $valor = $partes[0];

            if ($valor === '') {
                continue;
            }

            $opciones[] = ['value' => $valor, 'label' => $partes[1] ?? $valor];

            if (($partes[2] ?? '') !== '') {
                $mapa[$valor] = $partes[2];
            }
        }

        return [$opciones, $mapa];
    }
}
