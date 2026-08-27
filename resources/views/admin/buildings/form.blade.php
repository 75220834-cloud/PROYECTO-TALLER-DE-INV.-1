@extends('layouts.app')
@section('title', $building->exists ? 'Editar pabellón' : 'Nuevo pabellón')
@section('heading', $building->exists ? 'Editar pabellón' : 'Nuevo pabellón')

@section('content')
<form method="POST" action="{{ $building->exists ? route('admin.buildings.update', $building) : route('admin.buildings.store') }}"
      class="max-w-lg space-y-4 rounded-lg border border-slate-200 bg-white p-6">
    @csrf
    @if ($building->exists) @method('PUT') @endif

    <x-field label="Sede" name="site_id" :required="true">
        <select id="site_id" name="site_id" required class="mt-1 block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
            <option value="">Selecciona…</option>
            @foreach ($sites as $s)
                <option value="{{ $s->id }}" @selected(old('site_id', $building->site_id) == $s->id)>{{ $s->name }}</option>
            @endforeach
        </select>
    </x-field>

    <x-field label="Código" name="code" :required="true" help="Único dentro de la sede. Dos sedes pueden tener cada una su pabellón &quot;A&quot;.">
        <input id="code" name="code" value="{{ old('code', $building->code) }}" required maxlength="50"
               class="mt-1 block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm">
    </x-field>

    <x-field label="Nombre" name="name" :required="true">
        <input id="name" name="name" value="{{ old('name', $building->name) }}" required maxlength="150"
               class="mt-1 block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm">
    </x-field>

    <x-field label="Orden de aparición" name="sort_order" help="Controla en qué orden ve el docente los pabellones al elegir.">
        <input id="sort_order" name="sort_order" type="number" min="0" max="9999" value="{{ old('sort_order', $building->sort_order ?? 0) }}"
               class="mt-1 block w-32 rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm">
    </x-field>

    <label class="flex items-center gap-2 text-sm text-slate-700">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $building->exists ? $building->is_active : true)) class="rounded border-slate-300">
        Activo
    </label>

    <div class="flex gap-3 pt-2">
        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Guardar</button>
        <a href="{{ route('admin.buildings.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Cancelar</a>
    </div>
</form>
@endsection
