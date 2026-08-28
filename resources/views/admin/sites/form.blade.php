@extends('layouts.app')
@section('title', $site->exists ? 'Editar sede' : 'Nueva sede')
@section('heading', $site->exists ? 'Editar sede' : 'Nueva sede')

@section('content')
<form method="POST" action="{{ $site->exists ? route('admin.sites.update', $site) : route('admin.sites.store') }}"
      class="max-w-lg space-y-4 glass p-6">
    @csrf
    @if ($site->exists) @method('PUT') @endif

    <x-field label="Código" name="code" :required="true" help="Identificador estable. La lógica del sistema compara por este valor, así que evita cambiarlo una vez en uso.">
        <input id="code" name="code" value="{{ old('code', $site->code) }}" required maxlength="50"
               class="mt-1 block w-full rounded-md border-outline-variant px-3 py-2 text-sm shadow-sm">
    </x-field>

    <x-field label="Nombre" name="name" :required="true">
        <input id="name" name="name" value="{{ old('name', $site->name) }}" required maxlength="150"
               class="mt-1 block w-full rounded-md border-outline-variant px-3 py-2 text-sm shadow-sm">
    </x-field>

    <label class="flex items-center gap-2 text-sm text-on-surface-variant">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $site->exists ? $site->is_active : true))
               class="rounded border-outline-variant">
        Activa
    </label>

    <div class="flex gap-3 pt-2">
        <button type="submit" class="btn-primary focus-ring px-4 py-2 text-sm font-medium hover:bg-on-surface">Guardar</button>
        <a href="{{ route('admin.sites.index') }}" class="rounded-md border border-outline-variant px-4 py-2 text-sm text-on-surface-variant hover:bg-surface-low">Cancelar</a>
    </div>
</form>
@endsection
