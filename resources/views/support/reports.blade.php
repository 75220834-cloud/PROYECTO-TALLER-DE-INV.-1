@extends('layouts.app')
@section('title', 'Reportes')
@section('heading', 'Reportes')
@section('subheading', 'Dónde se concentra el problema')

@section('content')

@php
    $etiquetas = [
        'pabellon' => 'Pabellón',
        'aula' => 'Aula',
        'categoria' => 'Tipo de problema',
        'equipo' => 'Tipo de equipo',
        'tecnico' => 'Técnico',
        'mes' => 'Mes',
    ];
@endphp

{{-- Filtros. Se envían por GET a propósito: así el reporte que alguien mira
     se puede pegar en un correo como enlace y el que lo abre ve exactamente
     lo mismo. --}}
<form method="GET" action="{{ route('support.reports') }}" class="glass mb-4 p-5">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
        <label class="block">
            <span class="label-tech text-on-surface-variant">Desde</span>
            <input type="date" name="desde" value="{{ $filtros['desde']->format('Y-m-d') }}"
                   class="mt-1 block w-full rounded-md border-outline-variant px-3 py-2 text-sm">
        </label>

        <label class="block">
            <span class="label-tech text-on-surface-variant">Hasta</span>
            <input type="date" name="hasta" value="{{ $filtros['hasta']->format('Y-m-d') }}"
                   class="mt-1 block w-full rounded-md border-outline-variant px-3 py-2 text-sm">
        </label>

        <label class="block">
            <span class="label-tech text-on-surface-variant">Agrupar por</span>
            <select name="por" class="mt-1 block w-full rounded-md border-outline-variant px-3 py-2 text-sm">
                @foreach ($etiquetas as $valor => $texto)
                    <option value="{{ $valor }}" @selected($por === $valor)>{{ $texto }}</option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="label-tech text-on-surface-variant">Pabellón</span>
            <select name="pabellon" class="mt-1 block w-full rounded-md border-outline-variant px-3 py-2 text-sm">
                <option value="">Todos</option>
                @foreach ($pabellones as $p)
                    <option value="{{ $p->id }}" @selected($filtros['building_id'] === $p->id)>{{ $p->code }} · {{ $p->name }}</option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="label-tech text-on-surface-variant">Problema</span>
            <select name="categoria" class="mt-1 block w-full rounded-md border-outline-variant px-3 py-2 text-sm">
                <option value="">Todos</option>
                @foreach ($categorias as $c)
                    <option value="{{ $c->id }}" @selected($filtros['category_id'] === $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="label-tech text-on-surface-variant">Técnico</span>
            <select name="tecnico" class="mt-1 block w-full rounded-md border-outline-variant px-3 py-2 text-sm">
                <option value="">Todos</option>
                @foreach ($tecnicos as $t)
                    <option value="{{ $t->id }}" @selected($filtros['assigned_to'] === $t->id)>{{ $t->name }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-3">
        <button class="btn-primary focus-ring px-5 py-2 text-sm font-medium">Ver reporte</button>

        @can('reports.export')
            <a href="{{ route('support.reports.export', request()->query()) }}"
               class="focus-ring rounded-md border border-outline-variant px-5 py-2 text-sm hover:bg-surface-low">
                Descargar CSV
            </a>
        @endcan
    </div>
</form>

{{-- Los totales van antes que el desglose: sin saber sobre cuántos casos se
     habla, un porcentaje no significa nada. Con 6 incidencias, «el 50 % en
     el pabellón C» son 3 tickets. --}}
<div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    @foreach ([
        ['Incidencias en el periodo', $totales['total']],
        ['Todavía abiertas', $totales['abiertas']],
        ['Impidieron dictar clase', $totales['bloquearon_clase']],
        ['Resueltas sin que fuera nadie', $totales['resueltas_sin_visita']],
    ] as $tarjeta)
        <div class="glass p-5">
            <p class="label-tech text-on-surface-variant">{{ $tarjeta[0] }}</p>
            <p class="mt-1 text-3xl font-bold">{{ $tarjeta[1] }}</p>
        </div>
    @endforeach
</div>

@if ($totales['total'] > 0 && $totales['total'] < 30)
    {{-- Advertencia deliberada. Este reporte va a acabar citado en la tesis,
         y con pocos casos las diferencias entre filas son ruido. --}}
    <p class="glass mb-4 border-warn/60 bg-warn-container/60 px-4 py-3 text-sm text-on-warn-container">
        Solo hay {{ $totales['total'] }} incidencias en este periodo. Es muy poco para sacar
        conclusiones: las diferencias entre filas pueden ser casualidad.
    </p>
@endif

<div class="scroll glass overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="border-b border-outline-variant text-left">
            <tr>
                <th class="label-tech px-4 py-3 text-on-surface-variant">{{ $etiquetas[$por] }}</th>
                <th class="label-tech px-4 py-3 text-right text-on-surface-variant">Incidencias</th>
                <th class="label-tech px-4 py-3 text-right text-on-surface-variant">Impidieron la clase</th>
                <th class="label-tech px-4 py-3 text-right text-on-surface-variant">Sin visita</th>
                <th class="label-tech px-4 py-3 text-right text-on-surface-variant">Con visita</th>
                <th class="label-tech px-4 py-3 text-right text-on-surface-variant">Abiertas</th>
                <th class="label-tech px-4 py-3 text-right text-on-surface-variant">Promedio</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $fila)
                <tr class="border-b border-outline-variant last:border-0">
                    <td class="px-4 py-3 font-medium">{{ $fila->etiqueta }}</td>
                    <td class="px-4 py-3 text-right font-semibold">{{ $fila->total }}</td>
                    <td class="px-4 py-3 text-right text-on-surface-variant">{{ $fila->bloquearon_clase }}</td>
                    <td class="px-4 py-3 text-right text-on-surface-variant">{{ $fila->resueltas_sin_visita }}</td>
                    <td class="px-4 py-3 text-right text-on-surface-variant">{{ $fila->requirieron_visita }}</td>
                    <td class="px-4 py-3 text-right text-on-surface-variant">{{ $fila->abiertas }}</td>
                    <td class="px-4 py-3 text-right text-on-surface-variant">
                        @if ($fila->minutos_promedio !== null)
                            {{ round((float) $fila->minutos_promedio) }} min
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-on-surface-variant">
                        No hay incidencias en este periodo con esos filtros.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<p class="mt-3 text-xs text-outline">
    «Promedio» es el tiempo entre que el docente envió la solicitud y que se marcó resuelta.
    No incluye los tickets todavía abiertos, así que baja cuando algo lleva mucho sin cerrarse.
</p>
@endsection
