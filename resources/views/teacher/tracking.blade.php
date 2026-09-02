@extends('teacher.layout')
@section('title', 'Estado de tu solicitud')
@section('step', $incident->room?->code)

@section('content')

    @php
        $resuelto = $incident->resolved_at !== null || $incident->closed_at !== null;
        $enCamino = $incident->assigned_at !== null && ! $resuelto;
    @endphp

    {{-- El estado va grande y arriba: es lo único que el docente vino a
         saber. Todo lo demás es detalle. --}}
    <div class="glass px-5 py-6 text-center"
         @unless ($resuelto) data-auto-refresh="30" @endunless>
        <span class="material-symbols-outlined text-[40px] {{ $resuelto ? 'text-ok' : 'text-accent' }}"
              aria-hidden="true">
            {{ $resuelto ? 'check_circle' : ($enCamino ? 'engineering' : 'schedule') }}
        </span>

        <h1 class="mt-2 text-[28px] font-semibold leading-9 tracking-[-0.02em]">
            @if ($resuelto)
                Resuelto
            @elseif ($enCamino)
                Un técnico se hizo cargo
            @else
                Soporte ya fue avisado
            @endif
        </h1>

        <p class="mt-2 text-[18px] leading-7 text-on-surface-variant">
            @if ($resuelto)
                Si el problema vuelve, escanea el código otra vez.
            @elseif ($enCamino)
                Está atendiendo tu solicitud.
            @else
                Tu solicitud está en la cola de atención.
            @endif
        </p>

        @if ($incident->ticket_number)
            <p class="label-tech mt-4 text-on-surface-variant">Ticket {{ $incident->ticket_number }}</p>
        @endif
    </div>

    <div class="glass mt-4 px-5 py-4">
        <p class="label-tech text-on-surface-variant">Tu solicitud</p>
        <p class="mt-1 text-lg">{{ $incident->category?->name ?? 'Sin clasificar' }}</p>
        <p class="text-on-surface-variant">{{ $incident->room?->fullPath() }}</p>
    </div>

    {{-- Línea de tiempo. Solo aparecen los hitos que ya ocurrieron: una lista
         con pasos futuros en gris haría creer que el sistema sabe cuándo van
         a pasar, y no lo sabe. --}}
    <ol class="mt-4 space-y-3">
        @foreach ($hitos as $hito)
            <li class="glass flex items-start gap-3 px-4 py-3">
                <span class="material-symbols-outlined mt-0.5 shrink-0 text-ok" aria-hidden="true">
                    {{ $hito['icono'] }}
                </span>
                <div class="min-w-0">
                    <p class="font-medium">{{ $hito['titulo'] }}</p>
                    <p class="text-sm text-on-surface-variant">
                        {{ $hito['t']->timezone(config('incidencias.display_timezone'))->format('H:i · d/m/Y') }}
                    </p>
                </div>
            </li>
        @endforeach
    </ol>

    @unless ($resuelto)
        <p class="mt-5 text-center text-sm text-on-surface-variant">
            Esta página se actualiza sola. Puedes guardarla y volver cuando quieras.
        </p>
    @endunless
@endsection

@section('back')
    <a href="{{ route('teacher.start') }}"
       class="glass focus-ring flex min-h-[56px] w-full items-center justify-center px-5 py-3 text-lg font-medium">
        Reportar otro problema
    </a>
@endsection
