@extends('teacher.layout')
@section('title', '¿Qué problema tienes?')
@section('step', $incident->room?->code)

@section('content')
    <h1 class="mb-1 text-2xl font-bold">¿Qué problema tienes?</h1>
    <p class="mb-5 text-base text-slate-600">Toca la opción que más se parezca.</p>

    <form method="POST" action="{{ route('teacher.category.store') }}" id="reporte">
        @csrf

        <div class="space-y-3">
            @foreach ($categories as $category)
                {{-- Cada categoría es un botón grande de envío directo: sin
                     radios que haya que marcar y luego confirmar. Un paso
                     menos en la pantalla más usada del sistema. --}}
                <button type="submit" name="category_id" value="{{ $category->id }}"
                        class="flex min-h-[64px] w-full items-center justify-between gap-3 rounded-xl border-2 border-slate-300 bg-white px-5 py-4 text-left text-lg font-medium text-slate-900 transition active:scale-[.99] hover:border-slate-900 hover:bg-slate-50">
                    <span>{{ $category->teacherText() }}</span>
                    <span aria-hidden="true" class="text-2xl leading-none text-slate-400">&rsaquo;</span>
                </button>
            @endforeach
        </div>

        @error('category_id')
            <p class="mt-3 rounded-lg bg-rose-50 px-4 py-3 text-base text-rose-800">{{ $message }}</p>
        @enderror

        <details class="mt-6">
            <summary class="cursor-pointer text-base text-slate-600 underline underline-offset-4">
                Quiero explicarlo con mis palabras
            </summary>

            <textarea name="description" rows="3" maxlength="1000"
                      placeholder="Por ejemplo: la pantalla se ve azul y no pasa nada"
                      class="mt-3 block w-full rounded-xl border-2 border-slate-300 px-4 py-3 text-base focus:border-slate-900 focus:ring-0">{{ old('description') }}</textarea>

            <p class="mt-2 text-sm text-slate-500">
                Escríbelo y luego toca la opción que más se acerque.
            </p>
        </details>
    </form>
@endsection

@section('back')
    <a href="{{ route('teacher.start') }}" class="block text-center text-base text-slate-600 underline underline-offset-4">
        Cambiar aula
    </a>
@endsection
