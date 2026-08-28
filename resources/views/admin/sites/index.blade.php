@extends('layouts.app')
@section('title', 'Sedes')
@section('heading', 'Sedes')

@section('content')
<div class="mb-4 flex justify-end">
    <a href="{{ route('admin.sites.create') }}" class="btn-primary focus-ring px-4 py-2 text-sm font-medium hover:bg-on-surface">Nueva sede</a>
</div>

<div class="overflow-hidden glass">
    <table class="min-w-full divide-y divide-outline-variant text-sm">
        <thead class="bg-surface-low text-left label-tech text-on-surface-variant">
            <tr>
                <th class="px-4 py-3">Código</th>
                <th class="px-4 py-3">Nombre</th>
                <th class="px-4 py-3">Pabellones</th>
                <th class="px-4 py-3">Estado</th>
                <th class="px-4 py-3 text-right">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-surface-mid">
        @forelse ($sites as $site)
            <tr>
                <td class="px-4 py-3 font-mono text-xs">{{ $site->code }}</td>
                <td class="px-4 py-3">{{ $site->name }}</td>
                <td class="px-4 py-3 text-on-surface-variant">{{ $site->buildings_count }}</td>
                <td class="px-4 py-3"><x-badge :active="$site->is_active" :demo="$site->is_demo" /></td>
                <td class="px-4 py-3">
                    <x-actions
                        :edit-route="route('admin.sites.edit', $site)"
                        :toggle-route="route('admin.sites.toggle', $site)"
                        :destroy-route="route('admin.sites.destroy', $site)"
                        :active="$site->is_active"
                        :blocked="$guard->canDelete($site) ? null : $guard->explain($site)" />
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-8 text-center text-on-surface-variant">No hay sedes registradas.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $sites->links() }}</div>
@endsection
