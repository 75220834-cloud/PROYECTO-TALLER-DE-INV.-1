@extends('layouts.app')
@section('title', 'Solicitudes rechazadas')
@section('heading', 'Solicitudes rechazadas')
@section('subheading', 'Últimos ' . $days . ' días')

@section('content')

{{-- Va arriba porque es la razón de ser de esta pantalla: los umbrales no
     están calibrados y esto es lo que permite corregirlos con datos en lugar
     de por intuición (plan 16.4). --}}
<div class="mb-5 glass border-warn/60 bg-warn-container/60 px-4 py-3 text-sm text-on-warn-container">
    <p class="font-medium">Para qué sirve esta pantalla</p>
    <p class="mt-1">
        Si aparecen muchos rechazos <strong>por red</strong>, lo más probable no es un ataque: es que
        se está bloqueando a docentes que comparten la salida de la WiFi institucional. Ese umbral es
        el más laxo de todos a propósito, y si aun así estorba, hay que subirlo.
    </p>
</div>

<form method="GET" class="mb-5">
    <select name="days" data-auto-submit class="rounded-md border-outline-variant px-3 py-2 text-sm">
        @foreach ([7 => '7 días', 30 => '30 días', 90 => '90 días'] as $v => $l)
            <option value="{{ $v }}" @selected($days == $v)>{{ $l }}</option>
        @endforeach
    </select>
</form>

<div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    @forelse ($byReason as $reason => $total)
        <div class="rounded-lg border {{ $reason === 'ip_rate' ? 'border-warn bg-warn-container' : 'border-outline-variant bg-surface-lowest' }} px-4 py-3">
            <p class="label-tech text-on-surface-variant">{{ __('abuse-reasons.' . $reason) }}</p>
            <p class="mt-1 text-2xl font-semibold {{ $reason === 'ip_rate' ? 'text-warn' : 'text-primary' }}">{{ $total }}</p>
        </div>
    @empty
        <p class="text-sm text-on-surface-variant">
            Ninguna solicitud fue rechazada en este periodo. Es lo normal en un piloto sano.
        </p>
    @endforelse
</div>

<div class="overflow-x-auto glass">
    <table class="w-full text-sm">
        <thead class="border-b border-outline-variant bg-surface-low text-left label-tech text-on-surface-variant">
            <tr>
                <th class="px-4 py-3">Cuándo</th>
                <th class="px-4 py-3">Aula</th>
                <th class="px-4 py-3">Categoría</th>
                <th class="px-4 py-3">Motivo</th>
                <th class="px-4 py-3">Qué escribió</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rejections as $rejection)
                <tr class="border-b border-surface-mid last:border-0">
                    <td class="whitespace-nowrap px-4 py-3 text-on-surface-variant">
                        {{ $rejection->occurred_at?->timezone(config('incidencias.display_timezone'))->format('d/m/Y H:i') }}
                    </td>
                    <td class="px-4 py-3 font-mono">{{ $rejection->room?->code ?? '—' }}</td>
                    <td class="px-4 py-3">{{ $rejection->category?->name ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <span class="chip {{ $rejection->reason === 'ip_rate' ? 'bg-warn-container text-on-warn-container' : 'bg-surface-mid text-on-surface-variant' }}">
                            {{ __('abuse-reasons.' . $rejection->reason) }}
                        </span>
                    </td>
                    <td class="max-w-sm truncate px-4 py-3 text-on-surface-variant">{{ $rejection->payload_excerpt ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-6 text-center text-on-surface-variant">Sin rechazos en este periodo.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $rejections->links() }}</div>

{{-- Los umbrales vigentes, a la vista. Sin esto habría que abrir el .env
     para saber contra qué se está comparando. --}}
<div class="mt-6 glass p-5">
    <h2 class="label-tech text-on-surface-variant">Umbrales vigentes</h2>
    <p class="mb-3 mt-1 text-xs text-outline">
        Provisionales: nadie los ha calibrado todavía. Se ajustan en <code>.env</code> y no requieren tocar código.
    </p>

    <dl class="grid gap-2 text-sm sm:grid-cols-2">
        @foreach ($thresholds as $key => $value)
            <div class="flex justify-between border-b border-surface-mid py-1">
                <dt class="font-mono text-xs text-on-surface-variant">{{ $key }}</dt>
                <dd class="font-medium">{{ is_bool($value) ? ($value ? 'sí' : 'no') : $value }}</dd>
            </div>
        @endforeach
    </dl>
</div>
@endsection
