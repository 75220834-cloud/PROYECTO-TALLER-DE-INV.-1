<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    {{-- maximum-scale NO se limita: bloquear el zoom en una pantalla que
         usarán docentes de todas las edades es una barrera de accesibilidad
         real, no un detalle de estilo (plan §8). --}}
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#fdfbff">
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
<body class="flex min-h-full flex-col bg-surface text-on-surface antialiased transition-colors">

<header class="border-b border-outline-variant bg-surface-low px-5 py-4">
    <div class="mx-auto flex w-full max-w-md items-center justify-between gap-3">
        <div class="min-w-0 flex-1">
            <p class="text-center text-base font-semibold">Soporte de aulas</p>
            @hasSection('step')
                <p class="mt-0.5 truncate text-center text-sm text-on-surface-variant">@yield('step')</p>
            @endif
        </div>

        {{-- El conmutador va en la cabecera de TODAS las pantallas, no
             escondido en un menú: un docente que no ve bien la pantalla por
             el sol necesita arreglarlo donde está, no ir a buscarlo. --}}
        <button type="button" data-theme-toggle
                class="touch-target flex shrink-0 items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-high"
                aria-label="Cambiar entre modo claro y oscuro">
            <span class="material-symbols-outlined text-[22px]" aria-hidden="true">contrast</span>
        </button>
    </div>
</header>

<main class="mx-auto w-full max-w-md flex-1 px-5 py-6">

    @if (session('error'))
        <div class="mb-5 rounded-xl border-2 border-warn bg-warn-container px-4 py-3 text-base text-on-warn-container">
            {{ session('error') }}
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
