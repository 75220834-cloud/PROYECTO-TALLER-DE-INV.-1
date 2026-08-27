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

        @if ($incident->blocks_class)
            <div class="rounded-lg border-2 border-rose-300 bg-rose-50 px-4 py-3">
                <p class="font-semibold text-rose-900">El docente no puede dictar la clase</p>
            </div>
        @endif

        <div class="rounded-lg border border-slate-200 bg-white p-5">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Aula</dt>
                    <dd class="mt-0.5 font-mono text-lg font-semibold">{{ $incident->room?->code }}</dd>
                    <dd class="text-sm text-slate-600">
                        {{ $incident->room?->floor?->building?->name }} · {{ $incident->room?->floor?->label }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Problema</dt>
                    <dd class="mt-0.5 text-lg">{{ $incident->category?->name ?? 'Sin clasificar' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Reportado</dt>
                    <dd class="mt-0.5">{{ $incident->reported_at?->timezone(config('incidencias.display_timezone'))->format('d/m/Y H:i') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Estado / prioridad</dt>
                    <dd class="mt-0.5">{{ $incident->status?->name }} · {{ $incident->priority?->name ?? '—' }}</dd>
                </div>

                @if ($incident->reporter_hint)
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Contacto que dejó el docente</dt>
                        <dd class="mt-0.5">{{ $incident->reporter_hint }}</dd>
                    </div>
                @endif

                @if ($additionalReports > 0)
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Reportes adicionales</dt>
                        <dd class="mt-0.5 font-semibold text-amber-700">
                            {{ $additionalReports }} persona(s) más reportaron lo mismo
                        </dd>
                    </div>
                @endif
            </dl>

            @if ($incident->reported_description)
                <div class="mt-4 rounded-md bg-slate-50 px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-slate-500">En palabras del docente</p>
                    <p class="mt-1 text-slate-800">{{ $incident->reported_description }}</p>
                </div>
            @endif
        </div>

        {{-- Historial completo: es lo que permite responder "por qué este
             ticket terminó aquí" sin preguntarle a nadie (plan §57). --}}
        <div class="rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Historial</h2>

            <ol class="space-y-3">
                @foreach ($incident->events as $event)
                    <li class="flex gap-3 text-sm">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-slate-300"></span>
                        <div class="min-w-0">
                            <p class="font-medium text-slate-800">
                                {{ __('incident-events.' . $event->event_type) }}
                            </p>

                            @if ($event->event_type === 'status_changed')
                                <p class="text-slate-500">
                                    {{ $event->from_value['status'] ?? '?' }} → {{ $event->to_value['status'] ?? '?' }}
                                </p>
                            @endif

                            @if (! empty($event->metadata['priority_factors']))
                                {{-- Por qué este ticket tiene esta prioridad. Sin
                                     explicación, una prioridad se percibe como
                                     arbitraria y acaba ignorándose. --}}
                                <ul class="mt-1 list-disc pl-4 text-xs text-slate-500">
                                    @foreach ($event->metadata['priority_factors'] as $factor)
                                        <li>{{ $factor }}</li>
                                    @endforeach
                                </ul>
                            @endif

                            @if (! empty($event->metadata['reason']))
                                <p class="text-slate-500">{{ $event->metadata['reason'] }}</p>
                            @endif

                            <p class="mt-0.5 text-xs text-slate-400">
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
            <div class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Asignación</h2>

                <p class="mb-3 text-sm text-slate-600">
                    {{ $incident->assignee?->name ?? 'Sin asignar' }}
                </p>

                @if ($incident->assigned_to !== auth()->id())
                    <form method="POST" action="{{ route('support.incidents.take', $incident) }}" class="mb-3">
                        @csrf
                        <button class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                            Tomar este ticket
                        </button>
                    </form>
                @endif

                <form method="POST" action="{{ route('support.incidents.assign', $incident) }}" class="flex gap-2">
                    @csrf
                    <select name="technician_id" required class="min-w-0 flex-1 rounded-md border-slate-300 px-3 py-2 text-sm">
                        <option value="">Asignar a…</option>
                        @foreach ($technicians as $t)
                            <option value="{{ $t->id }}" @selected($incident->assigned_to === $t->id)>{{ $t->name }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-md border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Asignar</button>
                </form>
            </div>
        @endcan

        @can('incidents.update')
            @unless ($incident->statusCode()->isResolved() || $incident->statusCode()->isTerminal())
                <div class="rounded-lg border border-slate-200 bg-white p-5">
                    <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Resolver</h2>

                    <form method="POST" action="{{ route('support.incidents.resolve', $incident) }}" class="space-y-3">
                        @csrf

                        <select name="resolution_type" required class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
                            @foreach ($resolutionTypes as $type)
                                @continue($type === \App\Shared\Enums\ResolutionType::None)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </select>

                        <textarea name="technical_diagnosis" rows="2" placeholder="Diagnóstico técnico (opcional)"
                                  class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm"></textarea>

                        <textarea name="resolution_notes" rows="3" required
                                  placeholder="¿Qué se hizo para resolverlo? (obligatorio)"
                                  class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm"></textarea>

                        <p class="text-xs text-slate-500">
                            La solución registrada alimenta la base de conocimiento y el análisis de
                            recurrencia. Un ticket cerrado sin explicación no enseña nada.
                        </p>

                        <button class="w-full rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                            Marcar como resuelta
                        </button>
                    </form>
                </div>
            @endunless
        @endcan

        @can('incidents.close')
            <div class="rounded-lg border border-slate-200 bg-white p-5 text-sm">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Cierre</h2>

                @if ($incident->statusCode() === \App\Shared\Enums\IncidentStatus::Resolved)
                    <form method="POST" action="{{ route('support.incidents.close', $incident) }}" class="mb-3">
                        @csrf
                        <button class="w-full rounded-md border border-slate-300 px-4 py-2 hover:bg-slate-50">Cerrar ticket</button>
                    </form>
                @endif

                @if ($incident->statusCode()->isResolved())
                    <form method="POST" action="{{ route('support.incidents.reopen', $incident) }}" class="space-y-2">
                        @csrf
                        <input name="reason" required placeholder="Motivo de la reapertura"
                               class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
                        <button class="w-full rounded-md border border-amber-300 bg-amber-50 px-4 py-2 text-amber-900 hover:bg-amber-100">
                            Reabrir
                        </button>
                    </form>
                @endif
            </div>
        @endcan

        @can('incidents.cancel')
            @unless ($incident->statusCode()->isTerminal())
                <div class="rounded-lg border border-slate-200 bg-white p-5">
                    <form method="POST" action="{{ route('support.incidents.cancel', $incident) }}" class="space-y-2"
                          data-confirm="¿Cancelar esta incidencia?">
                        @csrf
                        <input name="reason" required placeholder="Motivo de la cancelación"
                               class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
                        <button class="w-full rounded-md border border-rose-300 px-4 py-2 text-sm text-rose-700 hover:bg-rose-50">
                            Cancelar incidencia
                        </button>
                    </form>
                </div>
            @endunless
        @endcan

        <a href="{{ route('support.incidents.index') }}"
           class="block text-center text-sm text-slate-500 underline underline-offset-2">
            Volver a la bandeja
        </a>
    </div>
</div>
@endsection
