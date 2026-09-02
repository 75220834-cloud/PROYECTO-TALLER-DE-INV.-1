@extends('layouts.app')
@section('title', 'Historial de ' . $room->code)
@section('heading', 'Aula ' . $room->code)
@section('subheading', $room->fullPath())

@section('content')

<div class="grid gap-4 lg:grid-cols-3">

    {{-- Lo primero no es la lista de incidencias: es qué falla y cómo se
         arregló. El técnico mira esto para saber qué llevar. --}}
    <div class="glass p-5 lg:col-span-2">
        <h2 class="label-tech mb-3 text-on-surface-variant">Qué falla en esta aula</h2>

        @forelse ($porCategoria as $fila)
            <div class="flex items-baseline justify-between gap-3 border-b border-outline-variant py-2 last:border-0">
                <span class="text-on-surface">{{ $fila->categoria }}</span>
                <span class="shrink-0 text-sm text-on-surface-variant">
                    <strong class="text-on-surface">{{ $fila->total }}</strong>
                    @if ($fila->presenciales > 0)
                        · {{ $fila->presenciales }} requirió ir
                    @endif
                </span>
            </div>
        @empty
            <p class="text-sm text-on-surface-variant">Sin incidencias registradas todavía.</p>
        @endforelse
    </div>

    <div class="glass p-5">
        <h2 class="label-tech mb-3 text-on-surface-variant">Riesgo</h2>

        @if ($riesgo)
            <p class="text-3xl font-bold">{{ number_format($riesgo->score, 2) }}</p>
            <p class="chip mt-1 inline-block
                {{ $riesgo->risk_band === 'high' ? 'bg-danger-container text-on-danger-container'
                   : ($riesgo->risk_band === 'medium' ? 'bg-warn-container text-on-warn-container' : 'bg-surface-high') }}">
                {{ ['low' => 'Bajo', 'medium' => 'Medio', 'high' => 'Alto'][$riesgo->risk_band] ?? $riesgo->risk_band }}
            </p>

            {{-- El número sin sus factores se percibe como arbitrario y acaba
                 ignorándose (plan §15.5). --}}
            <ul class="mt-3 space-y-1 text-sm text-on-surface-variant">
                @foreach ($riesgo->factors as $factor)
                    <li>· {{ $factor['label'] }}</li>
                @endforeach
            </ul>

            <p class="mt-3 text-xs text-outline">
                Ordena por dónde revisar. No es una probabilidad de avería.
            </p>
        @else
            <p class="text-sm text-on-surface-variant">Todavía sin calcular.</p>
        @endif
    </div>
</div>

@if ($ultimasSoluciones->isNotEmpty())
    <div class="glass mt-4 p-5">
        <h2 class="label-tech mb-1 text-on-surface-variant">Cómo se resolvió las últimas veces</h2>
        <p class="mb-3 text-sm text-on-surface-variant">
            Tal como lo escribió el técnico. Es lo que evita el segundo viaje.
        </p>

        <div class="space-y-3">
            @foreach ($ultimasSoluciones as $solucion)
                <div class="border-b border-outline-variant pb-3 last:border-0 last:pb-0">
                    <p class="text-sm font-medium text-on-surface">
                        {{ $solucion->category?->name ?? 'Sin clasificar' }}
                        <span class="font-normal text-on-surface-variant">
                            · {{ $solucion->resolved_at?->timezone(config('incidencias.display_timezone'))->format('d/m/Y') }}
                        </span>
                    </p>
                    <p class="mt-1 text-sm text-on-surface-variant">{{ $solucion->resolution_notes }}</p>
                </div>
            @endforeach
        </div>
    </div>
@endif

<div class="glass mt-4 p-5">
    <h2 class="label-tech mb-3 text-on-surface-variant">Equipos del aula</h2>

    <div class="grid gap-2 sm:grid-cols-2">
        @forelse ($equipos as $equipo)
            <div class="flex items-baseline justify-between gap-3 border-b border-outline-variant py-2 last:border-0">
                <span>
                    {{ $equipo->tipo ?? 'Equipo' }}
                    <span class="font-mono text-xs text-on-surface-variant">{{ $equipo->asset_code }}</span>
                </span>
                <span class="chip shrink-0 {{ $equipo->status === 'operational' ? 'bg-ok-container text-on-ok-container' : 'bg-warn-container text-on-warn-container' }}">
                    {{ __('equipment-status.' . $equipo->status) }}
                </span>
            </div>
        @empty
            <p class="text-sm text-on-surface-variant">Sin equipos registrados.</p>
        @endforelse
    </div>
</div>

<h2 class="label-tech mb-3 mt-6 text-on-surface-variant">Todas las incidencias</h2>

<div class="scroll glass overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="border-b border-outline-variant text-left">
            <tr>
                <th class="label-tech px-4 py-3 text-on-surface-variant">Fecha</th>
                <th class="label-tech px-4 py-3 text-on-surface-variant">Problema</th>
                <th class="label-tech px-4 py-3 text-on-surface-variant">Estado</th>
                <th class="label-tech px-4 py-3 text-on-surface-variant">Cómo se cerró</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($incidencias as $i)
                <tr class="cursor-pointer border-b border-outline-variant last:border-0 hover:bg-surface-high"
                    data-row-href="{{ route('support.incidents.show', $i) }}">
                    <td class="whitespace-nowrap px-4 py-3 text-on-surface-variant">
                        {{ $i->reported_at?->timezone(config('incidencias.display_timezone'))->format('d/m/Y H:i') ?? '—' }}
                    </td>
                    <td class="px-4 py-3">{{ $i->category?->name ?? 'Sin clasificar' }}</td>
                    <td class="px-4 py-3">{{ $i->status?->name }}</td>
                    <td class="px-4 py-3 text-on-surface-variant">
                        {{ $i->resolution_type ? __('resolution-types.' . $i->resolution_type) : '—' }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-6 text-center text-on-surface-variant">Sin incidencias.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $incidencias->links() }}</div>
@endsection
