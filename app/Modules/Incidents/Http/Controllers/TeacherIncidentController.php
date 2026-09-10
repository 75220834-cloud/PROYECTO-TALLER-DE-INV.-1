<?php

declare(strict_types=1);

namespace App\Modules\Incidents\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Diagnostics\Engine\DiagnosticEngine;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Incidents\Models\IncidentCategory;
use App\Modules\Incidents\Models\SatisfactionResponse;
use App\Modules\Incidents\Services\AbuseContext;
use App\Modules\Incidents\Services\AbuseGuard;
use App\Modules\Incidents\Services\IncidentService;
use App\Modules\Incidents\Services\ReporterPhotoStore;
use App\Modules\Incidents\Services\RoomCatalogFilter;
use App\Modules\Incidents\Services\SafetySignalDetector;
use App\Shared\Enums\IncidentStatus as S;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Reporte de la incidencia por parte del docente.
 *
 * Publico y sin autenticacion. El borrador ya existe (se creo al confirmar
 * la ubicacion); aqui el docente dice QUE le pasa y, si hace falta, pide
 * apoyo presencial.
 *
 * El borrador viaja por sesion mediante su uuid, no por la URL. Es lo
 * contrario que en la seleccion de ubicacion, y a proposito: la ubicacion
 * es informacion publica del catalogo y conviene que el boton "atras"
 * funcione, pero el identificador de una incidencia no debe quedar en el
 * historial del navegador de un aula compartida.
 */
class TeacherIncidentController extends Controller
{
    private const SESSION_KEY = 'teacher.incident_uuid';

    public function __construct(
        private readonly IncidentService $incidents,
        private readonly AbuseGuard $guard,
        private readonly SafetySignalDetector $safety,
        private readonly ReporterPhotoStore $photos,
        private readonly RoomCatalogFilter $roomCatalog,
    ) {}

    /**
     * "¿Qué problema tienes?" — categorias en botones grandes.
     */
    public function chooseCategory(Request $request): View|RedirectResponse
    {
        $incident = $this->currentIncident($request);

        if ($incident === null) {
            return redirect()->route('teacher.start');
        }

        return view('teacher.category', [
            'incident' => $incident,

            // Solo los equipos que ESTA aula tiene registrados. Ofrecer un
            // boton de parlante en un aula sin parlante hace perder el
            // tiempo al docente y mete en la investigacion una incidencia
            // contra un equipo inexistente.
            'categories' => $this->roomCatalog->categoriesFor($incident->room),
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $incident = $this->currentIncident($request);

        if ($incident === null) {
            return redirect()->route('teacher.start');
        }

        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:incident_categories,id'],
            'description' => ['nullable', 'string', 'max:1000'],
        ], [
            'category_id.required' => 'Elige el tipo de problema.',
        ]);

        $category = IncidentCategory::findOrFail($data['category_id']);

        $description = $data['description'] ?? null;

        $this->incidents->startDiagnosis($incident, $category, $description);

        /*
         * RIESGO FISICO: se salta el diagnostico entero (plan 17.5).
         *
         * Ante "sale humo del proyector", abrir el arbol de proyector y
         * pedirle al docente que revise el cable seria mandarlo a acercarse a
         * un equipo que puede estar quemandose. Se va directo a solicitar
         * soporte, y la pantalla le dice que no toque nada.
         */
        $hazardTerm = $this->safety->detect($description);

        if ($hazardTerm !== null) {
            $this->incidents->flagHazard($incident, $hazardTerm);

            return redirect()->route('teacher.escalate');
        }

        // Al diagnostico guiado. Si la categoria no tiene procedimiento
        // publicado, ese controlador reenvia solo a la confirmacion: el
        // sistema no inventa pasos cuando no los tiene.
        return redirect()->route('teacher.diagnostic');
    }

    /**
     * Confirmacion de solucion. Las tres opciones son OBLIGATORIAS y estan
     * fijadas por el plan (12): resuelto, sigue el problema, necesito
     * soporte.
     *
     * En la Fase 4 el diagnostico guiado se intercala antes de esta
     * pantalla; el desenlace seguira siendo este.
     */
    public function outcome(Request $request, DiagnosticEngine $engine): View|RedirectResponse
    {
        $incident = $this->currentIncident($request);

        if ($incident === null || $incident->category === null) {
            return redirect()->route('teacher.start');
        }

        $version = $engine->flowFor($incident->category_id);

        // "Todavía tengo el problema" solo se ofrece si de verdad quedan
        // pasos por delante. Un botón que recarga la misma pantalla haría
        // creer al docente que el sistema se colgó.
        $canContinue = $version !== null
            && $engine->currentStep($incident, $version) !== null;

        return view('teacher.outcome', [
            'incident' => $incident,
            'canContinue' => $canContinue,
            'stepsDone' => $engine->progressOf($incident)->count(),
        ]);
    }

    /** "Sí, funciona correctamente." */
    public function markResolved(Request $request): RedirectResponse
    {
        $incident = $this->currentIncident($request);

        if ($incident === null) {
            return redirect()->route('teacher.start');
        }

        $this->incidents->resolveByAssistant($incident);
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('teacher.done', ['uuid' => $incident->uuid]);
    }

    /** "Necesito soporte técnico." */
    public function escalateForm(Request $request): View|RedirectResponse
    {
        $incident = $this->currentIncident($request);

        if ($incident === null) {
            return redirect()->route('teacher.start');
        }

        return view('teacher.escalate', [
            'incident' => $incident,

            // Marca de tiempo de apertura del formulario: alimenta la senal
            // de "no humano" (envio instantaneo). No es un secreto, solo
            // una medida.
            'formOpenedAt' => now()->timestamp,
        ]);
    }

