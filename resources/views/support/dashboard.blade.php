@extends('layouts.app')
@section('title', 'Tablero')
@section('heading', 'Tablero')
@section('subheading', 'Últimos ' . $days . ' días')

@section('content')

<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <form method="GET" class="flex items-center gap-2">
        <select name="days" data-auto-submit class="rounded-md border-outline-variant px-3 py-2 text-sm">
            @foreach ([7 => '7 días', 30 => '30 días', 90 => '90 días', 180 => '6 meses'] as $v => $l)
                <option value="{{ $v }}" @selected($days == $v)>{{ $l }}</option>
            @endforeach
        </select>
    </form>

    @can('reports.export')
        <a href="{{ route('support.dashboard.export') }}"
           class="rounded-md border border-outline-variant bg-surface-lowest px-4 py-2 text-sm hover:bg-surface-low">
            Exportar datos (CSV)
        </a>
    @endcan
</div>

{{-- ------------------------------------------------------- volumen --}}
<div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    @foreach ([
        ['Incidencias', $metrics['volume']['total'], 'text-primary'],
        ['Abiertas', $metrics['volume']['open'], 'text-primary'],
        ['Con clase detenida', $metrics['volume']['blocking'], 'text-danger'],
        ['Críticas', $metrics['volume']['critical'], 'text-danger'],
    ] as [$label, $value, $color])
        <div class="glass px-4 py-3">
            <p class="label-tech text-on-surface-variant">{{ $label }}</p>
            <p class="mt-1 text-2xl font-semibold {{ $color }}">{{ $value }}</p>
        </div>
    @endforeach
</div>

