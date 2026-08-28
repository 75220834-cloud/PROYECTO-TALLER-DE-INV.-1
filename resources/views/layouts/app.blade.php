<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#141316">
    <title>@yield('title', 'Panel') · {{ config('app.name') }}</title>

    {{-- theme-init va PRIMERO: decide el tema antes de pintar y evita el
         fogonazo blanco al cargar en oscuro. --}}
    @vite(['resources/js/theme-init.js', 'resources/css/app.css', 'resources/js/app.js'])

    {{-- Fuentes locales, no de Google: el plan exige que el sistema funcione
         con la salida a Internet bloqueada (OS-9). --}}
    <link rel="stylesheet" href="{{ asset('fonts/fuentes.css') }}">
</head>
<body class="h-full bg-transparent text-on-surface antialiased">

@php
    $demoCount = \App\Modules\Locations\Models\Site::where('is_demo', true)->count();
@endphp

@if ($demoCount > 0)
    {{-- Distintivo exigido por el plan 21: mientras existan datos demo, tiene
         que ser imposible confundirlos con informacion institucional real. --}}
    <div class="label-tech bg-warn px-4 py-1.5 text-center text-on-warn-container">
        DATOS DE DEMOSTRACIÓN — este sistema no contiene información institucional real
    </div>
@endif

<div class="flex min-h-full">

    <aside class="hidden w-64 shrink-0 flex-col border-r border-[var(--glass-stroke)] bg-[var(--glass-bg)] backdrop-blur-xl md:flex">
        <div class="flex items-center gap-3 px-5 py-5">
            <x-brand-mark :size="34" />
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold leading-tight text-on-surface">Incidencias en aulas</p>
                <p class="label-tech mt-0.5 text-on-surface-variant">Soporte</p>
            </div>
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
                <p class="label-tech px-3 pb-1 pt-4 text-on-surface-variant">Catálogo</p>
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

            @can('locations.manage')
                <x-nav-link :href="route('admin.categories.index')" :active="request()->routeIs('admin.categories.*')">
                    Tipos de problema
                </x-nav-link>
            @endcan

            @can('equipment.view')
                <x-nav-link :href="route('admin.equipment.index')" :active="request()->routeIs('admin.equipment.*')">
                    Equipos
                </x-nav-link>
            @endcan

            @can('knowledge.view')
                <p class="label-tech px-3 pb-1 pt-4 text-on-surface-variant">Asistente</p>
                <x-nav-link :href="route('admin.knowledge.index')" :active="request()->routeIs('admin.knowledge.*')">
                    Conocimiento
                </x-nav-link>
            @endcan

            @can('media.manage')
                <x-nav-link :href="route('admin.media.index')" :active="request()->routeIs('admin.media.*')">
                    Banco de imágenes
                </x-nav-link>
            @endcan

            @can('diagnostics.manage')
                <x-nav-link :href="route('admin.flows.index')" :active="request()->routeIs('admin.flows.*')">
                    Procedimientos
                </x-nav-link>
            @endcan

            @can('locations.manage')
                <p class="label-tech px-3 pb-1 pt-4 text-on-surface-variant">Acceso</p>
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

        <div class="border-t border-[var(--glass-stroke)] px-5 py-4 text-xs">
            <p class="font-medium text-on-surface">{{ auth()->user()?->name }}</p>
            <p class="label-tech mt-0.5 text-on-surface-variant">{{ auth()->user()?->getRoleNames()->first() }}</p>
            <form method="POST" action="{{ route('logout') }}" class="mt-2">
                @csrf
                <button type="submit" class="focus-ring mt-2 text-on-surface-variant underline-offset-2 hover:text-on-surface hover:underline">
                    Cerrar sesión
                </button>
            </form>
        </div>
    </aside>

    <main class="min-w-0 flex-1">
        <header class="sticky top-0 z-10 flex items-center justify-between gap-4 border-b border-[var(--glass-stroke)] bg-[var(--glass-bg)] px-6 py-4 backdrop-blur-xl">
            <div class="min-w-0">
                {{-- El título de la página va en el color del texto normal,
                     no en el de marca: en morado compite con los botones de
                     acción, que son lo que de verdad debe destacar. --}}
                <h1 class="text-lg font-semibold text-on-surface">@yield('heading', 'Panel')</h1>
                @hasSection('subheading')
                    <p class="mt-0.5 text-sm text-on-surface-variant">@yield('subheading')</p>
                @endif
            </div>

            <div class="flex shrink-0 items-center gap-1">
                @can('incidents.view')
                    {{-- El permiso se pide con un botón y nunca solo al cargar:
                         un navegador que pregunta sin que nadie lo haya pedido
                         recibe un «no» automático difícil de revertir. --}}
                    <button type="button" data-alertas-permiso
                                class="touch-target flex items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-high"
                            title="Activar avisos del sistema"
                            aria-label="Activar avisos del sistema">
                        <span class="material-symbols-outlined text-[22px]" aria-hidden="true">notifications</span>
                    </button>

                    {{-- Sin esta opción, quien comparte oficina acaba
                         silenciando la pestaña entera — y con ella el aviso
                         que sí importa. --}}
                    <button type="button" data-alertas-silenciar
                            class="touch-target flex items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-high"
                            title="Silenciar el sonido de los avisos"
                            aria-label="Silenciar el sonido de los avisos">
                        <span class="material-symbols-outlined text-[22px]" aria-hidden="true">volume_up</span>
                    </button>
                @endcan

                <button type="button" data-theme-toggle
                        class="touch-target flex items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-high"
                        aria-label="Cambiar entre modo claro y oscuro">
                    <span class="material-symbols-outlined text-[22px]" aria-hidden="true">contrast</span>
                </button>
            </div>
        </header>

        {{-- AVISO DE INCIDENCIAS NUEVAS (plan 12).
             Va fijo arriba y no como un mensaje que se pierde al hacer
             scroll: el técnico puede estar en cualquier pantalla del panel
             cuando entra una incidencia, y si el aviso solo estuviera en la
             bandeja no serviría de nada. --}}
        @can('incidents.view')
            <div data-alertas="{{ route('support.alerts') }}"
                 data-alertas-desde="{{ now()->toIso8601String() }}"
                 hidden
                 class="sticky top-0 z-20 flex flex-wrap items-center gap-3 border-b-2 px-6 py-3
                        data-[urgente=1]:border-danger data-[urgente=1]:bg-danger-container
                        data-[urgente=0]:border-primary data-[urgente=0]:bg-primary-container">

                <span class="material-symbols-outlined text-[22px]" aria-hidden="true">notifications_active</span>

                <p class="flex-1 text-sm font-medium" data-alertas-texto>Nueva incidencia</p>

                <a data-alertas-enlace href="{{ route('support.incidents.index') }}"
                   data-bandeja="{{ route('support.incidents.index') }}"
                   class="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-on-primary">
                    Ver
                </a>

                <button type="button" data-alertas-cerrar
                        class="touch-target flex items-center justify-center rounded-full"
                        aria-label="Descartar aviso">
                    <span class="material-symbols-outlined text-[20px]" aria-hidden="true">close</span>
                </button>
            </div>
        @endcan

        <div class="page-enter p-6">
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
