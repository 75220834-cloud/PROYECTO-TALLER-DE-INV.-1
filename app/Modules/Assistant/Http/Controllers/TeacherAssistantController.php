<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Assistant\Services\AssistantService;
use App\Modules\Assistant\Services\IntentClassifier;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Incidents\Models\IncidentCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * La ayuda del asistente para el docente (plan 13, CU-D-06 y CU-D-13).
 *
 * Son dos cosas distintas y conviene no confundirlas:
 *
 *   CLASIFICAR   el docente escribe "no se ve nada" y el sistema propone
 *                una categoría. Nunca la impone: siempre pregunta.
 *   RESPONDER    el docente hace una pregunta abierta y el sistema contesta
 *                con lo que hay en la base de conocimiento, citando la
 *                fuente, o dice que no sabe y avisa a soporte.
 *
 * NINGUNA DE LAS DOS ESTÁ EN EL CAMINO CRÍTICO. Si el modelo no responde,
 * el docente sigue viendo el catálogo completo en botones y el diagnóstico
 * guiado funciona igual (plan 10.5). La IA aquí quita trabajo; no habilita
 * nada que sin ella fuese imposible.
 */
class TeacherAssistantController extends Controller
{
    private const SESSION_KEY = 'teacher.incident_uuid';

    public function __construct(
        private readonly IntentClassifier $classifier,
        private readonly AssistantService $assistant,
    ) {}

    /**
     * Clasifica el texto libre y pide confirmación (plan 7.1 paso 5).
     *
     * El sistema NUNCA da por buena su propia clasificación: enseña lo que
     * entendió y espera un sí. Un docente al que el sistema le adivina mal
     * la categoría y sigue adelante sin preguntar acaba en un árbol de
     * diagnóstico que no tiene nada que ver con su problema, y abandona.
     */
    public function classify(Request $request): View|RedirectResponse
    {
        $incident = $this->currentIncident($request);

        if ($incident === null) {
            return redirect()->route('teacher.start');
        }

        $data = $request->validate([
            'description' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
            'description.required' => 'Escribe qué está pasando.',
            'description.min' => 'Cuéntanos un poco más.',
        ]);

        $classification = $this->classifier->classify($data['description']);
        $decision = $this->classifier->decide($classification);

        // El catálogo se resuelve por el CÓDIGO devuelto, que el propio
        // clasificador ya validó contra la lista real. Una etiqueta que no
        // exista no llega hasta aquí.
        $suggested = $classification->label !== null
            ? IncidentCategory::active()->where('code', $classification->label)->first()
            : null;

        $alternatives = IncidentCategory::active()
            ->whereIn('code', $classification->alternatives)
            ->get();

        return view('teacher.classify', [
            'incident' => $incident,
            'description' => $data['description'],
            'suggested' => $suggested,
            'alternatives' => $alternatives,
            'decision' => $decision,
            'confidence' => $classification->confidence,

            // Si no hay nada claro se muestra el catálogo entero: es la
            // salida que garantiza que el docente nunca queda atascado
            // porque el modelo no supo qué decir.
            'categories' => $decision === 'manual'
                ? IncidentCategory::active()->orderBy('sort_order')->get()
                : collect(),
        ]);
    }

    /**
     * Pregunta abierta con respuesta basada en la base de conocimiento.
     *
     * Aquí es donde el sistema podría inventarse un procedimiento
     * institucional, así que aquí es donde más importa que no lo haga: si no
     * hay fuentes, no responde y escala (plan 44). La respuesta siempre
     * viene con su cita para que el docente pueda comprobarla.
     */
    public function ask(Request $request): View|RedirectResponse
    {
        $incident = $this->currentIncident($request);

        $data = $request->validate([
            'question' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'question.required' => 'Escribe tu pregunta.',
            'question.min' => 'Escribe la pregunta un poco más completa.',
        ]);

        $answer = $this->assistant->answer(
            $data['question'],
            $incident?->category_id,
            $incident,
        );

        return view('teacher.ask', [
            'incident' => $incident,
            'question' => $data['question'],
            'answer' => $answer,
        ]);
    }

    public function askForm(Request $request): View
    {
        return view('teacher.ask', [
            'incident' => $this->currentIncident($request),
            'question' => null,
            'answer' => null,
        ]);
    }

    private function currentIncident(Request $request): ?Incident
    {
        $uuid = $request->session()->get(self::SESSION_KEY);

        return $uuid === null ? null : Incident::where('uuid', $uuid)->first();
    }
}
