@extends('layouts.app')
@section('title', $item->exists ? 'Editar equipo' : 'Nuevo equipo')
@section('heading', $item->exists ? 'Editar equipo' : 'Nuevo equipo')

@section('content')
<form method="POST" action="{{ $item->exists ? route('admin.equipment.update', $item) : route('admin.equipment.store') }}"
      class="max-w-lg space-y-4 rounded-lg border border-slate-200 bg-white p-6">
    @csrf
    @if ($item->exists) @method('PUT') @endif

    <x-field label="Aula" name="room_id" :required="true">
        <select id="room_id" name="room_id" required class="mt-1 block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
            <option value="">Selecciona…</option>
            @foreach ($rooms as $r)
                <option value="{{ $r->id }}" @selected(old('room_id', $item->room_id) == $r->id)>
                    {{ $r->code }} — {{ $r->floor?->building?->name }}
                </option>
            @endforeach
        </select>
    </x-field>

    <x-field label="Tipo de equipo" name="equipment_type_id" :required="true">
        <select id="equipment_type_id" name="equipment_type_id" required class="mt-1 block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
            <option value="">Selecciona…</option>
            @foreach ($types as $t)
                <option value="{{ $t->id }}" @selected(old('equipment_type_id', $item->equipment_type_id) == $t->id)>{{ $t->name }}</option>
            @endforeach
        </select>
    </x-field>

    <x-field label="Código de inventario" name="asset_code" :required="true">
        <input id="asset_code" name="asset_code" value="{{ old('asset_code', $item->asset_code) }}" required maxlength="60"
               class="mt-1 block w-full rounded-md border-slate-300 px-3 py-2 font-mono text-sm shadow-sm">
    </x-field>

    <div class="grid grid-cols-2 gap-4">
        <x-field label="Marca" name="brand">
            <input id="brand" name="brand" value="{{ old('brand', $item->brand) }}" maxlength="80"
                   class="mt-1 block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm">
        </x-field>

        <x-field label="Modelo" name="model">
            <input id="model" name="model" value="{{ old('model', $item->model) }}" maxlength="80"
                   class="mt-1 block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm">
        </x-field>
    </div>

    <x-field label="Número de serie" name="serial_number"
             help="Opcional. Muchas series vienen vacías o ilegibles, por eso no se exige que sea única.">
        <input id="serial_number" name="serial_number" value="{{ old('serial_number', $item->serial_number) }}" maxlength="120"
               class="mt-1 block w-full rounded-md border-slate-300 px-3 py-2 font-mono text-sm shadow-sm">
    </x-field>

    <div class="grid grid-cols-2 gap-4">
        <x-field label="Estado" name="status" :required="true">
            <select id="status" name="status" required class="mt-1 block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
                @foreach ($statuses as $v => $l)
                    <option value="{{ $v }}" @selected(old('status', $item->status) === $v)>{{ $l }}</option>
                @endforeach
            </select>
        </x-field>

        <x-field label="Fecha de incorporación" name="commissioned_at" help="Alimenta la antigüedad en el modelo de riesgo.">
            <input id="commissioned_at" name="commissioned_at" type="date" max="{{ now()->toDateString() }}"
                   value="{{ old('commissioned_at', $item->commissioned_at?->toDateString()) }}"
                   class="mt-1 block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm">
        </x-field>
    </div>

    <label class="flex items-center gap-2 text-sm text-slate-700">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->exists ? $item->is_active : true)) class="rounded border-slate-300">
        Activo
    </label>

    <div class="flex gap-3 pt-2">
        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Guardar</button>
        <a href="{{ route('admin.equipment.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Cancelar</a>
    </div>
</form>
@endsection
