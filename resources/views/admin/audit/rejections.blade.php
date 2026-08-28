@extends('layouts.app')
@section('title', 'Solicitudes rechazadas')
@section('heading', 'Solicitudes rechazadas')
@section('subheading', 'Últimos ' . $days . ' días')

@section('content')

{{-- Va arriba porque es la razón de ser de esta pantalla: los umbrales no
     están calibrados y esto es lo que permite corregirlos con datos en lugar
     de por intuición (plan 16.4). --}}
<div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
    <p class="font-medium">Para qué sirve esta pantalla</p>
    <p class="mt-1">
        Si aparecen muchos rechazos <strong>por red</strong>, lo más probable no es un ataque: es que
        se está bloqueando a docentes que comparten la salida de la WiFi institucional. Ese umbral es
        el más laxo de todos a propósito, y si aun así estorba, hay que subirlo.
    </p>
</div>

<form method="GET" class="mb-5">
    <select name="days" data-auto-submit class="rounded-md border-slate-300 px-3 py-2 text-sm">
        @foreach ([7 => '7 días', 30 => '30 días', 90 => '90 días'] as $v => $l)
            <option value="{{ $v }}" @selected($days == $v)>{{ $l }}</option>
        @endforeach
    </select>
</form>

<div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    @forelse ($byReason as $reason => $total)
        <div class="rounded-lg border {{ $reason === 'ip_rate' ? 'border-amber-300 bg-amber-50' : 'border-slate-200 bg-white' }} px-4 py-3">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('abuse-reasons.' . $reason) }}</p>
            <p class="mt-1 text-2xl font-semibold {{ $reason === 'ip_rate' ? 'text-amber-700' : 'text-slate-900' }}">{{ $total }}</p>
        </div>
    @empty
        <p class="text-sm text-slate-500">
            Ninguna solicitud fue rechazada en este periodo. Es lo normal en un piloto sano.
        </p>
    @endforelse
</div>

<div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
    <table class="w-full text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
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
                <tr class="border-b border-slate-100 last:border-0">
                    <td class="whitespace-nowrap px-4 py-3 text-slate-600">
                        {{ $rejection->occurred_at?->timezone(config('incidencias.display_timezone'))->format('d/m/Y H:i') }}
                    </td>
                    <td class="px-4 py-3 font-mono">{{ $rejection->room?->code ?? '—' }}</td>
                    <td class="px-4 py-3">{{ $rejection->category?->name ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <span class="rounded px-2 py-0.5 text-xs {{ $rejection->reason === 'ip_rate' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700' }}">
                            {{ __('abuse-reasons.' . $rejection->reason) }}
                        </span>
                    </td>
                    <td class="max-w-sm truncate px-4 py-3 text-slate-600">{{ $rejection->payload_excerpt ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">Sin rechazos en este periodo.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $rejections->links() }}</div>

{{-- Los umbrales vigentes, a la vista. Sin esto habría que abrir el .env
     para saber contra qué se está comparando. --}}
<div class="mt-6 rounded-lg border border-slate-200 bg-white p-5">
    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Umbrales vigentes</h2>
    <p class="mb-3 mt-1 text-xs text-slate-400">
        Provisionales: nadie los ha calibrado todavía. Se ajustan en <code>.env</code> y no requieren tocar código.
    </p>

    <dl class="grid gap-2 text-sm sm:grid-cols-2">
        @foreach ($thresholds as $key => $value)
            <div class="flex justify-between border-b border-slate-100 py-1">
                <dt class="font-mono text-xs text-slate-600">{{ $key }}</dt>
                <dd class="font-medium">{{ is_bool($value) ? ($value ? 'sí' : 'no') : $value }}</dd>
            </div>
        @endforeach
    </dl>
</div>
@endsection
