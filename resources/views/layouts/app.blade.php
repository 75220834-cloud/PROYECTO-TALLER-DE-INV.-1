<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#fdfbff">
    <title>@yield('title', 'Panel') · {{ config('app.name') }}</title>

    {{-- theme-init va PRIMERO: decide el tema antes de pintar y evita el
         fogonazo blanco al cargar en oscuro. --}}
    @vite(['resources/js/theme-init.js', 'resources/css/app.css', 'resources/js/app.js'])

    {{-- Fuentes locales, no de Google: el plan exige que el sistema funcione
         con la salida a Internet bloqueada (OS-9). --}}
    <link rel="stylesheet" href="{{ asset('fonts/fuentes.css') }}">
</head>
<body class="h-full bg-surface-mid text-on-surface antialiased transition-colors">

@php
    $demoCount = \App\Modules\Locations\Models\Site::where('is_demo', true)->count();
@endphp

@if ($demoCount > 0)
    {{-- Distintivo exigido por el plan 21: mientras existan datos demo, tiene
         que ser imposible confundirlos con informacion institucional real. --}}
    <div class="bg-warn px-4 py-1.5 text-center text-xs font-semibold tracking-wide text-on-warn-container">
        DATOS DE DEMOSTRACIÓN — este sistema no contiene información institucional real
    </div>
@endif

<div class="flex min-h-full">

    <aside class="hidden w-60 shrink-0 flex-col bg-primary text-outline-variant md:flex">
        <div class="px-5 py-5">
            <p class="text-sm font-semibold leading-tight text-surface-lowest">Incidencias en aulas</p>
            <p class="mt-0.5 text-xs text-outline">Panel de soporte</p>
        </div>

        <nav class="flex-1 space-y-0.5 px-3 py-2 text-sm">
            {{-- El enlace se condiciona al mismo permiso que exige la ruta.
                 Mostrar un enlace que lleva a un 403 no es un detalle
                 estético: hace pensar al técnico que algo se rompió. --}}
            @can('dashboard.view')
                <x-nav-link :href="route('support.dashboard')" :active="request()->routeIs('support.dashboard')">
                    Tablero
                </x-nav-link>
            @endcan

            @can('risk.view')
                <x-nav-link :href="route('support.risk.index')" :active="request()->routeIs('support.risk.*')">
                    Señales de riesgo
                </x-nav-link>
            @endcan

            @can('abuse.view')
                <x-nav-link :href="route('admin.audit.rejections')" :active="request()->routeIs('admin.audit.rejections')">
                    Solicitudes rechazadas
                </x-nav-link>
            @endcan

            @can('incidents.view')
                <x-nav-link :href="route('support.incidents.index')" :active="request()->routeIs('support.incidents.*')">
                    Incidencias
                </x-nav-link>
            @endcan
            @can('locations.view')
                <p class="px-3 pb-1 pt-4 text-[11px] font-semibold uppercase tracking-wider text-on-surface-variant">Catálogo</p>
                <x-nav-link :href="route('admin.sites.index')" :active="request()->routeIs('admin.sites.*')">
                    Sedes
                </x-nav-link>
                <x-nav-link :href="route('admin.buildings.index')" :active="request()->routeIs('admin.buildings.*')">
                    Pabellones
                </x-nav-link>
                <x-nav-link :href="route('admin.floors.index')" :active="request()->routeIs('admin.floors.*')">
                    Pisos
                </x-nav-link>
                <x-nav-link :href="route('admin.rooms.index')" :active="request()->routeIs('admin.rooms.*')">
                    Aulas
                </x-nav-link>
            @endcan

            @can('equipment.view')
                <x-nav-link :href="route('admin.equipment.index')" :active="request()->routeIs('admin.equipment.*')">
                    Equipos
                </x-nav-link>
            @endcan

            @can('knowledge.view')
                <p class="px-3 pb-1 pt-4 text-[11px] font-semibold uppercase tracking-wider text-on-surface-variant">Asistente</p>
                <x-nav-link :href="route('admin.knowledge.index')" :active="request()->routeIs('admin.knowledge.*')">
                    Conocimiento
                </x-nav-link>
            @endcan

            @can('media.manage')
                <x-nav-link :href="route('admin.media.index')" :active="request()->routeIs('admin.media.*')">
                    Banco de imágenes
                </x-nav-link>
            @endcan

            @can('locations.manage')
                <p class="px-3 pb-1 pt-4 text-[11px] font-semibold uppercase tracking-wider text-on-surface-variant">Acceso</p>
                <x-nav-link :href="route('admin.qr.show')" :active="request()->routeIs('admin.qr.*')">
                    Código QR
                </x-nav-link>
            @endcan

            @can('users.manage')
                <x-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                    Usuarios
                </x-nav-link>
            @endcan

            @can('audit.view')
                <x-nav-link :href="route('admin.audit.index')" :active="request()->routeIs('admin.audit.index')">
                    Auditoría
                </x-nav-link>
            @endcan
        </nav>

        <div class="border-t border-on-surface px-5 py-4 text-xs">
            <p class="font-medium text-outline-variant">{{ auth()->user()?->name }}</p>
            <p class="text-on-surface-variant">{{ auth()->user()?->getRoleNames()->first() }}</p>
            <form method="POST" action="{{ route('logout') }}" class="mt-2">
                @csrf
                <button type="submit" class="text-outline underline-offset-2 hover:text-surface-lowest hover:underline">
                    Cerrar sesión
                </button>
            </form>
        </div>
    </aside>

    <main class="min-w-0 flex-1">
        <header class="flex items-center justify-between gap-4 border-b border-outline-variant bg-surface-lowest px-6 py-4">
            <div class="min-w-0">
                {{-- El título de la página va en el color del texto normal,
                     no en el de marca: en morado compite con los botones de
                     acción, que son lo que de verdad debe destacar. --}}
                <h1 class="text-lg font-semibold text-on-surface">@yield('heading', 'Panel')</h1>
                @hasSection('subheading')
                    <p class="mt-0.5 text-sm text-on-surface-variant">@yield('subheading')</p>
                @endif
            </div>

            <button type="button" data-theme-toggle
                    class="touch-target flex shrink-0 items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-high"
                    aria-label="Cambiar entre modo claro y oscuro">
                <span class="material-symbols-outlined text-[22px]" aria-hidden="true">contrast</span>
            </button>
        </header>

        <div class="p-6">
            @if (session('status'))
                <div class="mb-4 rounded-md border border-ok bg-ok-container px-4 py-3 text-sm text-on-ok-container">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-4 rounded-md border border-danger bg-danger-container px-4 py-3 text-sm text-on-danger-container">
                    {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </div>
    </main>
</div>

</body>
</html>
