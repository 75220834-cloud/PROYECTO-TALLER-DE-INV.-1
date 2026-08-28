@extends('teacher.layout')
@section('title', '¿Se solucionó?')
@section('step', $incident->room?->code . ' · ' . $incident->category?->name)

@section('content')
    {{-- Confirmación de solución. Las TRES opciones son obligatorias y están
         fijadas por el plan (§12). Se pregunta SIEMPRE, incluso cuando el
         árbol de diagnóstico dio el problema por resuelto: el procedimiento
         puede dar por bueno algo que en el aula no funcionó. --}}

    @if ($stepsDone > 0)
        <div class="mb-6 rounded-xl bg-slate-100 px-5 py-4">
            <p class="text-base text-slate-700">
                Revisamos {{ $stepsDone }} {{ $stepsDone === 1 ? 'cosa' : 'cosas' }} juntos.
            </p>
        </div>
    @endif

    <h1 class="mb-5 text-2xl font-bold">¿El problema ya se solucionó?</h1>

    <form method="POST" action="{{ route('teacher.resolved') }}">
        @csrf
        <button type="submit"
                class="flex min-h-[64px] w-full items-center justify-center rounded-xl bg-emerald-600 px-5 py-4 text-lg font-semibold text-white transition active:scale-[.99] hover:bg-emerald-700">
            Sí, funciona correctamente
        </button>
    </form>

    @if ($canContinue)
        {{-- Solo se ofrece continuar si de verdad quedan pasos. Un botón que
             recarga la misma pantalla haría creer al docente que el sistema
             se colgó. --}}
        <a href="{{ route('teacher.diagnostic') }}"
           class="mt-3 flex min-h-[64px] w-full items-center justify-center rounded-xl border-2 border-slate-300 px-5 py-4 text-lg font-medium text-slate-800 hover:bg-slate-50">
            No, todavía tengo el problema
        </a>
    @endif

    <a href="{{ route('teacher.escalate') }}"
       class="mt-3 flex min-h-[64px] w-full items-center justify-center rounded-xl border-2 border-slate-900 bg-slate-900 px-5 py-4 text-lg font-semibold text-white hover:bg-slate-800">
        {{ $canContinue ? 'Necesito soporte técnico' : 'No, necesito soporte técnico' }}
    </a>

    {{-- Salida intermedia entre "sigo atascado" y "que venga alguien": el
         docente pregunta y el asistente responde con los procedimientos
         cargados. Va discreta a propósito — quien quiere que le resuelvan ya
         no debe tropezar con una tercera opción antes de pedir soporte. --}}
    <a href="{{ route('teacher.ask.form') }}"
       class="mt-4 block text-center text-base text-slate-600 underline underline-offset-4">
        Tengo una duda distinta
    </a>
@endsection
