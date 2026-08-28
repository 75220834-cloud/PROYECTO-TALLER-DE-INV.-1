@extends('teacher.layout')
@section('title', 'Listo')

@section('content')
    @php
        $resolvedByTeacher = $incident->resolution_type === 'assistant';
    @endphp

    @if ($resolvedByTeacher)
        <div class="rounded-xl border-2 border-emerald-500 bg-emerald-50 px-5 py-6 text-center">
            <p class="text-3xl">✅</p>
            <h1 class="mt-2 text-2xl font-bold text-emerald-900">Problema resuelto</h1>
            <p class="mt-2 text-lg text-emerald-800">
                Gracias por avisarnos. Quedó registrado.
            </p>
        </div>

        <p class="mt-5 text-base leading-relaxed text-slate-600">
            Si el problema vuelve a aparecer, escanea el código otra vez.
        </p>
    @else
        <div class="rounded-xl border-2 border-slate-900 bg-slate-50 px-5 py-6 text-center">
            <p class="text-3xl">📩</p>
            <h1 class="mt-2 text-2xl font-bold">Soporte ya fue avisado</h1>

            @if ($incident->ticket_number)
                <p class="mt-3 font-mono text-xl font-semibold">#{{ $incident->ticket_number }}</p>
            @endif

            <p class="mt-3 text-lg text-slate-700">
                {{ $incident->room?->code }} · {{ $incident->category?->name }}
            </p>
        </div>

        {{-- El punto del sistema: soporte YA tiene todo. El docente no
             tendrá que volver a explicar nada (plan §45). --}}
        <div class="mt-5 rounded-xl bg-slate-100 px-5 py-4">
            <p class="text-base font-semibold text-slate-800">Soporte ya sabe:</p>
            <ul class="mt-2 space-y-1 text-base text-slate-700">
                <li>· En qué aula estás</li>
                <li>· Qué equipo falla</li>
                <li>· Qué intentaste</li>
                @if ($incident->blocks_class)
                    <li>· Que <strong>no puedes dictar la clase</strong></li>
                @endif
            </ul>
            <p class="mt-3 text-base text-slate-600">No hace falta que llames ni expliques de nuevo.</p>
        </div>
    @endif

    {{-- Encuesta de facilidad (plan 26.bis, D-8: OPCIONAL y omitible).
         Va al final, después de que el problema esté resuelto, y con una
         sola pregunta. Una encuesta obligatoria en mitad del flujo penaliza
         al docente en el peor momento — de pie frente a su clase — y además
         contamina el propio indicador que pretende medir. --}}
    @if (! $incident->satisfaction)
        <form method="POST" action="{{ route('teacher.survey', ['uuid' => $incident->uuid]) }}"
              class="mt-6 rounded-xl border-2 border-slate-200 px-5 py-4">
            @csrf

            <p class="text-base font-medium text-slate-800">¿Te resultó fácil de usar?</p>
            <p class="mt-1 text-sm text-slate-500">Opcional. Nos ayuda a mejorarlo.</p>

            <div class="mt-3 flex gap-2">
                @foreach ([1, 2, 3, 4, 5] as $score)
                    <button type="submit" name="ease_score" value="{{ $score }}"
                            class="flex h-14 flex-1 items-center justify-center rounded-xl border-2 border-slate-300 text-lg font-semibold text-slate-700 active:scale-95 hover:border-slate-900">
                        {{ $score }}
                    </button>
                @endforeach
            </div>

            <div class="mt-2 flex justify-between text-xs text-slate-500">
                <span>Difícil</span>
                <span>Muy fácil</span>
            </div>
        </form>
    @elseif (session('survey_thanks'))
        <p class="mt-6 rounded-xl bg-emerald-50 px-5 py-4 text-center text-base text-emerald-800">
            Gracias por responder.
        </p>
    @endif

    <a href="{{ route('teacher.start') }}"
       class="mt-6 flex min-h-[60px] w-full items-center justify-center rounded-xl border-2 border-slate-300 px-5 py-3 text-lg font-medium text-slate-700 hover:bg-slate-50">
        Reportar otro problema
    </a>
@endsection
