<?php

declare(strict_types=1);

namespace App\Modules\Diagnostics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Diagnostics\Engine\DiagnosticEngine;
use App\Modules\Incidents\Http\Controllers\TeacherIncidentController;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Media\Services\MediaResolver;
use App\Shared\Enums\IncidentStatus as S;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Diagnostico guiado paso a paso (plan 11 y 13.6).
 *
 * Una pregunta por pantalla, respuestas cerradas en botones grandes y una
 * imagen del componente cuando el paso menciona algo fisico.
 *
 * Si la categoria no tiene arbol publicado, esto no falla: se salta directo
 * a la confirmacion de solucion. Mientras soporte no haya cargado los
 * procedimientos reales, el sistema sigue sirviendo para reportar y
 * escalar, que es lo esencial.
 */
class TeacherDiagnosticController extends Controller
{
    public function __construct(private readonly DiagnosticEngine $engine) {}

    /** Muestra el paso actual. */
    public function show(Request $request): View|RedirectResponse
    {
        $incident = $this->currentIncident($request);

        if ($incident === null || $incident->category === null) {
            return redirect()->route('teacher.start');
        }

        $version = $this->engine->flowFor($incident->category_id);

        // Sin procedimiento cargado: se va directo a preguntar si se
        // soluciono. No se inventa un diagnostico.
        if ($version === null) {
            return redirect()->route('teacher.outcome');
        }

        $step = $this->engine->currentStep($incident, $version);

        // Arbol agotado sin desenlace explicito: escalar es lo conservador.
        if ($step === null) {
            return redirect()->route('teacher.outcome');
        }

        $step->load('media');
        $visuals = $this->engine->visualsFor($step);

        return view('teacher.diagnostic', [
            'incident' => $incident,
            'step' => $step,
            'primary' => $visuals['primary'],
            'alternates' => $visuals['alternates'],
            'reference' => $visuals['reference'],
            'stepNumber' => $this->engine->progressOf($incident)->count() + 1,
            'shownAt' => now()->timestamp,
        ]);
    }

    /** Registra la respuesta y avanza. */
    public function answer(Request $request): RedirectResponse
    {
        $incident = $this->currentIncident($request);

        if ($incident === null || $incident->category === null) {
            return redirect()->route('teacher.start');
        }

        $data = $request->validate([
            'step_id' => ['required', 'integer'],
            'answer' => ['required', 'string', 'max:60'],
            'shown_at' => ['nullable', 'integer'],
            'media_shown' => ['nullable', 'string', 'max:255'],
        ]);

        $version = $this->engine->flowFor($incident->category_id);

        if ($version === null) {
            return redirect()->route('teacher.outcome');
        }

        $step = $version->steps()->find((int) $data['step_id']);

        if ($step === null) {
            return redirect()->route('teacher.diagnostic');
        }

        // Cuanto tardo el docente en este paso. Un paso sistematicamente
        // lento senala que la instruccion no se entiende (plan 11).
        $durationMs = isset($data['shown_at'])
            ? max(0, (now()->timestamp - (int) $data['shown_at']) * 1000)
            : null;

        $mediaShown = array_values(array_filter(
            array_map('intval', explode(',', (string) ($data['media_shown'] ?? '')))
        ));

        $outcome = $this->engine->answer(
            $incident,
            $step,
            $data['answer'],
            $durationMs,
            $mediaShown,
        );

        if ($outcome->isInvalid()) {
            return redirect()->route('teacher.diagnostic')
                ->with('error', 'Elige una de las opciones, por favor.');
        }

        if ($outcome->isResolved()) {
            // El arbol declara que aqui el problema queda resuelto. Aun asi
            // se PREGUNTA al docente antes de cerrar: el procedimiento puede
            // dar por bueno algo que en el aula no funciono (plan 12).
            return redirect()->route('teacher.outcome');
        }

        if ($outcome->shouldEscalate()) {
            return redirect()->route('teacher.outcome');
        }

        return redirect()->route('teacher.diagnostic');
    }

    /** "¿Cuál es este cable?" — muestra el componente aislado. */
    public function reference(Request $request, string $componentKey): View|RedirectResponse
    {
        $incident = $this->currentIncident($request);

        if ($incident === null) {
            return redirect()->route('teacher.start');
        }

        $asset = app(MediaResolver::class)
            ->componentReference($componentKey);

        if ($asset === null) {
            return redirect()->route('teacher.diagnostic');
        }

        return view('teacher.reference', compact('asset'));
    }

    private function currentIncident(Request $request): ?Incident
    {
        $uuid = $request->session()->get(TeacherIncidentController::sessionKey());

        if (! is_string($uuid)) {
            return null;
        }

        $incident = Incident::with(['room.floor.building', 'category', 'status'])
            ->where('uuid', $uuid)
            ->first();

        return $incident !== null && $incident->hasStatus(S::Diagnosing) ? $incident : null;
    }
}
