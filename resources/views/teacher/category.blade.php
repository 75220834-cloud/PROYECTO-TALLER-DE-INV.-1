@extends('teacher.layout')
@section('title', '¿Qué problema tienes?')
@section('step', $incident->room?->code)

@section('content')
    <h1 class="mb-1 text-2xl font-bold">¿Qué problema tienes?</h1>
    <p class="mb-5 text-base text-on-surface-variant">Toca la opción que más se parezca.</p>

    <form method="POST" action="{{ route('teacher.category.store') }}" id="reporte">
        @csrf

        <div class="space-y-3">
            @foreach ($categories as $category)
                {{-- Cada categoría es un botón grande de envío directo: sin
                     radios que haya que marcar y luego confirmar. Un paso
                     menos en la pantalla más usada del sistema. --}}
                <button type="submit" name="category_id" value="{{ $category->id }}"
                        class="flex min-h-[64px] w-full items-center justify-between gap-3 rounded-xl border-2 border-outline-variant bg-surface-lowest px-5 py-4 text-left text-lg font-medium text-on-surface transition active:scale-[.99] hover:border-primary hover:bg-surface-high">
                    <span>{{ $category->teacherText() }}</span>
                    <span aria-hidden="true" class="text-2xl leading-none text-outline">&rsaquo;</span>
                </button>
            @endforeach
        </div>

        @error('category_id')
            <p class="mt-3 rounded-lg bg-danger-container px-4 py-3 text-base text-on-danger-container">{{ $message }}</p>
        @enderror

    </form>

    {{-- Formulario APARTE del de categorías, no anidado dentro: aquí el
         docente describe el problema con sus palabras y el asistente propone
         una categoría. Va después de los botones porque tocar una opción es
         más rápido para quien ya sabe cuál es la suya; escribir es la salida
         para quien no reconoce ninguna. --}}
    <details class="mt-6" @if ($errors->has('description')) open @endif>
        <summary class="cursor-pointer text-base text-on-surface-variant underline underline-offset-4">
            No sé cuál es · quiero explicarlo con mis palabras
        </summary>

        <form method="POST" action="{{ route('teacher.classify') }}" class="mt-3">
            @csrf

            <textarea name="description" rows="3" maxlength="1000" required
                      placeholder="Por ejemplo: la pantalla se ve azul y no pasa nada"
                      class="block w-full rounded-xl border-2 border-outline-variant px-4 py-3 text-base focus:border-primary focus:ring-0">{{ old('description') }}</textarea>

            @error('description')
                <p class="mt-2 rounded-lg bg-danger-container px-4 py-3 text-base text-on-danger-container">{{ $message }}</p>
            @enderror

            <button type="submit"
                    class="mt-3 min-h-[56px] w-full rounded-xl bg-primary px-5 py-4 text-lg font-semibold text-on-primary active:scale-[.99]">
                Continuar
            </button>

            <p class="mt-2 text-sm text-on-surface-variant">
                Te diremos qué entendimos y podrás corregirnos.
            </p>
        </form>
    </details>
@endsection

@section('back')
    <a href="{{ route('teacher.start') }}" class="block text-center text-base text-on-surface-variant underline underline-offset-4">
        Cambiar aula
    </a>
@endsection
