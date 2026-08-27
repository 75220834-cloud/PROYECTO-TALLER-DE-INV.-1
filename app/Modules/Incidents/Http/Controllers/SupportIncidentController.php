<?php

declare(strict_types=1);

namespace App\Modules\Incidents\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Incidents\Exceptions\InvalidTransitionException;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Incidents\Models\IncidentCategory;
use App\Modules\Incidents\Models\IncidentStatus as StatusModel;
use App\Modules\Incidents\Services\IncidentService;
use App\Shared\Enums\IncidentStatus as S;
use App\Shared\Enums\ResolutionType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Bandeja de soporte y atencion de tickets (plan 15 y 45).
 *
 * Objetivo de diseno: el tecnico NO debe volver a preguntar al docente
 * nada de lo que el sistema ya sabe. Por eso el detalle muestra la
 * ubicacion exacta, la categoria, el sintoma reportado, los pasos ya
 * ejecutados y la explicacion de por que ese ticket tiene esa prioridad.
 */
class SupportIncidentController extends Controller
{
    public function __construct(private readonly IncidentService $incidents) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $incidents = Incident::query()
            ->visibleToSupport()
            ->with(['room.floor.building', 'category', 'status', 'priority', 'assignee'])
            ->when($request->query('status'), fn ($q, $code) => $q->whereHas('status', fn ($s) => $s->where('code', $code)))
            ->when($request->query('scope') === 'mine', fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->when($request->query('scope') === 'unassigned', fn ($q) => $q->whereNull('assigned_to'))
            ->when($request->integer('category_id'), fn ($q, $id) => $q->where('category_id', $id))
            ->when($search !== '', function ($q) use ($search) {
                $escaped = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($sub) => $sub
                    ->where('ticket_number', 'like', $escaped)
                    ->orWhereHas('room', fn ($r) => $r->where('code', 'like', $escaped)));
            })
            // Sin filtro explicito, la bandeja muestra lo ABIERTO: es lo que
            // el tecnico necesita al entrar. Ver el historico es una accion
            // deliberada, no el estado por defecto.
            ->when(! $request->query('status'), fn ($q) => $q->open())
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('support.incidents.index', [
            'incidents' => $incidents,
            'statuses' => StatusModel::where('is_active', true)->orderBy('sort_order')->get(),
            'categories' => IncidentCategory::active()->orderBy('sort_order')->get(),
            'search' => $search,
            'counts' => $this->counts($request->user()),
        ]);
    }

    public function show(Incident $incident): View
    {
        $incident->load([
            'room.floor.building.site', 'equipment.type', 'category', 'status',
            'priority', 'assignee', 'events.actor', 'messages', 'mergedInto',
        ]);

        return view('support.incidents.show', [
            'incident' => $incident,
            'technicians' => $this->technicians(),
            'resolutionTypes' => ResolutionType::cases(),
            'additionalReports' => Incident::where('merged_into_id', $incident->id)->count(),
        ]);
    }

    public function assign(Request $request, Incident $incident): RedirectResponse
    {
        $data = $request->validate([
            'technician_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ]);

        $technician = User::findOrFail($data['technician_id']);

        return $this->guarded(
            fn () => $this->incidents->assign($incident, $technician, $request->user()),
            'Ticket asignado a '.$technician->name.'.'
        );
    }

    /** Autoasignacion: el caso mas frecuente en la practica. */
    public function take(Request $request, Incident $incident): RedirectResponse
    {
        return $this->guarded(
            fn () => $this->incidents->assign($incident, $request->user(), $request->user()),
            'Has tomado este ticket.'
        );
    }

    public function transition(Request $request, Incident $incident): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(S::class)],
        ]);

        return $this->guarded(
            fn () => $this->incidents->transitionTo(
                $incident,
                S::from($data['status']),
                'user',
                $request->user(),
            ),
            'Estado actualizado.'
        );
    }

    public function resolve(Request $request, Incident $incident): RedirectResponse
    {
        $data = $request->validate([
            'resolution_type' => ['required', Rule::enum(ResolutionType::class)],
            'technical_diagnosis' => ['nullable', 'string', 'max:2000'],
            'resolution_notes' => ['required', 'string', 'max:2000'],
        ], [
            // Obligatoria a proposito: la solucion registrada es la materia
            // prima de la base de conocimiento (Fase 5) y del analisis de
            // recurrencia. Un ticket cerrado sin explicacion no ensena nada.
            'resolution_notes.required' => 'Describe qué se hizo para resolverlo.',
        ]);

        return $this->guarded(
            fn () => $this->incidents->resolve(
                $incident,
                ResolutionType::from($data['resolution_type']),
                $data['resolution_notes'],
                $data['technical_diagnosis'] ?? null,
                $request->user(),
            ),
            'Incidencia marcada como resuelta.'
        );
    }

    public function close(Request $request, Incident $incident): RedirectResponse
    {
        return $this->guarded(
            fn () => $this->incidents->close($incident, $request->user()),
            'Incidencia cerrada.'
        );
    }

    public function reopen(Request $request, Incident $incident): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ], ['reason.required' => 'Indica por qué se reabre.']);

        return $this->guarded(
            fn () => $this->incidents->reopen($incident, $request->user(), $data['reason']),
            'Incidencia reabierta.'
        );
    }

    public function cancel(Request $request, Incident $incident): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ], ['reason.required' => 'Indica el motivo de la cancelación.']);

        return $this->guarded(
            fn () => $this->incidents->cancel($incident, $request->user(), $data['reason']),
            'Incidencia cancelada.'
        );
    }

    // ----------------------------------------------------------- interno

    /**
     * Ejecuta la accion y convierte una transicion invalida en un mensaje
     * util en lugar de en un error 500.
     *
     * Puede ocurrir sin que nadie haga nada raro: dos tecnicos con la misma
     * pantalla abierta, uno cierra el ticket y el otro pulsa "resolver".
     * El segundo merece una explicacion, no una pantalla de error.
     */
    private function guarded(callable $action, string $successMessage): RedirectResponse
    {
        try {
            $action();
        } catch (InvalidTransitionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', $successMessage);
    }

    /** @return array<string, int> */
    private function counts(User $user): array
    {
        return [
            'open' => Incident::visibleToSupport()->open()->count(),
            'unassigned' => Incident::visibleToSupport()->open()->whereNull('assigned_to')->count(),
            'mine' => Incident::visibleToSupport()->open()->where('assigned_to', $user->id)->count(),
            'blocking' => Incident::visibleToSupport()->open()->where('blocks_class', true)->count(),
        ];
    }

    /** @return Collection<int, User> */
    private function technicians()
    {
        return User::query()
            ->whereHas('roles.permissions', fn ($q) => $q->where('name', 'incidents.update'))
            ->orderBy('name')
            ->get();
    }
}