<div class="grid gap-6 lg:grid-cols-2">

    {{-- El indicador central de la investigación. --}}
    <div class="glass p-5">
        <h2 class="mb-1 label-tech text-on-surface-variant">Autonomía de resolución</h2>
        <p class="mb-4 text-xs text-outline">Qué proporción se resolvió sin que nadie se desplazara</p>

        <p class="text-4xl font-bold text-ok">{{ $metrics['resolution']['autonomyRate'] }}%</p>

        <dl class="mt-4 space-y-1 text-sm">
            <div class="flex justify-between"><dt class="text-on-surface-variant">Resueltas por el propio docente</dt><dd class="font-medium">{{ $metrics['resolution']['byAssistant'] }}</dd></div>
            <div class="flex justify-between"><dt class="text-on-surface-variant">Resueltas en remoto</dt><dd class="font-medium">{{ $metrics['resolution']['remote'] }}</dd></div>
            <div class="flex justify-between"><dt class="text-on-surface-variant">Requirieron ir al aula</dt><dd class="font-medium">{{ $metrics['resolution']['onsite'] }}</dd></div>
            <div class="flex justify-between border-t border-surface-mid pt-1">
                <dt class="font-medium text-on-surface">Desplazamientos evitados</dt>
                <dd class="font-semibold text-ok">{{ $metrics['resolution']['tripsAvoided'] }}</dd>
            </div>
        </dl>
    </div>

    {{-- Medianas, no medias: una incidencia olvidada un fin de semana
         desplazaría la media varias horas y haría ilegible el dato. --}}
    <div class="glass p-5">
        <h2 class="mb-1 label-tech text-on-surface-variant">Tiempos</h2>
        <p class="mb-4 text-xs text-outline">Mediana en minutos, para describir el caso típico</p>

        <dl class="space-y-3">
            <div>
                <dt class="text-sm text-on-surface-variant">Hasta la primera respuesta de soporte</dt>
                <dd class="text-2xl font-semibold">
                    {{ $metrics['times']['medianFirstResponse'] !== null ? $metrics['times']['medianFirstResponse'] . ' min' : '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-sm text-on-surface-variant">Hasta la resolución</dt>
                <dd class="text-2xl font-semibold">
                    {{ $metrics['times']['medianResolution'] !== null ? $metrics['times']['medianResolution'] . ' min' : '—' }}
                </dd>
                <dd class="mt-0.5 text-xs text-outline">
                    Se cuenta desde que el docente confirmó el aula, no desde que se creó el ticket
                </dd>
            </div>
        </dl>
    </div>

    {{-- Indicador incómodo: el coste del QR genérico. --}}
    <div class="glass p-5">
        <h2 class="mb-1 label-tech text-on-surface-variant">Fricción de acceso</h2>
        <p class="mb-4 text-xs text-outline">Docentes que entraron y no llegaron a reportar</p>

        <p class="text-4xl font-bold {{ $metrics['friction']['abandonmentRate'] > 30 ? 'text-warn' : 'text-primary' }}">
            {{ $metrics['friction']['abandonmentRate'] }}%
        </p>

        <dl class="mt-4 space-y-1 text-sm">
            <div class="flex justify-between"><dt class="text-on-surface-variant">Abandonaron</dt><dd class="font-medium">{{ $metrics['friction']['abandoned'] }}</dd></div>
            <div class="flex justify-between"><dt class="text-on-surface-variant">Completaron</dt><dd class="font-medium">{{ $metrics['friction']['completed'] }}</dd></div>
        </dl>
    </div>

    {{-- Los rechazos se muestran SIEMPRE, aunque sean cero: si aparecen
         muchos por IP, probablemente se esté bloqueando a docentes que
         comparten la WiFi institucional. --}}
    <div class="glass p-5">
        <h2 class="mb-1 label-tech text-on-surface-variant">Solicitudes rechazadas</h2>
        <p class="mb-4 text-xs text-outline">Controles antiabuso. Muchos rechazos por IP = revisar umbrales</p>

        @if (empty($metrics['channel']))
            <p class="text-sm text-on-surface-variant">Ninguna solicitud fue rechazada en este periodo.</p>
        @else
            <dl class="space-y-1 text-sm">
                @foreach ($metrics['channel'] as $reason => $count)
                    <div class="flex justify-between">
                        <dt class="text-on-surface-variant">{{ __('abuse-reasons.' . $reason) }}</dt>
                        <dd class="font-medium {{ $reason === 'ip_rate' ? 'text-warn' : '' }}">{{ $count }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </div>

    <div class="glass p-5">
        <h2 class="mb-3 label-tech text-on-surface-variant">Aulas con más incidencias</h2>

        @forelse ($metrics['topRooms'] as $room)
            <div class="flex items-center justify-between border-b border-surface-mid py-1.5 text-sm last:border-0">
                <span class="font-mono">{{ $room->code }}</span>
                <span class="font-medium">{{ $room->total }}</span>
            </div>
        @empty
            <p class="text-sm text-on-surface-variant">Sin datos todavía.</p>
        @endforelse
    </div>

    <div class="glass p-5">
        <h2 class="mb-3 label-tech text-on-surface-variant">Problemas más frecuentes</h2>

        @forelse ($metrics['topCategories'] as $category)
            <div class="flex items-center justify-between border-b border-surface-mid py-1.5 text-sm last:border-0">
                <span>{{ $category->name }}</span>
                <span class="font-medium">{{ $category->total }}</span>
            </div>
        @empty
            <p class="text-sm text-on-surface-variant">Sin datos todavía.</p>
        @endforelse
    </div>

    {{-- Convierte una decisión de diseño en una observación medible. --}}
    <div class="glass p-5">
        <h2 class="mb-1 label-tech text-on-surface-variant">¿Ayudan las imágenes?</h2>
        <p class="mb-4 text-xs text-outline">Tiempo medio por paso, con y sin apoyo visual</p>

        <dl class="space-y-1 text-sm">
            <div class="flex justify-between">
                <dt class="text-on-surface-variant">Pasos con imagen</dt>
                <dd class="font-medium">
                    {{ $metrics['visualAid']['stepsWithImage'] }}
                    @if ($metrics['visualAid']['avgSecondsWithImage'] !== null)
                        <span class="text-outline">· {{ $metrics['visualAid']['avgSecondsWithImage'] }} s</span>
                    @endif
                </dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-on-surface-variant">Pasos sin imagen</dt>
                <dd class="font-medium">
                    {{ $metrics['visualAid']['stepsWithoutImage'] }}
                    @if ($metrics['visualAid']['avgSecondsWithoutImage'] !== null)
                        <span class="text-outline">· {{ $metrics['visualAid']['avgSecondsWithoutImage'] }} s</span>
                    @endif
                </dd>
            </div>
        </dl>

        <p class="mt-3 text-xs text-outline">
            Con pocos datos esta comparación no significa nada todavía.
        </p>
    </div>

    <div class="glass p-5">
        <h2 class="mb-1 label-tech text-on-surface-variant">Facilidad de uso</h2>
        <p class="mb-4 text-xs text-outline">Encuesta opcional al cerrar</p>

        <p class="text-4xl font-bold">
            {{ $metrics['satisfaction']['averageEase'] !== null ? $metrics['satisfaction']['averageEase'] . ' / 5' : '—' }}
        </p>
        <p class="mt-2 text-sm text-on-surface-variant">{{ $metrics['satisfaction']['responses'] }} respuestas</p>
    </div>
</div>
@endsection