    public function escalateStore(Request $request): RedirectResponse
    {
        $incident = $this->currentIncident($request);

        if ($incident === null) {
            return redirect()->route('teacher.start');
        }

        $data = $request->validate([
            'blocks_class' => ['required', 'boolean'],
            'confirmed' => ['accepted'],
            'form_opened_at' => ['nullable', 'integer'],

            // Campo trampa: oculto por CSS, solo lo rellenan los bots.
            'website' => ['nullable', 'string', 'max:255'],

            /*
             * Foto del problema (decision D-12). OPCIONAL y debe seguir
             * siendolo: un docente con el aula esperando no puede quedar
             * bloqueado porque la camara no abre.
             *
             * Solo mapas de bits. Un SVG puede llevar JavaScript ejecutable,
             * y esta es la unica subida de archivos que acepta el sistema sin
             * autenticacion — la superficie mas expuesta que hay.
             */
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ], [
            'blocks_class.required' => 'Indícanos si el problema impide continuar la clase.',
            'confirmed.accepted' => 'Confirma la solicitud antes de enviarla.',
        ]);

        $elapsed = $data['form_opened_at'] !== null
            ? max(0, now()->timestamp - (int) $data['form_opened_at'])
            : null;

        $verdict = $this->guard->check(new AbuseContext(
            room: $incident->room,
            category: $incident->category,
            deviceKey: $incident->device_key,
            ipHash: $incident->ip_hash,
            description: $incident->reported_description,
            confirmed: true,
            formElapsedSeconds: $elapsed,
            honeypot: $data['website'] ?? null,
        ));

        if (! $verdict->allowed) {
            // Nunca se deja al docente sin salida: si hay un ticket abierto
            // para lo mismo, se le ofrece sumarse a el (plan 16.4).
            if ($verdict->canJoinExisting()) {
                return redirect()
                    ->route('teacher.join', ['uuid' => $verdict->relatedIncident->uuid])
                    ->with('error', $verdict->teacherMessage());
            }

            return back()->with('error', $verdict->teacherMessage());
        }

        // La foto se guarda ANTES de escalar: si el procesado falla, el
        // ticket no se crea y el docente reintenta con el formulario intacto,
        // en lugar de acabar con un ticket a medias sin imagen.
        if ($request->hasFile('foto')) {
            $incident->update([
                'reporter_photo_path' => $this->photos->store($request->file('foto'), $incident->uuid),
            ]);
        }

        $ticket = $this->incidents->escalate($incident, (bool) $data['blocks_class']);
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('teacher.done', ['uuid' => $ticket->uuid]);
    }

    /** Pantalla para sumarse a un ticket ya abierto. */
    public function joinForm(Request $request, string $uuid): View|RedirectResponse
    {
        $target = Incident::where('uuid', $uuid)->firstOrFail();
        $draft = $this->currentIncident($request);

        if ($draft === null) {
            return redirect()->route('teacher.start');
        }

        return view('teacher.join', compact('target', 'draft'));
    }

    public function joinStore(Request $request, string $uuid): RedirectResponse
    {
        $target = Incident::where('uuid', $uuid)->firstOrFail();
        $draft = $this->currentIncident($request);

        if ($draft === null) {
            return redirect()->route('teacher.start');
        }

        $this->incidents->joinExisting($draft, $target);
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('teacher.done', ['uuid' => $target->uuid]);
    }

    /** Pantalla final: que va a pasar ahora. */
    public function done(string $uuid): View
    {
        $incident = Incident::with(['room.floor.building', 'category', 'priority', 'status'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        return view('teacher.done', compact('incident'));
    }

    /**
     * Recupera el borrador en curso desde la sesion.
     *
     * Devuelve null si no hay ninguno, si ya se cerro o si quedo en un
     * estado que no admite continuar: en todos esos casos el docente vuelve
     * al inicio en lugar de encontrarse una pantalla rota.
     */
    /**
     * Encuesta de facilidad de uso (plan 26.bis, decision D-8).
     *
     * OPCIONAL y de una sola pregunta, mostrada DESPUES de que el problema
     * esta resuelto. El plan advierte contra encuestas que perjudiquen la
     * experiencia: una obligatoria a mitad del flujo penaliza al docente de
     * pie frente a su clase y ademas contamina el propio indicador que
     * pretende medir.
     *
     * Se acepta por uuid publico y sin sesion: el docente puede llegar aqui
     * desde el enlace de "listo" aunque la sesion se haya perdido. El uuid no
     * es enumerable y solo permite puntuar, nunca leer nada.
     */
    public function survey(Request $request, string $uuid): RedirectResponse
    {
        $incident = Incident::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'ease_score' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        // Una sola respuesta por incidencia: si ya contesto, no se pisa. Un
        // reenvio accidental no debe cambiar un dato de la investigacion.
        SatisfactionResponse::firstOrCreate(
            ['incident_id' => $incident->id],
            [
                'ease_score' => $request->integer('ease_score'),
                'comment' => $data['comment'] ?? null,
                'answered_at' => now(),
            ],
        );

        return redirect()
            ->route('teacher.done', ['uuid' => $incident->uuid])
            ->with('survey_thanks', true);
    }

    private function currentIncident(Request $request): ?Incident
    {
        $uuid = $request->session()->get(self::SESSION_KEY);

        if (! is_string($uuid)) {
            return null;
        }

        $incident = Incident::with(['room.floor.building', 'category', 'status'])
            ->where('uuid', $uuid)
            ->first();

        if ($incident === null) {
            return null;
        }

        return in_array($incident->statusCode(), [S::Draft, S::Diagnosing], true)
            ? $incident
            : null;
    }

    public static function sessionKey(): string
    {
        return self::SESSION_KEY;
    }
}
