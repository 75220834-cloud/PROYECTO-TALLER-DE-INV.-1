@extends('layouts.app')
@section('title', 'Equipos')
@section('heading', 'Equipos')

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <form method="GET" class="flex flex-wrap items-center gap-2">
        <input name="q" value="{{ $search }}" placeholder="Código de inventario o serie…"
               class="rounded-md border-slate-300 px-3 py-2 text-sm">
        <select name="status" class="rounded-md border-slate-300 px-3 py-2 text-sm">
            <option value="">Todos los estados</option>
            @foreach ($statuses as $v => $l)
                <option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>
            @endforeach
        </select>
        <button class="rounded-md border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Filtrar</button>
    </form>
    @can('equipment.manage')
        <a href="{{ route('admin.equipment.create') }}" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Nuevo equipo</a>
    @endcan
</div>

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-4 py-3">Inventario</th>
                <th class="px-4 py-3">Tipo</th>
                <th class="px-4 py-3">Aula</th>
                <th class="px-4 py-3">Marca / modelo</th>
                <th class="px-4 py-3">Estado</th>
                @can('equipment.manage')<th class="px-4 py-3 text-right">Acciones</th>@endcan
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($equipment as $e)
            <tr>
                <td class="px-4 py-3 font-mono text-xs">{{ $e->asset_code }}</td>
                <td class="px-4 py-3">{{ $e->type?->name }}</td>
                <td class="px-4 py-3 font-mono text-xs">{{ $e->room?->code }}</td>
                <td class="px-4 py-3 text-slate-500">{{ trim(($e->brand ?? '') . ' ' . ($e->model ?? '')) ?: '—' }}</td>
                <td class="px-4 py-3">
                    <span @class([
                        'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                        'bg-emerald-100 text-emerald-800' => $e->status === 'operational',
                        'bg-amber-100 text-amber-800' => $e->status === 'degraded',
                        'bg-sky-100 text-sky-800' => $e->status === 'in_maintenance',
                        'bg-rose-100 text-rose-800' => $e->status === 'out_of_service',
                    ])>{{ $statuses[$e->status] ?? $e->status }}</span>

                    @if ($e->is_demo)
                        <span class="ml-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">DEMO</span>
                    @endif
                </td>
                @can('equipment.manage')
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.equipment.edit', $e) }}" class="text-sm text-slate-600 underline-offset-2 hover:text-slate-900 hover:underline">Editar</a>
                    </td>
                @endcan
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No hay equipos que coincidan.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $equipment->links() }}</div>

<p class="mt-4 text-xs text-slate-500">
    Los equipos no se eliminan: se marcan como fuera de servicio. Un equipo con incidencias en el
    historial es la unidad de análisis del módulo de riesgo; borrarlo dejaría esas incidencias huérfanas.
</p>
@endsection
