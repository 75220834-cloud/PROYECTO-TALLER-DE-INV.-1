@extends('layouts.app')
@section('title', 'Categorías')
@section('heading', 'Tipos de problema')
@section('subheading', 'Lo que el docente ve al reportar')

@section('content')

<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <p class="max-w-2xl text-sm text-on-surface-variant">
        El orden de esta lista es el orden en que aparecen los botones en el móvil del docente.
        Pon arriba lo que más se reporta.
    </p>

    <a href="{{ route('admin.categories.create') }}"
       class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-on-primary">
        Nueva categoría
    </a>
</div>

<div class="overflow-x-auto rounded-lg border border-outline-variant bg-surface-lowest">
    <table class="w-full text-sm">
        <thead class="border-b border-outline-variant bg-surface-low text-left text-xs uppercase tracking-wide text-on-surface-variant">
            <tr>
                <th class="px-4 py-3">Orden</th>
                <th class="px-4 py-3">Código</th>
                <th class="px-4 py-3">Nombre interno</th>
                <th class="px-4 py-3">Lo que lee el docente</th>
                <th class="px-4 py-3">Prioridad base</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($categories as $category)
                <tr class="border-b border-outline-variant last:border-0 {{ $category->is_active ? '' : 'opacity-50' }}">
                    <td class="px-4 py-3 text-on-surface-variant">{{ $category->sort_order }}</td>
                    <td class="px-4 py-3 font-mono text-xs">{{ $category->code }}</td>
                    <td class="px-4 py-3 font-medium">{{ $category->name }}</td>
                    <td class="px-4 py-3 text-on-surface-variant">{{ $category->teacherText() }}</td>
                    <td class="px-4 py-3 text-on-surface-variant">{{ $category->defaultPriority?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('admin.categories.edit', $category) }}"
                               class="text-on-surface-variant underline underline-offset-2">Editar</a>

                            <form method="POST" action="{{ route('admin.categories.toggle', $category) }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="text-on-surface-variant underline underline-offset-2">
                                    {{ $category->is_active ? 'Ocultar' : 'Mostrar' }}
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- Se explica la ausencia del botón de borrar en lugar de dejar al
     administrador buscándolo por la pantalla. --}}
<p class="mt-5 rounded-lg border border-outline-variant bg-surface-lowest px-4 py-3 text-sm text-on-surface-variant">
    <strong class="text-on-surface">Las categorías no se borran.</strong>
    Una categoría retirada sigue clasificando incidencias del pasado, y borrarla dejaría huecos en
    los datos que la investigación va a analizar. Ocultarla la quita de la pantalla del docente sin
    tocar el histórico.
</p>
@endsection
