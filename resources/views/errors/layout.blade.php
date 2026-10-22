<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#141316">
    <title>@yield('title')</title>

    {{-- Sin nada que consulte la base de datos: estas páginas se muestran
         justamente cuando algo falla, incluida la base. --}}
    @vite(['resources/js/theme-init.js', 'resources/css/app.css'])
    <link rel="stylesheet" href="{{ asset('fonts/fuentes.css') }}">
</head>
<body class="flex min-h-full flex-col items-center justify-center bg-transparent px-5 py-10 text-on-surface antialiased">
    <main class="glass w-full max-w-md px-6 py-8 text-center">
        <p class="label-tech text-on-surface-variant">Soporte de aulas · error @yield('code')</p>
        <h1 class="mt-3 text-[26px] font-semibold leading-8 tracking-[-0.02em]">@yield('title')</h1>
        <p class="mt-3 text-lg leading-7 text-on-surface-variant">@yield('message')</p>

        <a href="{{ url('/reportar') }}"
           class="btn-primary focus-ring mt-6 inline-flex min-h-[52px] items-center justify-center px-6 text-base font-semibold">
            Volver al inicio
        </a>
    </main>
</body>
</html>
