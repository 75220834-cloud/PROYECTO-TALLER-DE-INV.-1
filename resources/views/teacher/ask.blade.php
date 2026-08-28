@extends('teacher.layout')
@section('title', 'Preguntar')
@section('step', $incident?->room?->code ?? 'Ayuda')

@section('content')
    <h1 class="mb-1 text-[28px] font-semibold leading-9 tracking-[-0.02em]">Pregunta lo que necesites</h1>
    <p class="mb-6 text-[18px] leading-7 text-on-surface-variant">
        Respondemos solo con los procedimientos que soporte tiene cargados.
    </p>

    <form method="POST" action="{{ route('teacher.ask') }}">
        @csrf

        <textarea name="question" rows="3" maxlength="500" required
                  placeholder="Por ejemplo: ¿cómo cambio la fuente del proyector?"
                  class="block w-full glass focus-ring w-full px-4 py-3 text-base focus:border-accent">{{ $question }}</textarea>

        @error('question')
            <p class="mt-2 rounded-lg bg-danger-container px-4 py-3 text-base text-on-danger-container">{{ $message }}</p>
        @enderror

        <button type="submit"
                class="mt-3 min-h-[56px] w-full btn-primary focus-ring px-5 py-4 text-lg font-semibold active:scale-[.99]">
            Preguntar
        </button>
    </form>

    @if ($answer !== null)
        <div class="mt-6 rounded-xl border-2 {{ $answer->escalated ? 'border-warn bg-warn-container' : 'border-primary bg-surface-lowest' }} px-5 py-4">

            {{-- El texto de la respuesta viene escapado por Blade. La salida
                 del modelo se trata como entrada NO confiable: nunca se
                 renderiza como HTML (plan 16.2). --}}
            <p class="whitespace-pre-line text-base leading-relaxed text-on-surface">{{ $answer->text }}</p>

            @if ($answer->sources->isNotEmpty())
                {{-- La cita no es adorno: es lo que permite al docente
                     comprobar que la respuesta viene de un documento real y
                     no de algo que el modelo se inventó (plan 42). --}}
                <div class="mt-4 border-t border-outline-variant pt-3">
                    <p class="text-sm font-medium text-on-surface-variant">De dónde sale esta respuesta</p>
                    <ul class="mt-1 space-y-1">
                        @foreach ($answer->sources as $source)
                            <li class="text-sm text-on-surface-variant">· {{ $source->citation() }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        @if ($answer->escalated)
            {{-- Cuando el sistema no sabe, no deja al docente sin salida:
                 le ofrece el camino que sí resuelve. --}}
            <a href="{{ route('teacher.escalate') }}"
               class="mt-4 flex min-h-[64px] w-full items-center justify-center btn-primary focus-ring px-5 py-4 text-lg font-semibold active:scale-[.99]">
                Solicitar soporte técnico
            </a>
        @endif
    @endif
@endsection

@section('back')
    @if ($incident !== null)
        <a href="{{ route('teacher.outcome') }}" class="block text-center text-base text-on-surface-variant underline underline-offset-4">
            Volver
        </a>
    @else
        <a href="{{ route('teacher.start') }}" class="block text-center text-base text-on-surface-variant underline underline-offset-4">
            Reportar un problema
        </a>
    @endif
@endsection
