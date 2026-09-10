@extends('teacher.layout')
@section('title', 'Solicitar soporte')
@section('step', 'Solicitar apoyo presencial')

@section('content')
    <h1 class="mb-5 text-[28px] font-semibold leading-9 tracking-[-0.02em]">Solicitar soporte técnico</h1>

    {{-- Riesgo físico (plan §17.5). Va lo primero y con la instrucción de no
         tocar nada por delante de cualquier otra cosa: el docente está de pie
         frente a un equipo que puede estar quemándose y puede no leer más
         abajo. Aquí no se le ofrece ningún paso de diagnóstico: pedirle que
         revise un cable sería mandarlo a acercarse al equipo. --}}
    @if ($incident->hazard_reported)
        <div class="mb-6 glass border-danger/70 bg-danger-container/70 px-5 py-4">
            <p class="text-lg font-bold text-on-danger-container">No manipules el equipo</p>
            <p class="mt-2 text-on-danger-container">
                No lo toques, no lo desconectes y no intentes apagarlo tú. Si puedes hacerlo
                sin acercarte, corta la energía desde el interruptor de la pared y aleja a
                los estudiantes del equipo.
            </p>
            <p class="mt-2 font-medium text-on-danger-container">
                Vamos a avisar a soporte con la máxima prioridad.
            </p>
        </div>
    @endif

    {{-- Aulas que no se autoatienden. Va antes del resumen porque explica
         por qué el docente llegó aquí sin haber elegido un problema: sin
         esta caja, la pantalla parece habérselo saltado por error. --}}
    @if ($incident->room && ! $incident->room->self_service)
        <div class="mb-6 glass border-primary/60 bg-surface-low px-5 py-4">
            <p class="text-lg font-semibold">Esta aula la atiende soporte técnico</p>
            <p class="mt-2 text-base text-on-surface-variant">
                {{ $incident->room->support_only_reason ?? 'Soporte técnico se encarga directamente de esta aula.' }}
                Cuéntanos qué pasa y les avisamos.
            </p>
        </div>
    @endif

    <div class="mb-6 glass px-5 py-4">
        <p class="text-sm text-on-surface-variant">Se avisará a soporte sobre</p>
        <p class="mt-1 font-mono text-2xl font-bold">{{ $incident->room?->code }}</p>
        @if ($incident->category)
            <p class="mt-1 text-lg text-on-surface-variant">{{ $incident->category->name }}</p>
        @endif
    </div>

    <form method="POST" action="{{ route('teacher.escalate.store') }}" enctype="multipart/form-data">
        @csrf

        {{-- Marca de tiempo de apertura: alimenta la señal de "no humano"
             (envío instantáneo). No es un secreto, solo una medida. --}}
        <input type="hidden" name="form_opened_at" value="{{ $formOpenedAt }}">

        {{-- Campo trampa. Oculto para personas, irresistible para bots.
             Se prefiere esto a un CAPTCHA porque un CAPTCHA contradice de
             frente el requisito de que el sistema sea usable por docentes
             con poca familiaridad tecnológica (plan §8). --}}
        <div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px">
            <label for="website">No completar</label>
            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
        </div>

        <p class="mb-3 text-lg font-semibold">¿El problema impide continuar la clase?</p>

        <div class="space-y-3">
            <label class="flex min-h-[60px] cursor-pointer items-center gap-3 rounded-xl border-2 border-outline-variant px-5 py-3 text-lg hover:bg-surface-high has-[:checked]:border-primary has-[:checked]:bg-surface-low">
                <input type="radio" name="blocks_class" value="1" required class="h-5 w-5">
                Sí, no puedo dictar la clase
            </label>

            <label class="flex min-h-[60px] cursor-pointer items-center gap-3 rounded-xl border-2 border-outline-variant px-5 py-3 text-lg hover:bg-surface-high has-[:checked]:border-primary has-[:checked]:bg-surface-low">
                <input type="radio" name="blocks_class" value="0" class="h-5 w-5">
                No, puedo continuar
            </label>
        </div>

        @error('blocks_class')
            <p class="mt-3 rounded-lg bg-danger-container px-4 py-3 text-base text-on-danger-container">{{ $message }}</p>
        @enderror

        {{-- Foto opcional (decisión D-12). `capture="environment"` abre la
             cámara trasera directamente en el móvil, sin pasar por la
             galería: el docente tiene el problema delante.

             Va después de la pregunta de la clase y antes de confirmar, y
             deja clarísimo que es opcional — quien tiene el aula esperando
             no puede sentir que le falta un paso. --}}
        <div class="mt-6">
            <p class="text-lg font-semibold">¿Quieres mandarnos una foto?</p>
            <p class="mt-1 text-sm text-on-surface-variant">
                Opcional. Ayuda al técnico a saber qué llevar antes de venir.
            </p>

            <label class="glass focus-ring mt-3 flex min-h-[56px] w-full cursor-pointer items-center justify-center gap-3 px-5 py-3 text-base font-medium">
                <span class="material-symbols-outlined" aria-hidden="true">photo_camera</span>
                <span>Tomar una foto</span>
                <input type="file" name="foto" accept="image/*" capture="environment" class="sr-only">
            </label>

            @error('foto')
                <p class="mt-2 glass border-danger/70 bg-danger-container/70 px-4 py-3 text-base text-on-danger-container">{{ $message }}</p>
            @enderror
        </div>

        {{-- Confirmación explícita: primer control antiabuso y, sobre todo,
             lo que evita los envíos accidentales, que son la causa más
             frecuente de ruido (plan §16.4). --}}
        <label class="mt-6 flex cursor-pointer items-start gap-3 glass px-4 py-3 text-base">
            <input type="checkbox" name="confirmed" value="1" required class="mt-1 h-5 w-5">
            <span>Confirmo que necesito que un técnico venga al aula.</span>
        </label>

        @error('confirmed')
            <p class="mt-3 rounded-lg bg-danger-container px-4 py-3 text-base text-on-danger-container">{{ $message }}</p>
        @enderror

        <button type="submit"
                class="mt-5 flex min-h-[64px] w-full items-center justify-center btn-primary focus-ring px-5 py-4 text-lg font-semibold transition active:scale-[.99] hover:opacity-90">
            Sí, solicitar soporte
        </button>
    </form>
@endsection

@section('back')
    <a href="{{ route('teacher.outcome') }}" class="block text-center text-base text-on-surface-variant underline underline-offset-4">
        Cancelar
    </a>
@endsection
