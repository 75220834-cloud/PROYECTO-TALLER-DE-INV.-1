@extends('teacher.layout')
@section('title', 'Listo')

@section('content')
    @php
        $resolvedByTeacher = $incident->resolution_type === 'assistant';
    @endphp

    @if ($resolvedByTeacher)
        <div class="glass border-ok/60 bg-ok-container/60 px-5 py-6 text-center">
            <p class="text-3xl">✅</p>
            <h1 class="mt-2 text-[28px] font-semibold leading-9 tracking-[-0.02em] text-on-ok-container">Problema resuelto</h1>
            <p class="mt-2 text-lg text-on-ok-container">
                Gracias por avisarnos. Quedó registrado.
            </p>
        </div>

        <p class="mt-5 text-base leading-relaxed text-on-surface-variant">
            Si el problema vuelve a aparecer, escanea el código otra vez.
        </p>
    @else
        <div class="glass px-5 py-6 text-center">
            <p class="text-3xl">📩</p>
            <h1 class="mt-2 text-[28px] font-semibold leading-9 tracking-[-0.02em]">Soporte ya fue avisado</h1>

            @if ($incident->ticket_number)
                <p class="mt-3 font-mono text-xl font-semibold">#{{ $incident->ticket_number }}</p>
            @endif

            <p class="mt-3 text-lg text-on-surface-variant">
                {{ $incident->room?->code }} · {{ $incident->category?->name }}
            </p>
        </div>

        {{-- El punto del sistema: soporte YA tiene todo. El docente no
             tendrá que volver a explicar nada (plan §45). --}}
        <div class="mt-5 glass px-5 py-4">
            <p class="text-base font-semibold text-on-surface">Soporte ya sabe:</p>
            <ul class="mt-2 space-y-1 text-base text-on-surface-variant">
                <li>· En qué aula estás</li>
                <li>· Qué equipo falla</li>
                <li>· Qué intentaste</li>
                @if ($incident->blocks_class)
                    <li>· Que <strong>no puedes dictar la clase</strong></li>
                @endif
            </ul>
            <p class="mt-3 text-base text-on-surface-variant">No hace falta que llames ni expliques de nuevo.</p>
        </div>
    @endif

    {{-- Encuesta de facilidad (plan 26.bis, D-8: OPCIONAL y omitible).
         Va al final, después de que el problema esté resuelto, y con una
         sola pregunta. Una encuesta obligatoria en mitad del flujo penaliza
         al docente en el peor momento — de pie frente a su clase — y además
         contamina el propio indicador que pretende medir. --}}
    @if (! $incident->satisfaction)
        <form method="POST" action="{{ route('teacher.survey', ['uuid' => $incident->uuid]) }}"
              class="mt-6 rounded-xl border-2 border-outline-variant px-5 py-4">
            @csrf

            <p class="text-base font-medium text-on-surface">¿Te resultó fácil de usar?</p>
            <p class="mt-1 text-sm text-on-surface-variant">Opcional. Nos ayuda a mejorarlo.</p>

            <div class="mt-3 flex gap-2">
                @foreach ([1, 2, 3, 4, 5] as $score)
                    <button type="submit" name="ease_score" value="{{ $score }}"
                            class="flex h-14 flex-1 items-center justify-center rounded-xl border-2 border-outline-variant text-lg font-semibold text-on-surface-variant active:scale-95 hover:border-primary">
                        {{ $score }}
                    </button>
                @endforeach
            </div>

            <div class="mt-2 flex justify-between text-xs text-on-surface-variant">
                <span>Difícil</span>
                <span>Muy fácil</span>
            </div>
        </form>
    @elseif (session('survey_thanks'))
        <p class="mt-6 rounded-xl bg-ok-container px-5 py-4 text-center text-base text-on-ok-container">
            Gracias por responder.
        </p>
    @endif

    <a href="{{ route('teacher.start') }}"
       class="mt-6 flex min-h-[60px] w-full items-center justify-center rounded-xl border-2 border-outline-variant px-5 py-3 text-lg font-medium text-on-surface-variant hover:bg-surface-high">
        Reportar otro problema
    </a>
@endsection
