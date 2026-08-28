<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    {{-- maximum-scale NO se limita: bloquear el zoom en una pantalla que
         usarán docentes de todas las edades es una barrera de accesibilidad
         real, no un detalle de estilo (plan §8). --}}
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#141316">
    <title>@yield('title', 'Reportar un problema')</title>

    {{-- ANTES que la hoja de estilos, y como archivo propio en vez de en
         línea: decide el tema antes de pintar. Si fuera al final, la página
         aparecería un instante en blanco antes de saltar a oscuro, y ese
         fogonazo en un aula a media luz molesta de verdad. --}}
    @vite(['resources/js/theme-init.js', 'resources/css/app.css', 'resources/js/app.js'])

    {{-- Fuentes servidas desde el propio servidor, NO desde Google.
         El plan exige que el sistema funcione con la salida a Internet
         bloqueada (OS-9): una fuente remota dejaría la interfaz del docente
         con la tipografía del sistema en la red institucional, y además
         obligaría a abrir la CSP hacia dos dominios de terceros. --}}
    <link rel="stylesheet" href="{{ asset('fonts/fuentes.css') }}">
</head>
<body class="flex min-h-full flex-col bg-transparent text-on-surface antialiased">

<header class="sticky top-0 z-10 border-b border-[var(--glass-stroke)] bg-[var(--glass-bg)] backdrop-blur-xl">
    <div class="mx-auto flex w-full max-w-md items-center gap-3 px-5 py-3">

        {{-- Identificador institucional. El archivo lo sube el equipo desde
             el panel; mientras no exista se muestra un distintivo neutro, no
             un logo inventado. --}}
        <x-brand-mark class="shrink-0" />

        <div class="min-w-0 flex-1 text-center">
            <p class="label-tech text-on-surface-variant">Soporte de aulas</p>
            @hasSection('step')
                <p class="mt-0.5 truncate text-sm font-medium text-on-surface">@yield('step')</p>
            @endif
        </div>

        {{-- El conmutador va en la cabecera de TODAS las pantallas, no
             escondido en un menú: un docente que no ve bien la pantalla por
             el sol necesita arreglarlo donde está, no ir a buscarlo. --}}
        <button type="button" data-theme-toggle
                class="touch-target focus-ring flex shrink-0 items-center justify-center rounded-full text-on-surface-variant transition hover:bg-surface-high"
                aria-label="Cambiar entre modo claro y oscuro">
            <span class="material-symbols-outlined text-[22px]" aria-hidden="true">contrast</span>
        </button>
    </div>
</header>

<main class="page-enter mx-auto w-full max-w-md flex-1 px-5 py-6">

    @if (session('error'))
        <div class="glass mb-5 px-4 py-3">
            <p class="text-base text-on-surface">{{ session('error') }}</p>
        </div>
    @endif

    @yield('content')
</main>

@hasSection('back')
    <footer class="px-5 pb-8">
        <div class="mx-auto w-full max-w-md">
            @yield('back')
        </div>
    </footer>
@endif

</body>
</html>
