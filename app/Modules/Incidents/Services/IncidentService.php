<?php

declare(strict_types=1);

namespace App\Modules\Incidents\Services;

use App\Models\User;
use App\Modules\Incidents\Events\IncidentEscalated;
use App\Modules\Incidents\Exceptions\InvalidTransitionException;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Incidents\Models\IncidentCategory;
use App\Modules\Incidents\Models\IncidentEvent;
use App\Modules\Incidents\Models\IncidentPriority as PriorityModel;
use App\Modules\Incidents\Models\IncidentStatus as StatusModel;
use App\Modules\Incidents\StateMachine\IncidentStateMachine;
use App\Modules\Locations\Models\Room;
use App\Shared\Enums\IncidentStatus as S;
use App\Shared\Enums\ResolutionType;
use Illuminate\Support\Facades\DB;

/**
 * Ciclo de vida de una incidencia.
 *
 * Todo cambio de estado pasa por aqui y deja rastro en incident_events.
 * No hay atajos: si alguien actualiza status_id directamente, la linea de
 * tiempo del ticket queda incompleta y con ella la auditoria y las
 * metricas de la investigacion.
 */
final class IncidentService
{
    public function __construct(
        private readonly IncidentStateMachine $machine,
        private readonly PriorityCalculator $priorities,
    ) {}

    /**
     * Crea el BORRADOR al confirmar la ubicacion.
     *
     * No es un ticket: soporte no lo ve y no notifica a nadie. Existe para
     * poder medir abandonos y el tiempo real desde el inicio del reporte
     * (plan 7.1).
     */
    public function createDraft(Room $room, ?string $deviceKey, ?string $ipHash): Incident
    {
        $incident = Incident::create([
            'room_id' => $room->id,
            'status_id' => StatusModel::idFor(S::Draft),
            'is_draft' => true,
            'device_key' => $deviceKey,
            'ip_hash' => $ipHash,
            'confirmed_at' => now(),
        ]);

        $this->record($incident, 'location_confirmed', 'teacher', metadata: [
            'room_code' => $room->code,
        ]);

        return $incident;
    }

    /**
     * El docente declara el problema. El borrador pasa a diagnostico.
     */
    public function startDiagnosis(
        Incident $incident,
        IncidentCategory $category,
        ?string $description,
        string $classifiedBy = 'teacher',
        ?float $confidence = null,
    ): Incident {
        $this->transitionTo($incident, S::Diagnosing, 'teacher');

        $incident->update([
            'category_id' => $category->id,
            'reported_description' => $description,
            'classified_by' => $classifiedBy,
            'classification_confidence' => $confidence,
            'reported_at' => now(),
        ]);

        $this->record($incident, 'problem_reported', 'teacher', metadata: [
            'category' => $category->code,
            'classified_by' => $classifiedBy,
        ]);

        return $incident->refresh();
    }

    /**
     * El docente confirma que el problema quedo resuelto sin intervencion
     * presencial. Es el desenlace que el proyecto busca maximizar.
     */
    public function resolveByAssistant(Incident $incident): Incident
    {
        $this->transitionTo($incident, S::Resolved, 'teacher');

        $incident->update([
            'resolution_type' => ResolutionType::Assistant->value,
            'resolved_at' => now(),
            'is_draft' => false,
        ]);

        $this->record($incident, 'resolved_by_assistant', 'teacher');

        return $incident->refresh();
    }

    /**
     * Escala a soporte: el borrador se convierte en TICKET.
     *
     * Solo aqui se asigna numero de ticket y prioridad, y solo aqui se
     * notifica a nadie. Antes de este punto el sistema no ha movilizado a
     * ninguna persona.
     */
    public function escalate(Incident $incident, bool $blocksClass): Incident
    {
        $priority = $this->priorities->calculate(
            $incident->category,
            $incident->room,
            $blocksClass,
        );

        $this->transitionTo($incident, S::New, 'teacher');

        $incident->update([
            'ticket_number' => $this->nextTicketNumber(),
            'priority_id' => PriorityModel::idFor($priority->priority),
            'blocks_class' => $blocksClass,
            'is_draft' => false,
            'reported_at' => $incident->reported_at ?? now(),
        ]);

        $this->record($incident, 'escalated_to_support', 'teacher', metadata: [
            'blocks_class' => $blocksClass,
            'priority' => $priority->priority->value,

            // Los factores se guardan para que el tecnico pueda ver por que
            // este ticket esta por encima de otro en su bandeja.
            'priority_factors' => $priority->factors,
        ]);

        IncidentEscalated::dispatch($incident->refresh());

        return $incident;
    }

    /**
     * El docente se suma a un ticket ya abierto en lugar de crear otro.
     *
     * El borrador no se descarta: se enlaza. Asi queda constancia de que
     * DOS personas reportaron el mismo problema, que es informacion
     * operativa util (indica cuanta gente esta afectada) y ademas evita
     * que el indicador de "reportes" subestime la incidencia real.
     */
    public function joinExisting(Incident $draft, Incident $target): Incident
    {
        $draft->update([
            'merged_into_id' => $target->id,
            'is_draft' => false,
            'status_id' => StatusModel::idFor(S::Cancelled),
        ]);

        $this->record($draft, 'merged_into_existing', 'teacher', metadata: [
            'target_ticket' => $target->ticket_number,
        ]);

        $this->record($target, 'additional_report_received', 'teacher', metadata: [
            'from_incident_uuid' => $draft->uuid,
        ]);

        return $target;
    }

