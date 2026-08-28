@extends('layouts.app')
@section('title', 'Aulas')
@section('heading', 'Aulas')

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <form method="GET" class="flex flex-wrap items-center gap-2">
        <input name="q" value="{{ $search }}" placeholder="Buscar por código…"
               class="rounded-md border-outline-variant px-3 py-2 text-sm">
        <select name="floor_id" class="rounded-md border-outline-variant px-3 py-2 text-sm">
            <option value="">Todos los pisos</option>
            @foreach ($floors as $f)
                <option value="{{ $f->id }}" @selected(request('floor_id') == $f->id)>{{ $f->building?->code }} · {{ $f->label }}</option>
            @endforeach
        </select>
        <button class="rounded-md border border-outline-variant px-3 py-2 text-sm hover:bg-surface-low">Filtrar</button>
    </form>
    <a href="{{ route('admin.rooms.create') }}" class="btn-primary focus-ring px-4 py-2 text-sm font-medium hover:bg-on-surface">Nueva aula</a>
</div>

<div class="overflow-hidden glass">
    <table class="min-w-full divide-y divide-outline-variant text-sm">
        <thead class="bg-surface-low text-left label-tech text-on-surface-variant">
            <tr><th class="px-4 py-3">Código</th><th class="px-4 py-3">Ubicación</th><th class="px-4 py-3">Cap.</th>
                <th class="px-4 py-3">Crit.</th><th class="px-4 py-3">Equipos</th><th class="px-4 py-3">Estado</th>
                <th class="px-4 py-3 text-right">Acciones</th></tr>
        </thead>
        <tbody class="divide-y divide-surface-mid">
        @forelse ($rooms as $r)
            <tr>
                <td class="px-4 py-3 font-mono text-xs font-semibold">{{ $r->code }}</td>
                <td class="px-4 py-3 text-on-surface-variant">{{ $r->floor?->building?->name }} · {{ $r->floor?->label }}</td>
                <td class="px-4 py-3 text-on-surface-variant">{{ $r->capacity ?? '—' }}</td>
                <td class="px-4 py-3 text-on-surface-variant">{{ ['1' => 'Normal', '2' => 'Alta', '3' => 'Crítica'][$r->criticality] ?? $r->criticality }}</td>
                <td class="px-4 py-3 text-on-surface-variant">{{ $r->equipment_count }}</td>
                <td class="px-4 py-3"><x-badge :active="$r->is_active" :demo="$r->is_demo" /></td>
                <td class="px-4 py-3">
                    <x-actions :edit-route="route('admin.rooms.edit', $r)"
                               :toggle-route="route('admin.rooms.toggle', $r)"
                               :destroy-route="route('admin.rooms.destroy', $r)"
                               :active="$r->is_active"
                               :blocked="$guard->canDelete($r) ? null : $guard->explain($r)" />
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-4 py-8 text-center text-on-surface-variant">No hay aulas que coincidan.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $rooms->links() }}</div>
@endsection
