@extends('layouts.app')
@section('title', 'Equipos')
@section('heading', 'Equipos')

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <form method="GET" class="flex flex-wrap items-center gap-2">
        <input name="q" value="{{ $search }}" placeholder="Código de inventario o serie…"
               class="rounded-md border-outline-variant px-3 py-2 text-sm">
        <select name="status" class="rounded-md border-outline-variant px-3 py-2 text-sm">
            <option value="">Todos los estados</option>
            @foreach ($statuses as $v => $l)
                <option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>
            @endforeach
        </select>
        <button class="rounded-md border border-outline-variant px-3 py-2 text-sm hover:bg-surface-low">Filtrar</button>
    </form>
    @can('equipment.manage')
        <a href="{{ route('admin.equipment.create') }}" class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-on-primary hover:bg-on-surface">Nuevo equipo</a>
    @endcan
</div>

<div class="overflow-hidden rounded-lg border border-outline-variant bg-surface-lowest">
    <table class="min-w-full divide-y divide-outline-variant text-sm">
        <thead class="bg-surface-low text-left text-xs uppercase tracking-wide text-on-surface-variant">
            <tr>
                <th class="px-4 py-3">Inventario</th>
                <th class="px-4 py-3">Tipo</th>
                <th class="px-4 py-3">Aula</th>
                <th class="px-4 py-3">Marca / modelo</th>
                <th class="px-4 py-3">Estado</th>
                @can('equipment.manage')<th class="px-4 py-3 text-right">Acciones</th>@endcan
            </tr>
        </thead>
        <tbody class="divide-y divide-surface-mid">
        @forelse ($equipment as $e)
            <tr>
                <td class="px-4 py-3 font-mono text-xs">{{ $e->asset_code }}</td>
                <td class="px-4 py-3">{{ $e->type?->name }}</td>
                <td class="px-4 py-3 font-mono text-xs">{{ $e->room?->code }}</td>
                <td class="px-4 py-3 text-on-surface-variant">{{ trim(($e->brand ?? '') . ' ' . ($e->model ?? '')) ?: '—' }}</td>
                <td class="px-4 py-3">
                    <span @class([
                        'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                        'bg-ok-container text-on-ok-container' => $e->status === 'operational',
                        'bg-warn-container text-on-warn-container' => $e->status === 'degraded',
                        'bg-primary-container text-on-primary-container' => $e->status === 'in_maintenance',
                        'bg-danger-container text-on-danger-container' => $e->status === 'out_of_service',
                    ])>{{ $statuses[$e->status] ?? $e->status }}</span>

                    @if ($e->is_demo)
                        <span class="ml-1 inline-flex rounded-full bg-warn-container px-2 py-0.5 text-xs font-medium text-on-warn-container">DEMO</span>
                    @endif
                </td>
                @can('equipment.manage')
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.equipment.edit', $e) }}" class="text-sm text-on-surface-variant underline-offset-2 hover:text-primary hover:underline">Editar</a>
                    </td>
                @endcan
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-8 text-center text-on-surface-variant">No hay equipos que coincidan.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $equipment->links() }}</div>

<p class="mt-4 text-xs text-on-surface-variant">
    Los equipos no se eliminan: se marcan como fuera de servicio. Un equipo con incidencias en el
    historial es la unidad de análisis del módulo de riesgo; borrarlo dejaría esas incidencias huérfanas.
</p>
@endsection
