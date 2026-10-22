@extends('teacher.layout')
@section('title', '¿Qué problema tienes?')
@section('step', $incident->room?->code)

@section('content')
    <h1 class="mb-4 text-[28px] font-semibold leading-9 tracking-[-0.02em]">¿Qué problema tienes?</h1>

    {{-- La IA va ARRIBA y a la vista, con su distintivo dorado: escondida al
         final, nadie sabía que existía. Pero va plegada: el texto solo se
         despliega al tocarla, así las opciones de abajo siguen a la vista y
         quien ya reconoce su problema lo toca sin escribir nada.

         Formulario APARTE del de categorías, no anidado dentro. --}}
    <details class="glass ia-card mb-6 px-5 py-4" @if ($errors->has('description')) open @endif>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
            <span>
                <span class="ia-badge"><span aria-hidden="true">✦</span> IA</span>
                <span class="mt-1.5 block text-lg font-semibold text-on-surface">Cuéntalo con tus palabras</span>
                <span class="block text-base text-on-surface-variant">La IA entiende el problema y te lleva al paso correcto.</span>
            </span>
            <span aria-hidden="true" class="material-symbols-outlined text-[26px] text-gold-text">edit_note</span>
        </summary>

        <form method="POST" action="{{ route('teacher.classify') }}" class="mt-4">
            @csrf

            <textarea name="description" rows="3" maxlength="1000" required
                      placeholder="Por ejemplo: la pantalla se ve azul y no pasa nada"
                      class="block w-full rounded-[var(--radius-md)] border border-outline-variant bg-surface-lowest focus-ring px-4 py-3 text-base text-on-surface focus:border-accent">{{ old('description') }}</textarea>

            @error('description')
                <p class="mt-2 rounded-lg bg-danger-container px-4 py-3 text-base text-on-danger-container">{{ $message }}</p>
            @enderror

            <button type="submit"
                    class="mt-3 min-h-[56px] w-full btn-primary focus-ring px-5 py-4 text-lg font-semibold active:scale-[.99]">
                Continuar
            </button>

            <p class="mt-2 text-sm text-on-surface-variant">
                Te diremos qué entendimos y podrás corregirnos.
            </p>
        </form>
    </details>

    <p class="mb-3 text-[18px] leading-7 text-on-surface-variant">O toca la opción que más se parezca:</p>

    <form method="POST" action="{{ route('teacher.category.store') }}" id="reporte">
        @csrf

        <div class="space-y-3">
            @foreach ($categories as $category)
                {{-- Cada categoría es un botón grande de envío directo: sin
                     radios que haya que marcar y luego confirmar. Un paso
                     menos en la pantalla más usada del sistema. --}}
                <button type="submit" name="category_id" value="{{ $category->id }}"
                        class="flex min-h-[64px] w-full items-center justify-between gap-3 glass glass-hover focus-ring px-5 py-4 text-left text-lg font-medium text-on-surface transition active:scale-[.99] hover:bg-surface-high">
                    <span>{{ $category->teacherText() }}</span>
                    <span aria-hidden="true" class="text-2xl leading-none text-outline">&rsaquo;</span>
                </button>
            @endforeach
        </div>

        @error('category_id')
            <p class="mt-3 rounded-lg bg-danger-container px-4 py-3 text-base text-on-danger-container">{{ $message }}</p>
        @enderror

    </form>
@endsection

@section('back')
    <a href="{{ route('teacher.start') }}" class="block text-center text-base text-on-surface-variant underline underline-offset-4">
        Cambiar aula
    </a>
@endsection
