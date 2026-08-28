@extends('layouts.app')
@section('title', $floor->exists ? 'Editar piso' : 'Nuevo piso')
@section('heading', $floor->exists ? 'Editar piso' : 'Nuevo piso')

@section('content')
<form method="POST" action="{{ $floor->exists ? route('admin.floors.update', $floor) : route('admin.floors.store') }}"
      class="max-w-lg space-y-4 rounded-lg border border-outline-variant bg-surface-lowest p-6">
    @csrf
    @if ($floor->exists) @method('PUT') @endif

    <x-field label="Pabellón" name="building_id" :required="true">
        <select id="building_id" name="building_id" required class="mt-1 block w-full rounded-md border-outline-variant px-3 py-2 text-sm">
            <option value="">Selecciona…</option>
            @foreach ($buildings as $b)
                <option value="{{ $b->id }}" @selected(old('building_id', $floor->building_id) == $b->id)>{{ $b->site?->name }} · {{ $b->name }}</option>
            @endforeach
        </select>
    </x-field>

    <x-field label="Número" name="number" :required="true" help="Admite negativos para sótanos (por ejemplo, -1).">
        <input id="number" name="number" type="number" min="-5" max="99" value="{{ old('number', $floor->number) }}" required
               class="mt-1 block w-32 rounded-md border-outline-variant px-3 py-2 text-sm shadow-sm">
    </x-field>

    <x-field label="Etiqueta" name="label" :required="true" help="Lo que ve el docente. No todo piso se nombra con su número: &quot;Semisótano&quot;, &quot;Mezanine&quot;.">
        <input id="label" name="label" value="{{ old('label', $floor->label) }}" required maxlength="50"
               class="mt-1 block w-full rounded-md border-outline-variant px-3 py-2 text-sm shadow-sm">
    </x-field>

    <label class="flex items-center gap-2 text-sm text-on-surface-variant">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $floor->exists ? $floor->is_active : true)) class="rounded border-outline-variant">
        Activo
    </label>

    <div class="flex gap-3 pt-2">
        <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-on-primary hover:bg-on-surface">Guardar</button>
        <a href="{{ route('admin.floors.index') }}" class="rounded-md border border-outline-variant px-4 py-2 text-sm text-on-surface-variant hover:bg-surface-low">Cancelar</a>
    </div>
</form>
@endsection
