@extends('layouts.app')
@section('title', 'Sedes')
@section('heading', 'Sedes')

@section('content')
<div class="mb-4 flex justify-end">
    <a href="{{ route('admin.sites.create') }}" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Nueva sede</a>
</div>

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-4 py-3">Código</th>
                <th class="px-4 py-3">Nombre</th>
                <th class="px-4 py-3">Pabellones</th>
                <th class="px-4 py-3">Estado</th>
                <th class="px-4 py-3 text-right">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($sites as $site)
            <tr>
                <td class="px-4 py-3 font-mono text-xs">{{ $site->code }}</td>
                <td class="px-4 py-3">{{ $site->name }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $site->buildings_count }}</td>
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
            <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No hay sedes registradas.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $sites->links() }}</div>
@endsection
