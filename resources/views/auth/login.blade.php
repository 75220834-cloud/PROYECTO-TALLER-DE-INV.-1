<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex h-full items-center justify-center bg-surface-mid px-4 antialiased">

<div class="w-full max-w-sm">
    <div class="mb-6 text-center">
        <h1 class="text-xl font-semibold text-primary">Incidencias en aulas</h1>
        <p class="mt-1 text-sm text-on-surface-variant">Acceso para personal de soporte</p>
    </div>

    <form method="POST" action="{{ route('login.store') }}"
          class="space-y-4 glass p-6 shadow-sm">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium text-on-surface-variant">Correo</label>
            <input id="email" name="email" type="email" required autofocus autocomplete="username"
                   value="{{ old('email') }}"
                   class="mt-1 block w-full rounded-md border-outline-variant px-3 py-2 text-sm shadow-sm focus:border-on-surface-variant focus:ring-on-surface-variant">
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-on-surface-variant">Contraseña</label>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   class="mt-1 block w-full rounded-md border-outline-variant px-3 py-2 text-sm shadow-sm focus:border-on-surface-variant focus:ring-on-surface-variant">
        </div>

        @error('email')
            <p class="rounded-md bg-danger-container px-3 py-2 text-sm text-danger">{{ $message }}</p>
        @enderror

        <label class="flex items-center gap-2 text-sm text-on-surface-variant">
            <input type="checkbox" name="remember" class="rounded border-outline-variant text-on-surface focus:ring-on-surface-variant">
            Mantener la sesión iniciada
        </label>

        <button type="submit"
                class="w-full rounded-md bg-primary px-4 py-2.5 text-sm font-medium text-on-primary transition hover:bg-on-surface">
            Entrar
        </button>
    </form>

    <p class="mt-6 text-center text-xs text-outline">
        Si eres docente y quieres reportar un problema, escanea el código QR del aula.
    </p>
</div>

</body>
</html>
