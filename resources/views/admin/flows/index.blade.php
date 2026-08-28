@extends('layouts.app')
@section('title', 'Procedimientos')
@section('heading', 'Procedimientos de diagnóstico')
@section('subheading', 'Los pasos que el sistema le va pidiendo al docente')

@section('content')

<div class="mb-5 rounded-lg border border-outline-variant bg-surface-lowest px-5 py-4 text-sm text-on-surface-variant">
    <p>
        Cada categoría puede tener un procedimiento. Si no lo tiene, el docente pasa directo a
        «¿se solucionó?» y el sistema no le propone nada — <strong class="text-on-surface">no se
        inventa pasos</strong>.
    </p>
    <p class="mt-2">
        Los procedimientos se <strong class="text-on-surface">versionan</strong>: una vez publicado,
        no se toca. Cada incidencia guarda qué versión ejecutó, y si se pudiera editar sobre la
        marcha, un cambio de hoy cambiaría el significado de los datos de la semana pasada.
    </p>
</div>

<div class="space-y-3">
    @foreach ($categories as $category)
        @php
            $flow = $flows[$category->id] ?? null;
            $publicada = $flow?->versions->firstWhere('published_at', '!=', null);
            $borradores = $flow?->versions->whereNull('published_at') ?? collect();
        @endphp

        <div class="rounded-lg border border-outline-variant bg-surface-lowest p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="font-medium text-on-surface">{{ $category->name }}</p>
                    <p class="mt-0.5 text-sm text-on-surface-variant">{{ $category->teacherText() }}</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @if ($publicada)
                        <a href="{{ route('admin.flows.show', $publicada) }}"
                           class="rounded-md border border-ok bg-ok-container px-3 py-1.5 text-sm font-medium text-on-ok-container">
                            En uso · v{{ $publicada->version }}
                        </a>

                        <form method="POST" action="{{ route('admin.flows.duplicate', $publicada) }}">
                            @csrf
                            <button type="submit" class="rounded-md border border-outline-variant px-3 py-1.5 text-sm">
                                Crear nueva versión
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.flows.store') }}">
                            @csrf
                            <input type="hidden" name="category_id" value="{{ $category->id }}">
                            <button type="submit" class="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-on-primary">
                                Crear procedimiento
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            @if ($borradores->isNotEmpty())
                <div class="mt-3 border-t border-outline-variant pt-3">
                    <p class="text-xs uppercase tracking-wide text-on-surface-variant">Borradores</p>
                    <div class="mt-1 flex flex-wrap gap-2">
                        @foreach ($borradores as $borrador)
                            <a href="{{ route('admin.flows.show', $borrador) }}"
                               class="rounded-md border border-warn bg-warn-container px-3 py-1 text-sm text-on-warn-container">
                                v{{ $borrador->version }} · {{ $borrador->steps()->count() }} pasos
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if (! $publicada && $borradores->isEmpty())
                <p class="mt-3 text-sm text-on-surface-variant">
                    Sin procedimiento. El diagnóstico guiado no se ofrece para esta categoría.
                </p>
            @endif
        </div>
    @endforeach
</div>
@endsection
