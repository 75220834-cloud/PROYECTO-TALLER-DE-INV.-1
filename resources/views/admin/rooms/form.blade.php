@extends('layouts.app')
@section('title', $room->exists ? 'Editar aula' : 'Nueva aula')
@section('heading', $room->exists ? 'Editar aula' : 'Nueva aula')

@section('content')
<form method="POST" action="{{ $room->exists ? route('admin.rooms.update', $room) : route('admin.rooms.store') }}"
      class="max-w-lg space-y-4 glass p-6">
    @csrf
    @if ($room->exists) @method('PUT') @endif

    <x-field label="Piso" name="floor_id" :required="true">
        <select id="floor_id" name="floor_id" required class="mt-1 block w-full rounded-md border-outline-variant px-3 py-2 text-sm">
            <option value="">Selecciona…</option>
            @foreach ($floors as $f)
                <option value="{{ $f->id }}" @selected(old('floor_id', $room->floor_id) == $f->id)>
                    {{ $f->building?->site?->name }} · {{ $f->building?->name }} · {{ $f->label }}
                </option>
            @endforeach
        </select>
    </x-field>

    <x-field label="Código del aula" name="code" :required="true"
             help="Escribe el código REAL tal como está en el aula. No se calcula a partir del pabellón y el piso: así conviven &quot;C305&quot; y &quot;LAB-2&quot; sin problema.">
        <input id="code" name="code" value="{{ old('code', $room->code) }}" required maxlength="50"
               class="mt-1 block w-full rounded-md border-outline-variant px-3 py-2 font-mono text-sm shadow-sm">
    </x-field>

    <x-field label="Nombre" name="name">
        <input id="name" name="name" value="{{ old('name', $room->name) }}" maxlength="150"
               class="mt-1 block w-full rounded-md border-outline-variant px-3 py-2 text-sm shadow-sm">
    </x-field>

    <div class="grid grid-cols-2 gap-4">
        <x-field label="Capacidad" name="capacity">
            <input id="capacity" name="capacity" type="number" min="1" max="999" value="{{ old('capacity', $room->capacity) }}"
                   class="mt-1 block w-full rounded-md border-outline-variant px-3 py-2 text-sm shadow-sm">
        </x-field>

        <x-field label="Criticidad" name="criticality" :required="true" help="Influye en la prioridad del ticket.">
            <select id="criticality" name="criticality" required class="mt-1 block w-full rounded-md border-outline-variant px-3 py-2 text-sm">
                @foreach (['1' => 'Normal', '2' => 'Alta', '3' => 'Crítica'] as $v => $l)
                    <option value="{{ $v }}" @selected(old('criticality', $room->criticality ?? 1) == $v)>{{ $l }}</option>
                @endforeach
            </select>
        </x-field>
    </div>

    <label class="flex items-center gap-2 text-sm text-on-surface-variant">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $room->exists ? $room->is_active : true)) class="rounded border-outline-variant">
        Activa (acepta reportes)
    </label>

    <div class="flex gap-3 pt-2">
        <button type="submit" class="btn-primary focus-ring px-4 py-2 text-sm font-medium hover:bg-on-surface">Guardar</button>
        <a href="{{ route('admin.rooms.index') }}" class="rounded-md border border-outline-variant px-4 py-2 text-sm text-on-surface-variant hover:bg-surface-low">Cancelar</a>
    </div>
</form>
@endsection
