@extends('layouts.app')
@section('title', 'Tablero')
@section('heading', 'Tablero')
@section('subheading', 'Últimos ' . $days . ' días')

@section('content')

<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <form method="GET" class="flex items-center gap-2">
        <select name="days" onchange="this.form.submit()" class="rounded-md border-slate-300 px-3 py-2 text-sm">
            @foreach ([7 => '7 días', 30 => '30 días', 90 => '90 días', 180 => '6 meses'] as $v => $l)
                <option value="{{ $v }}" @selected($days == $v)>{{ $l }}</option>
            @endforeach
        </select>
    </form>

    @can('reports.export')
        <a href="{{ route('support.dashboard.export') }}"
           class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm hover:bg-slate-50">
            Exportar datos (CSV)
        </a>
    @endcan
</div>

{{-- ------------------------------------------------------- volumen --}}
<div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    @foreach ([
        ['Incidencias', $metrics['volume']['total'], 'text-slate-900'],
        ['Abiertas', $metrics['volume']['open'], 'text-sky-700'],
        ['Con clase detenida', $metrics['volume']['blocking'], 'text-rose-700'],
        ['Críticas', $metrics['volume']['critical'], 'text-rose-700'],
    ] as [$label, $value, $color])
        <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ $label }}</p>
            <p class="mt-1 text-2xl font-semibold {{ $color }}">{{ $value }}</p>
        </div>
    @endforeach
</div>

