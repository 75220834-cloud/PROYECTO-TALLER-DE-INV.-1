@extends('layouts.app')
@section('title', 'Pisos')
@section('heading', 'Pisos')

@section('content')
<div class="mb-4 flex items-center justify-between gap-3">
    <form method="GET">
        <select name="building_id" data-auto-submit class="rounded-md border-outline-variant px-3 py-2 text-sm">
            <option value="">Todos los pabellones</option>
            @foreach ($buildings as $b)
                <option value="{{ $b->id }}" @selected(request('building_id') == $b->id)>{{ $b->site?->name }} · {{ $b->name }}</option>
            @endforeach
        </select>
    </form>
    <a href="{{ route('admin.floors.create') }}" class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-on-primary hover:bg-on-surface">Nuevo piso</a>
</div>

<div class="overflow-hidden rounded-lg border border-outline-variant bg-surface-lowest">
    <table class="min-w-full divide-y divide-outline-variant text-sm">
        <thead class="bg-surface-low text-left text-xs uppercase tracking-wide text-on-surface-variant">
            <tr><th class="px-4 py-3">Nº</th><th class="px-4 py-3">Etiqueta</th><th class="px-4 py-3">Pabellón</th>
                <th class="px-4 py-3">Aulas</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3 text-right">Acciones</th></tr>
        </thead>
        <tbody class="divide-y divide-surface-mid">
        @forelse ($floors as $f)
            <tr>
                <td class="px-4 py-3 font-mono text-xs">{{ $f->number }}</td>
                <td class="px-4 py-3">{{ $f->label }}</td>
                <td class="px-4 py-3 text-on-surface-variant">{{ $f->building?->name }}</td>
                <td class="px-4 py-3 text-on-surface-variant">{{ $f->rooms_count }}</td>
                <td class="px-4 py-3"><x-badge :active="$f->is_active" :demo="$f->is_demo" /></td>
                <td class="px-4 py-3">
                    <x-actions :edit-route="route('admin.floors.edit', $f)"
                               :toggle-route="route('admin.floors.toggle', $f)"
                               :destroy-route="route('admin.floors.destroy', $f)"
                               :active="$f->is_active"
                               :blocked="$guard->canDelete($f) ? null : $guard->explain($f)" />
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-8 text-center text-on-surface-variant">No hay pisos.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $floors->links() }}</div>
@endsection
