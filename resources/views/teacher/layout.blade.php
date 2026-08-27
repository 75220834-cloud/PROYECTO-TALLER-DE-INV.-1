<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    {{-- maximum-scale NO se limita: bloquear el zoom en una pantalla que
         usaran docentes de todas las edades es una barrera de accesibilidad
         real, no un detalle de estilo (plan 8). --}}
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Reportar un problema')</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-full flex-col bg-white text-slate-900 antialiased">

<header class="border-b border-slate-200 px-5 py-4">
    <p class="text-center text-base font-semibold">Soporte de aulas</p>
    @hasSection('step')
        <p class="mt-0.5 text-center text-sm text-slate-500">@yield('step')</p>
    @endif
</header>

<main class="mx-auto w-full max-w-md flex-1 px-5 py-6">

    @if (session('error'))
        <div class="mb-5 rounded-lg border-2 border-amber-300 bg-amber-50 px-4 py-3 text-base text-amber-900">
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
