<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-100 text-slate-800 antialiased">

@php
    $demoCount = \App\Modules\Locations\Models\Site::where('is_demo', true)->count();
@endphp

@if ($demoCount > 0)
    {{-- Distintivo exigido por el plan 21: mientras existan datos demo, tiene
         que ser imposible confundirlos con informacion institucional real. --}}
    <div class="bg-amber-400 px-4 py-1.5 text-center text-xs font-semibold tracking-wide text-amber-950">
        DATOS DE DEMOSTRACIÓN — este sistema no contiene información institucional real
    </div>
@endif

<div class="flex min-h-full">

    <aside class="hidden w-60 shrink-0 flex-col bg-slate-900 text-slate-300 md:flex">
        <div class="px-5 py-5">
            <p class="text-sm font-semibold leading-tight text-white">Incidencias en aulas</p>
            <p class="mt-0.5 text-xs text-slate-400">Panel de soporte</p>
        </div>

        <nav class="flex-1 space-y-0.5 px-3 py-2 text-sm">
            <x-nav-link :href="route('support.dashboard')" :active="request()->routeIs('support.dashboard')">
                Tablero
            </x-nav-link>

            @can('incidents.view')
                <x-nav-link :href="route('support.incidents.index')" :active="request()->routeIs('support.incidents.*')">
                    Incidencias
                </x-nav-link>
            @endcan
            @can('locations.view')
                <p class="px-3 pb-1 pt-4 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Catálogo</p>
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

            @can('locations.manage')
                <p class="px-3 pb-1 pt-4 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Acceso</p>
                <x-nav-link :href="route('admin.qr.show')" :active="request()->routeIs('admin.qr.*')">
                    Código QR
                </x-nav-link>
            @endcan
        </nav>

        <div class="border-t border-slate-800 px-5 py-4 text-xs">
            <p class="font-medium text-slate-200">{{ auth()->user()?->name }}</p>
            <p class="text-slate-500">{{ auth()->user()?->getRoleNames()->first() }}</p>
            <form method="POST" action="{{ route('logout') }}" class="mt-2">
                @csrf
                <button type="submit" class="text-slate-400 underline-offset-2 hover:text-white hover:underline">
                    Cerrar sesión
                </button>
            </form>
        </div>
    </aside>

    <main class="min-w-0 flex-1">
        <header class="border-b border-slate-200 bg-white px-6 py-4">
            <h1 class="text-lg font-semibold text-slate-900">@yield('heading', 'Panel')</h1>
            @hasSection('subheading')
                <p class="mt-0.5 text-sm text-slate-500">@yield('subheading')</p>
            @endif
        </header>

        <div class="p-6">
            @if (session('status'))
                <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </div>
    </main>
</div>

</body>
</html>
