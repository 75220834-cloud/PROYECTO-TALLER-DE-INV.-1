@extends('teacher.layout')
@section('title', '¿Es esto lo que pasa?')
@section('step', $incident->room?->code)

@section('content')

    {{-- Lo que el docente escribió, tal cual. Verlo repetido le confirma que
         el sistema leyó lo suyo y no otra cosa. --}}
    <div class="mb-5 glass focus-ring px-4 py-3">
        <p class="label-tech text-on-surface-variant">Escribiste</p>
        <p class="mt-1 text-base text-on-surface">«{{ $description }}»</p>
    </div>

    @if ($decision === 'manual' || $suggested === null)

        {{-- El asistente no lo tuvo claro, y lo dice. No adivina: enseña el
             catálogo entero. Un docente nunca queda atascado porque el
             modelo no supo qué contestar. --}}
        <h1 class="mb-1 text-[28px] font-semibold leading-9 tracking-[-0.02em]">¿Con cuál se parece más?</h1>
        <p class="mb-6 text-[18px] leading-7 text-on-surface-variant">No estamos seguros de qué tipo de problema es. Elígelo tú.</p>

        <form method="POST" action="{{ route('teacher.category.store') }}">
            @csrf
            <input type="hidden" name="description" value="{{ $description }}">

            <div class="space-y-3">
                @foreach ($categories as $category)
                    <button type="submit" name="category_id" value="{{ $category->id }}"
                            class="flex min-h-[64px] w-full items-center justify-between gap-3 glass glass-hover focus-ring px-5 py-4 text-left text-lg font-medium text-on-surface active:scale-[.99] hover:border-primary">
                        <span>{{ $category->teacherText() }}</span>
                        <span aria-hidden="true" class="text-2xl leading-none text-outline">&rsaquo;</span>
                    </button>
                @endforeach
            </div>
        </form>

    @else

        {{-- Hay una propuesta. Se PREGUNTA, nunca se da por buena: si el
             sistema acierta mal y sigue solo, el docente acaba en un árbol de
             diagnóstico que no tiene que ver con su problema y abandona. --}}
        <h1 class="mb-1 text-[28px] font-semibold leading-9 tracking-[-0.02em]">¿Es esto lo que pasa?</h1>
        <p class="mb-6 text-[18px] leading-7 text-on-surface-variant">Confírmanos que entendimos bien.</p>

        <form method="POST" action="{{ route('teacher.category.store') }}">
            @csrf
            <input type="hidden" name="description" value="{{ $description }}">

            <button type="submit" name="category_id" value="{{ $suggested->id }}"
                    class="flex min-h-[80px] w-full items-center justify-between gap-3 btn-primary focus-ring px-5 py-4 text-left text-xl font-semibold active:scale-[.99]">
                <span>Sí, es {{ mb_strtolower($suggested->name) }}</span>
                <span aria-hidden="true" class="text-2xl leading-none">&rsaquo;</span>
            </button>

            @if ($alternatives->isNotEmpty())
                <p class="mb-2 mt-6 text-base font-medium text-on-surface-variant">O quizá sea…</p>

                <div class="space-y-3">
                    @foreach ($alternatives as $alternative)
                        <button type="submit" name="category_id" value="{{ $alternative->id }}"
                                class="flex min-h-[64px] w-full items-center justify-between gap-3 glass glass-hover focus-ring px-5 py-4 text-left text-lg font-medium text-on-surface active:scale-[.99] hover:border-primary">
                            <span>{{ $alternative->teacherText() }}</span>
                            <span aria-hidden="true" class="text-2xl leading-none text-outline">&rsaquo;</span>
                        </button>
                    @endforeach
                </div>
            @endif
        </form>

        <a href="{{ route('teacher.category') }}"
           class="mt-6 block text-center text-base text-on-surface-variant underline underline-offset-4">
            No, es otra cosa
        </a>

    @endif
@endsection

@section('back')
    <a href="{{ route('teacher.category') }}" class="block text-center text-base text-on-surface-variant underline underline-offset-4">
        Volver
    </a>
@endsection