    // --------------------------------------------------------- soporte

    public function assign(Incident $incident, User $technician, User $actor): Incident
    {
        $wasUnassigned = $incident->assigned_to === null;

        $incident->update([
            'assigned_to' => $technician->id,
            'assigned_at' => now(),

            // La primera respuesta se marca una sola vez: es un indicador
            // de la investigacion y sobrescribirlo lo falsearia.
            'first_response_at' => $incident->first_response_at ?? now(),
        ]);

        if ($incident->hasStatus(S::New)) {
            $this->transitionTo($incident, S::InProgress, 'user', $actor);
        }

        $this->record($incident, $wasUnassigned ? 'assigned' : 'reassigned', 'user', $actor, metadata: [
            'technician_id' => $technician->id,
        ]);

        return $incident->refresh();
    }

    public function resolve(
        Incident $incident,
        ResolutionType $type,
        ?string $notes,
        ?string $diagnosis,
        User $actor,
    ): Incident {
        $this->transitionTo($incident, S::Resolved, 'user', $actor);

        $incident->update([
            'resolution_type' => $type->value,
            'resolution_notes' => $notes,
            'technical_diagnosis' => $diagnosis,
            'resolved_at' => now(),
        ]);

        $this->record($incident, 'resolved', 'user', $actor, metadata: [
            'resolution_type' => $type->value,
        ]);

        return $incident->refresh();
    }

    public function close(Incident $incident, User $actor): Incident
    {
        $this->transitionTo($incident, S::Closed, 'user', $actor);
        $incident->update(['closed_at' => now()]);
        $this->record($incident, 'closed', 'user', $actor);

        return $incident->refresh();
    }

    /**
     * @throws InvalidTransitionException
     */
    public function reopen(Incident $incident, User $actor, string $reason): Incident
    {
        $windowDays = 7;

        if (! $this->machine->canReopen($incident->statusCode(), $incident->closed_at, $windowDays)) {
            throw InvalidTransitionException::reopenWindowExpired($windowDays);
        }

        $this->transitionTo($incident, S::InProgress, 'user', $actor);

        $incident->update([
            'closed_at' => null,
            'resolved_at' => null,
            'resolution_type' => null,
            'reopened_count' => $incident->reopened_count + 1,
        ]);

        $this->record($incident, 'reopened', 'user', $actor, metadata: ['reason' => $reason]);

        return $incident->refresh();
    }

    public function cancel(Incident $incident, User $actor, string $reason): Incident
    {
        $this->transitionTo($incident, S::Cancelled, 'user', $actor);
        $this->record($incident, 'cancelled', 'user', $actor, metadata: ['reason' => $reason]);

        return $incident->refresh();
    }

    // ----------------------------------------------------------- interno

    /**
     * Unico punto por el que cambia el estado. Valida contra la maquina
     * de estados y deja el cambio registrado.
     *
     * @throws InvalidTransitionException
     */
    public function transitionTo(Incident $incident, S $to, string $actorType, ?User $actor = null): void
    {
        $from = $incident->statusCode();

        $this->machine->assertCanTransition($from, $to);

        $incident->update(['status_id' => StatusModel::idFor($to)]);

        $this->record($incident, 'status_changed', $actorType, $actor, [
            'status' => $from->value,
        ], [
            'status' => $to->value,
        ]);

        $incident->load('status');
    }

    /**
     * @param  array<string, mixed>|null  $from
     * @param  array<string, mixed>|null  $to
     * @param  array<string, mixed>  $metadata
     */
    private function record(
        Incident $incident,
        string $type,
        string $actorType,
        ?User $actor = null,
        ?array $from = null,
        ?array $to = null,
        array $metadata = [],
    ): void {
        IncidentEvent::create([
            'incident_id' => $incident->id,
            'event_type' => $type,
            'actor_type' => $actorType,
            'actor_id' => $actor?->id,
            'from_value' => $from,
            'to_value' => $to,
            'metadata' => $metadata === [] ? null : $metadata,
            'occurred_at' => now(),
        ]);
    }

    /**
     * Numero de ticket legible y sin huecos.
     *
     * Se calcula dentro de una transaccion con bloqueo en lugar de usar el
     * id: los borradores consumen ids y la numeracion saldria llena de
     * saltos (del 184 al 203), lo que confunde a soporte al referirse a un
     * ticket por telefono.
     */
    private function nextTicketNumber(): string
    {
        return DB::transaction(function () {
            $last = Incident::query()
                ->whereNotNull('ticket_number')
                ->lockForUpdate()
                ->max('ticket_number');

            return str_pad((string) (((int) $last) + 1), 6, '0', STR_PAD_LEFT);
        });
    }
}
