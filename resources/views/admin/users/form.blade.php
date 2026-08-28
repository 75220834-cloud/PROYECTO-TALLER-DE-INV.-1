@extends('layouts.app')
@section('title', $user->exists ? 'Editar usuario' : 'Nuevo usuario')
@section('heading', $user->exists ? 'Editar «' . $user->name . '»' : 'Nuevo usuario')

@section('content')
<form method="POST" class="max-w-xl space-y-5"
      action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">
    @csrf
    @if ($user->exists) @method('PUT') @endif

    @if ($errors->any())
        <div class="rounded-md border border-danger bg-danger-container px-4 py-3 text-sm text-on-danger-container">
            <ul class="space-y-1">
                @foreach ($errors->all() as $error)<li>· {{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="space-y-4 glass p-5">
        <div>
            <label class="block text-sm font-medium text-on-surface-variant">Nombre</label>
            <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                   class="mt-1 block w-full rounded-md border-outline-variant text-sm">
        </div>

        <div>
            <label class="block text-sm font-medium text-on-surface-variant">Correo</label>
            <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                   class="mt-1 block w-full rounded-md border-outline-variant text-sm">
        </div>
    </div>

    <div class="space-y-4 glass p-5">
        <div>
            <label class="block text-sm font-medium text-on-surface-variant">
                Contraseña {{ $user->exists ? '(dejar vacío para no cambiarla)' : '' }}
            </label>
            <input type="password" name="password" autocomplete="new-password" {{ $user->exists ? '' : 'required' }}
                   class="mt-1 block w-full rounded-md border-outline-variant text-sm">
            <p class="mt-1 text-xs text-on-surface-variant">Mínimo 10 caracteres.</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-on-surface-variant">Repetir contraseña</label>
            <input type="password" name="password_confirmation" autocomplete="new-password"
                   class="mt-1 block w-full rounded-md border-outline-variant text-sm">
        </div>
    </div>

    <div class="glass p-5">
        <label class="block text-sm font-medium text-on-surface-variant">Rol</label>

        {{-- Se explica qué hace cada rol: elegir mal aquí da a alguien acceso
             a cambiar el catálogo, y un cambio en el catálogo afecta a TODOS
             los tickets, incluidos los cerrados que la investigación va a
             analizar. --}}
        <div class="mt-3 space-y-2">
            @php
                $descripciones = [
                    'admin' => 'Todo, incluidos usuarios y catálogo.',
                    'coordinator' => 'Atiende y reasigna incidencias, ve tablero y riesgo.',
                    'technician' => 'Atiende incidencias. No toca el catálogo.',
                    'knowledge_manager' => 'Carga procedimientos e imágenes. No atiende tickets.',
                    'researcher' => 'Solo lectura y exportación de datos. No modifica nada.',
                ];
            @endphp

            @foreach ($roles as $role)
                <label class="flex cursor-pointer items-start gap-3 rounded-md border border-outline-variant px-3 py-2 hover:bg-surface-low">
                    <input type="radio" name="role" value="{{ $role }}" class="mt-1"
                           @checked(old('role', $user->getRoleNames()->first()) === $role)>
                    <span>
                        <span class="block text-sm font-medium text-on-surface">{{ $role }}</span>
                        <span class="block text-xs text-on-surface-variant">{{ $descripciones[$role] ?? 'Rol personalizado.' }}</span>
                    </span>
                </label>
            @endforeach
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="btn-primary focus-ring px-5 py-2.5 text-sm font-medium">
            {{ $user->exists ? 'Guardar cambios' : 'Crear usuario' }}
        </button>
        <a href="{{ route('admin.users.index') }}" class="text-sm text-on-surface-variant underline underline-offset-2">Cancelar</a>
    </div>
</form>
@endsection
