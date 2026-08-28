@extends('layouts.app')
@section('title', 'Ticket #' . ($incident->ticket_number ?? $incident->id))
@section('heading', 'Ticket #' . ($incident->ticket_number ?? '—'))
@section('subheading', $incident->room?->fullPath())

@section('content')
<div class="grid gap-6 lg:grid-cols-3">

    {{-- ---------------------------------------------------------------
         TODO LO QUE EL TÉCNICO NECESITA ANTES DE MOVERSE.
         El objetivo del plan (§45) es que no tenga que volver a preguntar
         al docente nada de lo que el sistema ya sabe.
    ---------------------------------------------------------------- --}}
    <div class="space-y-4 lg:col-span-2">

        {{-- Va por encima de "no puede dictar clase": el técnico tiene que
             saber ANTES de salir que va a un equipo posiblemente en riesgo,
             porque cambia qué lleva y con quién coordina. --}}
        @if ($incident->hazard_reported)
            <div class="glass border-danger/70 bg-danger-container/70 px-4 py-3">
                <p class="font-bold text-on-danger-container">Riesgo físico reportado</p>
                <p class="mt-1 text-sm text-on-danger-container">
                    El docente describió una situación de riesgo
                    @if ($incident->hazard_term)
                        («{{ $incident->hazard_term }}»)
                    @endif
                    y se le indicó que no manipule el equipo. El diagnóstico guiado se omitió
                    a propósito y la prioridad la fijó el sistema al máximo.
                </p>
            </div>
        @endif

        @if ($incident->blocks_class)
            <div class="glass border-danger/70 bg-danger-container/70 px-4 py-3">
                <p class="font-semibold text-on-danger-container">El docente no puede dictar la clase</p>
            </div>
        @endif

        <div class="glass p-5">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="label-tech text-on-surface-variant">Aula</dt>
                    <dd class="mt-0.5 font-mono text-lg font-semibold">{{ $incident->room?->code }}</dd>
                    <dd class="text-sm text-on-surface-variant">
                        {{ $incident->room?->floor?->building?->name }} · {{ $incident->room?->floor?->label }}
                    </dd>
                </div>
                <div>
                    <dt class="label-tech text-on-surface-variant">Problema</dt>
                    <dd class="mt-0.5 text-lg">{{ $incident->category?->name ?? 'Sin clasificar' }}</dd>
                </div>
                <div>
                    <dt class="label-tech text-on-surface-variant">Reportado</dt>
                    <dd class="mt-0.5">{{ $incident->reported_at?->timezone(config('incidencias.display_timezone'))->format('d/m/Y H:i') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="label-tech text-on-surface-variant">Estado / prioridad</dt>
                    <dd class="mt-0.5">{{ $incident->status?->name }} · {{ $incident->priority?->name ?? '—' }}</dd>
                </div>

                @if ($incident->reporter_hint)
                    <div>
                        <dt class="label-tech text-on-surface-variant">Contacto que dejó el docente</dt>
                        <dd class="mt-0.5">{{ $incident->reporter_hint }}</dd>
                    </div>
                @endif

                @if ($additionalReports > 0)
                    <div>
                        <dt class="label-tech text-on-surface-variant">Reportes adicionales</dt>
                        <dd class="mt-0.5 font-semibold text-warn">
                            {{ $additionalReports }} persona(s) más reportaron lo mismo
                        </dd>
                    </div>
                @endif
            </dl>

            @if ($incident->reported_description)
                <div class="mt-4 rounded-md bg-surface-low px-4 py-3">
                    <p class="label-tech text-on-surface-variant">En palabras del docente</p>
                    <p class="mt-1 text-on-surface">{{ $incident->reported_description }}</p>
                </div>
            @endif
        </div>

        {{-- Historial completo: es lo que permite responder "por qué este
             ticket terminó aquí" sin preguntarle a nadie (plan §57). --}}
        <div class="glass p-5">
            <h2 class="mb-3 label-tech text-on-surface-variant">Historial</h2>

            <ol class="space-y-3">
                @foreach ($incident->events as $event)
                    <li class="flex gap-3 text-sm">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-outline-variant"></span>
                        <div class="min-w-0">
                            <p class="font-medium text-on-surface">
                                {{ __('incident-events.' . $event->event_type) }}
                            </p>

                            @if ($event->event_type === 'status_changed')
                                <p class="text-on-surface-variant">
                                    {{ $event->from_value['status'] ?? '?' }} → {{ $event->to_value['status'] ?? '?' }}
                                </p>
                            @endif

                            @if (! empty($event->metadata['priority_factors']))
                                {{-- Por qué este ticket tiene esta prioridad. Sin
                                     explicación, una prioridad se percibe como
                                     arbitraria y acaba ignorándose. --}}
                                <ul class="mt-1 list-disc pl-4 text-xs text-on-surface-variant">
                                    @foreach ($event->metadata['priority_factors'] as $factor)
                                        <li>{{ $factor }}</li>
                                    @endforeach
                                </ul>
                            @endif

                            @if (! empty($event->metadata['reason']))
                                <p class="text-on-surface-variant">{{ $event->metadata['reason'] }}</p>
                            @endif

                            <p class="mt-0.5 text-xs text-outline">
                                {{ $event->occurred_at->timezone(config('incidencias.display_timezone'))->format('d/m/Y H:i') }}
                                @if ($event->actor) · {{ $event->actor->name }} @endif
                            </p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </div>

    {{-- ------------------------------- ACCIONES ------------------------ --}}
    <div class="space-y-4">

        @can('incidents.assign')
            <div class="glass p-5">
                <h2 class="mb-3 label-tech text-on-surface-variant">Asignación</h2>

                <p class="mb-3 text-sm text-on-surface-variant">
                    {{ $incident->assignee?->name ?? 'Sin asignar' }}
                </p>

                @if ($incident->assigned_to !== auth()->id())
                    <form method="POST" action="{{ route('support.incidents.take', $incident) }}" class="mb-3">
                        @csrf
                        <button class="w-full btn-primary focus-ring px-4 py-2 text-sm font-medium hover:bg-on-surface">
                            Tomar este ticket
                        </button>
                    </form>
                @endif

                <form method="POST" action="{{ route('support.incidents.assign', $incident) }}" class="flex gap-2">
                    @csrf
                    <select name="technician_id" required class="min-w-0 flex-1 rounded-md border-outline-variant px-3 py-2 text-sm">
                        <option value="">Asignar a…</option>
                        @foreach ($technicians as $t)
                            <option value="{{ $t->id }}" @selected($incident->assigned_to === $t->id)>{{ $t->name }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-md border border-outline-variant px-3 py-2 text-sm hover:bg-surface-low">Asignar</button>
                </form>
            </div>
        @endcan

        @can('incidents.update')
            @unless ($incident->statusCode()->isResolved() || $incident->statusCode()->isTerminal())
                <div class="glass p-5">
                    <h2 class="mb-3 label-tech text-on-surface-variant">Resolver</h2>

                    <form method="POST" action="{{ route('support.incidents.resolve', $incident) }}" class="space-y-3">
                        @csrf

                        <select name="resolution_type" required class="block w-full rounded-md border-outline-variant px-3 py-2 text-sm">
                            @foreach ($resolutionTypes as $type)
                                @continue($type === \App\Shared\Enums\ResolutionType::None)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </select>

                        <textarea name="technical_diagnosis" rows="2" placeholder="Diagnóstico técnico (opcional)"
                                  class="block w-full rounded-md border-outline-variant px-3 py-2 text-sm"></textarea>

                        <textarea name="resolution_notes" rows="3" required
                                  placeholder="¿Qué se hizo para resolverlo? (obligatorio)"
                                  class="block w-full rounded-md border-outline-variant px-3 py-2 text-sm"></textarea>

                        <p class="text-xs text-on-surface-variant">
                            La solución registrada alimenta la base de conocimiento y el análisis de
                            recurrencia. Un ticket cerrado sin explicación no enseña nada.
                        </p>

                        <button class="w-full rounded-md bg-ok px-4 py-2 text-sm font-medium text-surface-lowest hover:bg-ok">
                            Marcar como resuelta
                        </button>
                    </form>
                </div>
            @endunless
        @endcan

        @can('incidents.close')
            <div class="glass p-5 text-sm">
                <h2 class="mb-3 label-tech text-on-surface-variant">Cierre</h2>

                @if ($incident->statusCode() === \App\Shared\Enums\IncidentStatus::Resolved)
                    <form method="POST" action="{{ route('support.incidents.close', $incident) }}" class="mb-3">
                        @csrf
                        <button class="w-full rounded-md border border-outline-variant px-4 py-2 hover:bg-surface-low">Cerrar ticket</button>
                    </form>
                @endif

                @if ($incident->statusCode()->isResolved())
                    <form method="POST" action="{{ route('support.incidents.reopen', $incident) }}" class="space-y-2">
                        @csrf
                        <input name="reason" required placeholder="Motivo de la reapertura"
                               class="block w-full rounded-md border-outline-variant px-3 py-2 text-sm">
                        <button class="w-full rounded-md border border-warn bg-warn-container px-4 py-2 text-on-warn-container hover:bg-warn-container">
                            Reabrir
                        </button>
                    </form>
                @endif
            </div>
        @endcan

        @can('incidents.cancel')
            @unless ($incident->statusCode()->isTerminal())
                <div class="glass p-5">
                    <form method="POST" action="{{ route('support.incidents.cancel', $incident) }}" class="space-y-2"
                          data-confirm="¿Cancelar esta incidencia?">
                        @csrf
                        <input name="reason" required placeholder="Motivo de la cancelación"
                               class="block w-full rounded-md border-outline-variant px-3 py-2 text-sm">
                        <button class="w-full rounded-md border border-danger px-4 py-2 text-sm text-danger hover:bg-danger-container">
                            Cancelar incidencia
                        </button>
                    </form>
                </div>
            @endunless
        @endcan

        <a href="{{ route('support.incidents.index') }}"
           class="block text-center text-sm text-on-surface-variant underline underline-offset-2">
            Volver a la bandeja
        </a>
    </div>
</div>
@endsection
