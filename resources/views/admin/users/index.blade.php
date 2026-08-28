@extends('layouts.app')
@section('title', 'Usuarios')
@section('heading', 'Usuarios del panel')
@section('subheading', 'Quién puede entrar y con qué rol')

@section('content')

<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-on-surface-variant">
        Solo el personal interno. El docente no tiene cuenta ni la va a tener: entra por el QR.
    </p>

    <a href="{{ route('admin.users.create') }}"
       class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-on-primary">
        Nuevo usuario
    </a>
</div>

<div class="overflow-x-auto rounded-lg border border-outline-variant bg-surface-lowest">
    <table class="w-full text-sm">
        <thead class="border-b border-outline-variant bg-surface-low text-left text-xs uppercase tracking-wide text-on-surface-variant">
            <tr>
                <th class="px-4 py-3">Nombre</th>
                <th class="px-4 py-3">Correo</th>
                <th class="px-4 py-3">Rol</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($users as $user)
                <tr class="border-b border-surface-mid last:border-0">
                    <td class="px-4 py-3 font-medium">{{ $user->name }}</td>
                    <td class="px-4 py-3 text-on-surface-variant">{{ $user->email }}</td>
                    <td class="px-4 py-3">
                        <span class="rounded bg-surface-mid px-2 py-0.5 text-xs text-on-surface-variant">
                            {{ $user->getRoleNames()->first() ?? 'sin rol' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.users.edit', $user) }}"
                           class="text-on-surface-variant underline underline-offset-2">Editar</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $users->links() }}</div>

{{-- Se explica por qué no hay botón de borrar, en lugar de dejar al
     administrador buscándolo. --}}
<p class="mt-5 rounded-lg border border-outline-variant bg-surface-lowest px-4 py-3 text-sm text-on-surface-variant">
    <strong class="text-on-surface">No se borran usuarios.</strong>
    Sus incidencias atendidas y su rastro en la auditoría tienen que seguir siendo legibles para la
    investigación. Cuando alguien deja el equipo, cámbiale el rol o la contraseña: eso le quita el
    acceso sin abrir huecos en el historial.
</p>
@endsection
