@extends('layouts.app')
@section('title', 'Pabellones')
@section('heading', 'Pabellones')

@section('content')
<div class="mb-4 flex items-center justify-between gap-3">
    <form method="GET" class="flex items-center gap-2">
        <select name="site_id" data-auto-submit class="rounded-md border-slate-300 px-3 py-2 text-sm">
            <option value="">Todas las sedes</option>
            @foreach ($sites as $s)
                <option value="{{ $s->id }}" @selected(request('site_id') == $s->id)>{{ $s->name }}</option>
            @endforeach
        </select>
    </form>
    <a href="{{ route('admin.buildings.create') }}" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Nuevo pabellón</a>
</div>

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-4 py-3">Código</th><th class="px-4 py-3">Nombre</th>
                <th class="px-4 py-3">Sede</th><th class="px-4 py-3">Pisos</th>
                <th class="px-4 py-3">Estado</th><th class="px-4 py-3 text-right">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($buildings as $b)
            <tr>
                <td class="px-4 py-3 font-mono text-xs">{{ $b->code }}</td>
                <td class="px-4 py-3">{{ $b->name }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $b->site?->name }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $b->floors_count }}</td>
                <td class="px-4 py-3"><x-badge :active="$b->is_active" :demo="$b->is_demo" /></td>
                <td class="px-4 py-3">
                    <x-actions :edit-route="route('admin.buildings.edit', $b)"
                               :toggle-route="route('admin.buildings.toggle', $b)"
                               :destroy-route="route('admin.buildings.destroy', $b)"
                               :active="$b->is_active"
                               :blocked="$guard->canDelete($b) ? null : $guard->explain($b)" />
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No hay pabellones.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $buildings->links() }}</div>
@endsection
