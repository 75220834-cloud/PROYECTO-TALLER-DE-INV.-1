@extends('layouts.app')
@section('title', $category->exists ? 'Editar categoría' : 'Nueva categoría')
@section('heading', $category->exists ? 'Editar «' . $category->name . '»' : 'Nueva categoría')

@section('content')
<form method="POST" class="max-w-2xl space-y-5"
      action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}">
    @csrf
    @if ($category->exists) @method('PUT') @endif

    @if ($errors->any())
        <div class="rounded-md border border-danger bg-danger-container px-4 py-3 text-sm text-on-danger-container">
            <ul class="space-y-1">
                @foreach ($errors->all() as $error)<li>· {{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="space-y-4 rounded-lg border border-outline-variant bg-surface-lowest p-5">
        <div>
            <label class="block text-sm font-medium text-on-surface-variant">Código interno</label>

            @if ($category->exists)
                {{-- No se ofrece editarlo, y se dice por qué. Cambiarlo dejaría
                     huérfanos los tickets ya cerrados que el análisis va a leer. --}}
                <p class="mt-1 font-mono text-sm">{{ $category->code }}</p>
                <p class="mt-1 text-xs text-on-surface-variant">
                    No se puede cambiar: lo usan la lógica del sistema y las incidencias ya
                    registradas. Para cambiar lo que se ve, edita el nombre.
                </p>
            @else
                <input type="text" name="code" value="{{ old('code') }}" required
                       placeholder="PROJECTOR"
                       class="mt-1 block w-full rounded-md border-outline-variant font-mono text-sm">
                <p class="mt-1 text-xs text-on-surface-variant">
                    MAYÚSCULAS, números y guion bajo. <strong>No podrá cambiarse después.</strong>
                </p>
            @endif
        </div>

        <div>
            <label class="block text-sm font-medium text-on-surface-variant">Nombre interno</label>
            <input type="text" name="name" value="{{ old('name', $category->name) }}" required
                   placeholder="Proyector"
                   class="mt-1 block w-full rounded-md border-outline-variant text-sm">
            <p class="mt-1 text-xs text-on-surface-variant">El que ve soporte en la bandeja y en los reportes.</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-on-surface-variant">Lo que lee el docente</label>
            <input type="text" name="teacher_label" value="{{ old('teacher_label', $category->teacher_label) }}"
                   placeholder="No se ve la imagen"
                   class="mt-1 block w-full rounded-md border-outline-variant text-sm">
            <p class="mt-1 text-xs text-on-surface-variant">
                Descríbelo por el <strong>síntoma</strong>, no por el equipo: «No se ve la imagen»
                funciona para quien no sabe si el problema es del proyector o del cable. Si lo dejas
                vacío se usa el nombre interno.
            </p>
        </div>
    </div>

    <div class="grid gap-4 rounded-lg border border-outline-variant bg-surface-lowest p-5 sm:grid-cols-2">
        <div>
            <label class="block text-sm font-medium text-on-surface-variant">Prioridad base</label>
            <select name="default_priority_id" class="mt-1 block w-full rounded-md border-outline-variant text-sm">
                <option value="">Media (por defecto)</option>
                @foreach ($priorities as $priority)
                    <option value="{{ $priority->id }}" @selected(old('default_priority_id', $category->default_priority_id) == $priority->id)>
                        {{ $priority->name }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-on-surface-variant">
                Punto de partida. El sistema la sube si la clase está detenida, si el aula es crítica
                o si el problema se repite.
            </p>
        </div>

        <div>
            <label class="block text-sm font-medium text-on-surface-variant">Orden en la pantalla</label>
            <input type="number" name="sort_order" min="0" max="999"
                   value="{{ old('sort_order', $category->sort_order ?? 0) }}"
                   class="mt-1 block w-full rounded-md border-outline-variant text-sm">
            <p class="mt-1 text-xs text-on-surface-variant">Menor número, más arriba.</p>
        </div>

        <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-on-surface-variant">Imagen representativa</label>
            <select name="media_asset_id" class="mt-1 block w-full rounded-md border-outline-variant text-sm">
                <option value="">Sin imagen</option>
                @foreach ($assets as $asset)
                    <option value="{{ $asset->id }}" @selected(old('media_asset_id', $category->media_asset_id) == $asset->id)>
                        {{ $asset->code }} — {{ $asset->title }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-on-surface-variant">
                Ayuda a reconocer la opción sin leer, en la pantalla más usada del sistema.
            </p>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="rounded-md bg-primary px-5 py-2.5 text-sm font-medium text-on-primary">
            {{ $category->exists ? 'Guardar cambios' : 'Crear categoría' }}
        </button>
        <a href="{{ route('admin.categories.index') }}" class="text-sm text-on-surface-variant underline underline-offset-2">Cancelar</a>
    </div>
</form>
@endsection
