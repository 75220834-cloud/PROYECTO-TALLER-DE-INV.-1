<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex h-full items-center justify-center bg-slate-100 px-4 antialiased">

<div class="w-full max-w-sm">
    <div class="mb-6 text-center">
        <h1 class="text-xl font-semibold text-slate-900">Incidencias en aulas</h1>
        <p class="mt-1 text-sm text-slate-500">Acceso para personal de soporte</p>
    </div>

    <form method="POST" action="{{ route('login.store') }}"
          class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium text-slate-700">Correo</label>
            <input id="email" name="email" type="email" required autofocus autocomplete="username"
                   value="{{ old('email') }}"
                   class="mt-1 block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-slate-700">Contraseña</label>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   class="mt-1 block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
        </div>

        @error('email')
            <p class="rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ $message }}</p>
        @enderror

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" class="rounded border-slate-300 text-slate-800 focus:ring-slate-500">
            Mantener la sesión iniciada
        </label>

        <button type="submit"
                class="w-full rounded-md bg-slate-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-slate-800">
            Entrar
        </button>
    </form>

    <p class="mt-6 text-center text-xs text-slate-400">
        Si eres docente y quieres reportar un problema, escanea el código QR del aula.
    </p>
</div>

</body>
</html>
