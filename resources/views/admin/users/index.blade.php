@extends('layouts.app')
@section('title', 'Usuarios')
@section('heading', 'Usuarios del panel')
@section('subheading', 'Quién puede entrar y con qué rol')

@section('content')

<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-slate-600">
        Solo el personal interno. El docente no tiene cuenta ni la va a tener: entra por el QR.
    </p>

    <a href="{{ route('admin.users.create') }}"
       class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">
        Nuevo usuario
    </a>
</div>

<div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
    <table class="w-full text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-4 py-3">Nombre</th>
                <th class="px-4 py-3">Correo</th>
                <th class="px-4 py-3">Rol</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($users as $user)
                <tr class="border-b border-slate-100 last:border-0">
                    <td class="px-4 py-3 font-medium">{{ $user->name }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $user->email }}</td>
                    <td class="px-4 py-3">
                        <span class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-700">
                            {{ $user->getRoleNames()->first() ?? 'sin rol' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.users.edit', $user) }}"
                           class="text-slate-700 underline underline-offset-2">Editar</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $users->links() }}</div>

{{-- Se explica por qué no hay botón de borrar, en lugar de dejar al
     administrador buscándolo. --}}
<p class="mt-5 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
    <strong class="text-slate-800">No se borran usuarios.</strong>
    Sus incidencias atendidas y su rastro en la auditoría tienen que seguir siendo legibles para la
    investigación. Cuando alguien deja el equipo, cámbiale el rol o la contraseña: eso le quita el
    acceso sin abrir huecos en el historial.
</p>
@endsection
