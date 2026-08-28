@extends('layouts.app')
@section('title', 'Banco de imágenes')
@section('heading', 'Banco de imágenes')
@section('subheading', 'Lo que el docente ve en cada paso del diagnóstico')

@section('content')

{{-- La cobertura va arriba porque es el número que dice si el diagnóstico
     guiado es utilizable de verdad. Un paso que menciona una pieza física y
     no la enseña deja tirado justo al docente que más lo necesita. --}}
<div class="mb-5 rounded-lg border {{ $coverage['percent'] === 100 ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }} px-5 py-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm font-medium {{ $coverage['percent'] === 100 ? 'text-emerald-900' : 'text-amber-900' }}">
                Cobertura visual: {{ $coverage['covered'] }} de {{ $coverage['total'] }} pasos
            </p>
            <p class="mt-1 text-sm {{ $coverage['percent'] === 100 ? 'text-emerald-800' : 'text-amber-800' }}">
                @if ($coverage['percent'] === 100)
                    Todos los pasos que mencionan una pieza física la muestran.
                @else
                    Faltan imágenes. Un paso que dice «revisa el cable HDMI» sin enseñarlo no sirve
                    al docente que no sabe qué es un HDMI.
                @endif
            </p>
        </div>

        <div class="flex items-center gap-3">
            <p class="text-3xl font-bold {{ $coverage['percent'] === 100 ? 'text-emerald-700' : 'text-amber-700' }}">
                {{ $coverage['percent'] }}%
            </p>
            @if ($coverage['percent'] < 100)
                <a href="{{ route('admin.media.coverage') }}"
                   class="rounded-md border border-amber-300 bg-white px-3 py-2 text-sm font-medium text-amber-900">
                    Ver qué falta
                </a>
            @endif
        </div>
    </div>
</div>

<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <form method="GET" class="flex flex-wrap items-center gap-2">
        <input type="search" name="q" value="{{ $q }}" placeholder="Buscar por código o título"
               class="rounded-md border-slate-300 px-3 py-2 text-sm">

        <select name="scope" data-auto-submit class="rounded-md border-slate-300 px-3 py-2 text-sm">
            <option value="">Todos los ámbitos</option>
            @foreach (['component' => 'Componente', 'category' => 'Categoría', 'equipment_type' => 'Tipo de equipo', 'generic' => 'Genérica'] as $v => $l)
                <option value="{{ $v }}" @selected($scope === $v)>{{ $l }}</option>
            @endforeach
        </select>

        <button type="submit" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">Buscar</button>
    </form>

    <a href="{{ route('admin.media.create') }}"
       class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">
        Subir imagen
    </a>
</div>

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
    @forelse ($assets as $asset)
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white {{ $asset->is_active ? '' : 'opacity-60' }}">
            <div class="flex aspect-video items-center justify-center bg-slate-100">
                <img src="{{ route('media.show', $asset) }}" alt="{{ $asset->alt_text }}"
                     loading="lazy" class="max-h-full max-w-full object-contain">
            </div>

            <div class="p-3">
                <p class="truncate font-mono text-xs text-slate-500">{{ $asset->code }}</p>
                <p class="mt-0.5 truncate text-sm font-medium">{{ $asset->title }}</p>

                <p class="mt-1 text-xs text-slate-500">
                    {{ $asset->scope_type }}@if ($asset->scope_key) · {{ $asset->scope_key }}@endif
                </p>

                <div class="mt-3 flex items-center gap-3 text-sm">
                    <a href="{{ route('admin.media.edit', $asset) }}" class="text-slate-700 underline underline-offset-2">Editar</a>

                    <form method="POST" action="{{ route('admin.media.toggle', $asset) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="text-slate-600 underline underline-offset-2">
                            {{ $asset->is_active ? 'Ocultar' : 'Mostrar' }}
                        </button>
                    </form>

                    @if (! $asset->is_active)
                        <span class="ml-auto rounded bg-slate-200 px-1.5 py-0.5 text-xs text-slate-600">oculta</span>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <p class="text-sm text-slate-500 sm:col-span-2 lg:col-span-3 xl:col-span-4">
            Todavía no hay imágenes cargadas.
        </p>
    @endforelse
</div>

<div class="mt-5">{{ $assets->links() }}</div>
@endsection