<div class="grid gap-6 lg:grid-cols-2">

    {{-- El indicador central de la investigación. --}}
    <div class="rounded-lg border border-slate-200 bg-white p-5">
        <h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-slate-500">Autonomía de resolución</h2>
        <p class="mb-4 text-xs text-slate-400">Qué proporción se resolvió sin que nadie se desplazara</p>

        <p class="text-4xl font-bold text-emerald-700">{{ $metrics['resolution']['autonomyRate'] }}%</p>

        <dl class="mt-4 space-y-1 text-sm">
            <div class="flex justify-between"><dt class="text-slate-600">Resueltas por el propio docente</dt><dd class="font-medium">{{ $metrics['resolution']['byAssistant'] }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-600">Resueltas en remoto</dt><dd class="font-medium">{{ $metrics['resolution']['remote'] }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-600">Requirieron ir al aula</dt><dd class="font-medium">{{ $metrics['resolution']['onsite'] }}</dd></div>
            <div class="flex justify-between border-t border-slate-100 pt-1">
                <dt class="font-medium text-slate-800">Desplazamientos evitados</dt>
                <dd class="font-semibold text-emerald-700">{{ $metrics['resolution']['tripsAvoided'] }}</dd>
            </div>
        </dl>
    </div>

    {{-- Medianas, no medias: una incidencia olvidada un fin de semana
         desplazaría la media varias horas y haría ilegible el dato. --}}
    <div class="rounded-lg border border-slate-200 bg-white p-5">
        <h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-slate-500">Tiempos</h2>
        <p class="mb-4 text-xs text-slate-400">Mediana en minutos, para describir el caso típico</p>

        <dl class="space-y-3">
            <div>
                <dt class="text-sm text-slate-600">Hasta la primera respuesta de soporte</dt>
                <dd class="text-2xl font-semibold">
                    {{ $metrics['times']['medianFirstResponse'] !== null ? $metrics['times']['medianFirstResponse'] . ' min' : '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-sm text-slate-600">Hasta la resolución</dt>
                <dd class="text-2xl font-semibold">
                    {{ $metrics['times']['medianResolution'] !== null ? $metrics['times']['medianResolution'] . ' min' : '—' }}
                </dd>
                <dd class="mt-0.5 text-xs text-slate-400">
                    Se cuenta desde que el docente confirmó el aula, no desde que se creó el ticket
                </dd>
            </div>
        </dl>
    </div>

    {{-- Indicador incómodo: el coste del QR genérico. --}}
    <div class="rounded-lg border border-slate-200 bg-white p-5">
        <h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-slate-500">Fricción de acceso</h2>
        <p class="mb-4 text-xs text-slate-400">Docentes que entraron y no llegaron a reportar</p>

        <p class="text-4xl font-bold {{ $metrics['friction']['abandonmentRate'] > 30 ? 'text-amber-600' : 'text-slate-900' }}">
            {{ $metrics['friction']['abandonmentRate'] }}%
        </p>

        <dl class="mt-4 space-y-1 text-sm">
            <div class="flex justify-between"><dt class="text-slate-600">Abandonaron</dt><dd class="font-medium">{{ $metrics['friction']['abandoned'] }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-600">Completaron</dt><dd class="font-medium">{{ $metrics['friction']['completed'] }}</dd></div>
        </dl>
    </div>

    {{-- Los rechazos se muestran SIEMPRE, aunque sean cero: si aparecen
         muchos por IP, probablemente se esté bloqueando a docentes que
         comparten la WiFi institucional. --}}
    <div class="rounded-lg border border-slate-200 bg-white p-5">
        <h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-slate-500">Solicitudes rechazadas</h2>
        <p class="mb-4 text-xs text-slate-400">Controles antiabuso. Muchos rechazos por IP = revisar umbrales</p>

        @if (empty($metrics['channel']))
            <p class="text-sm text-slate-500">Ninguna solicitud fue rechazada en este periodo.</p>
        @else
            <dl class="space-y-1 text-sm">
                @foreach ($metrics['channel'] as $reason => $count)
                    <div class="flex justify-between">
                        <dt class="text-slate-600">{{ __('abuse-reasons.' . $reason) }}</dt>
                        <dd class="font-medium {{ $reason === 'ip_rate' ? 'text-amber-700' : '' }}">{{ $count }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Aulas con más incidencias</h2>

        @forelse ($metrics['topRooms'] as $room)
            <div class="flex items-center justify-between border-b border-slate-100 py-1.5 text-sm last:border-0">
                <span class="font-mono">{{ $room->code }}</span>
                <span class="font-medium">{{ $room->total }}</span>
            </div>
        @empty
            <p class="text-sm text-slate-500">Sin datos todavía.</p>
        @endforelse
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Problemas más frecuentes</h2>

        @forelse ($metrics['topCategories'] as $category)
            <div class="flex items-center justify-between border-b border-slate-100 py-1.5 text-sm last:border-0">
                <span>{{ $category->name }}</span>
                <span class="font-medium">{{ $category->total }}</span>
            </div>
        @empty
            <p class="text-sm text-slate-500">Sin datos todavía.</p>
        @endforelse
    </div>

    {{-- Convierte una decisión de diseño en una observación medible. --}}
    <div class="rounded-lg border border-slate-200 bg-white p-5">
        <h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-slate-500">¿Ayudan las imágenes?</h2>
        <p class="mb-4 text-xs text-slate-400">Tiempo medio por paso, con y sin apoyo visual</p>

        <dl class="space-y-1 text-sm">
            <div class="flex justify-between">
                <dt class="text-slate-600">Pasos con imagen</dt>
                <dd class="font-medium">
                    {{ $metrics['visualAid']['stepsWithImage'] }}
                    @if ($metrics['visualAid']['avgSecondsWithImage'] !== null)
                        <span class="text-slate-400">· {{ $metrics['visualAid']['avgSecondsWithImage'] }} s</span>
                    @endif
                </dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-slate-600">Pasos sin imagen</dt>
                <dd class="font-medium">
                    {{ $metrics['visualAid']['stepsWithoutImage'] }}
                    @if ($metrics['visualAid']['avgSecondsWithoutImage'] !== null)
                        <span class="text-slate-400">· {{ $metrics['visualAid']['avgSecondsWithoutImage'] }} s</span>
                    @endif
                </dd>
            </div>
        </dl>

        <p class="mt-3 text-xs text-slate-400">
            Con pocos datos esta comparación no significa nada todavía.
        </p>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5">
        <h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-slate-500">Facilidad de uso</h2>
        <p class="mb-4 text-xs text-slate-400">Encuesta opcional al cerrar</p>

        <p class="text-4xl font-bold">
            {{ $metrics['satisfaction']['averageEase'] !== null ? $metrics['satisfaction']['averageEase'] . ' / 5' : '—' }}
        </p>
        <p class="mt-2 text-sm text-slate-500">{{ $metrics['satisfaction']['responses'] }} respuestas</p>
    </div>
</div>
@endsection
