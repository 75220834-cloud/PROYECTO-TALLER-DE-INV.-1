@extends('teacher.layout')
@section('title', '¿Es esto lo que pasa?')
@section('step', $incident->room?->code)

@section('content')

    {{-- Lo que el docente escribió, tal cual. Verlo repetido le confirma que
         el sistema leyó lo suyo y no otra cosa. --}}
    <div class="mb-5 rounded-xl border-2 border-slate-200 bg-slate-50 px-4 py-3">
        <p class="text-sm text-slate-500">Escribiste</p>
        <p class="mt-1 text-base text-slate-800">«{{ $description }}»</p>
    </div>

    @if ($decision === 'manual' || $suggested === null)

        {{-- El asistente no lo tuvo claro, y lo dice. No adivina: enseña el
             catálogo entero. Un docente nunca queda atascado porque el
             modelo no supo qué contestar. --}}
        <h1 class="mb-1 text-2xl font-bold">¿Con cuál se parece más?</h1>
        <p class="mb-5 text-base text-slate-600">No estamos seguros de qué tipo de problema es. Elígelo tú.</p>

        <form method="POST" action="{{ route('teacher.category.store') }}">
            @csrf
            <input type="hidden" name="description" value="{{ $description }}">

            <div class="space-y-3">
                @foreach ($categories as $category)
                    <button type="submit" name="category_id" value="{{ $category->id }}"
                            class="flex min-h-[64px] w-full items-center justify-between gap-3 rounded-xl border-2 border-slate-300 bg-white px-5 py-4 text-left text-lg font-medium text-slate-900 active:scale-[.99] hover:border-slate-900">
                        <span>{{ $category->teacherText() }}</span>
                        <span aria-hidden="true" class="text-2xl leading-none text-slate-400">&rsaquo;</span>
                    </button>
                @endforeach
            </div>
        </form>

    @else

        {{-- Hay una propuesta. Se PREGUNTA, nunca se da por buena: si el
             sistema acierta mal y sigue solo, el docente acaba en un árbol de
             diagnóstico que no tiene que ver con su problema y abandona. --}}
        <h1 class="mb-1 text-2xl font-bold">¿Es esto lo que pasa?</h1>
        <p class="mb-5 text-base text-slate-600">Confírmanos que entendimos bien.</p>

        <form method="POST" action="{{ route('teacher.category.store') }}">
            @csrf
            <input type="hidden" name="description" value="{{ $description }}">

            <button type="submit" name="category_id" value="{{ $suggested->id }}"
                    class="flex min-h-[80px] w-full items-center justify-between gap-3 rounded-xl border-2 border-slate-900 bg-slate-900 px-5 py-4 text-left text-xl font-semibold text-white active:scale-[.99]">
                <span>Sí, es {{ mb_strtolower($suggested->name) }}</span>
                <span aria-hidden="true" class="text-2xl leading-none">&rsaquo;</span>
            </button>

            @if ($alternatives->isNotEmpty())
                <p class="mb-2 mt-6 text-base font-medium text-slate-700">O quizá sea…</p>

                <div class="space-y-3">
                    @foreach ($alternatives as $alternative)
                        <button type="submit" name="category_id" value="{{ $alternative->id }}"
                                class="flex min-h-[64px] w-full items-center justify-between gap-3 rounded-xl border-2 border-slate-300 bg-white px-5 py-4 text-left text-lg font-medium text-slate-900 active:scale-[.99] hover:border-slate-900">
                            <span>{{ $alternative->teacherText() }}</span>
                            <span aria-hidden="true" class="text-2xl leading-none text-slate-400">&rsaquo;</span>
                        </button>
                    @endforeach
                </div>
            @endif
        </form>

        <a href="{{ route('teacher.category') }}"
           class="mt-6 block text-center text-base text-slate-600 underline underline-offset-4">
            No, es otra cosa
        </a>

    @endif
@endsection

@section('back')
    <a href="{{ route('teacher.category') }}" class="block text-center text-base text-slate-600 underline underline-offset-4">
        Volver
    </a>
@endsection
