@extends('layouts.app')
@section('title', 'Procedimiento v' . $version->version)
@section('heading', $version->flow->category->name . ' · versión ' . $version->version)
@section('subheading', $version->published_at ? 'Publicada — en uso por los docentes' : 'Borrador — todavía no la ve nadie')

@section('content')

@if ($version->published_at)
    {{-- Se explica la inmutabilidad donde el usuario iría a buscar el botón
         de editar, no en un manual aparte. --}}
    <div class="mb-5 rounded-lg border border-ok bg-ok-container px-5 py-4 text-sm text-on-ok-container">
        <p class="font-medium">Esta versión está en uso y no se puede modificar.</p>
        <p class="mt-1">
            Hay incidencias cerradas que la ejecutaron: cambiarla haría que sus respuestas guardadas
            dejaran de significar lo que significaban. Para cambiar el procedimiento, créale una
            versión nueva desde la lista.
        </p>
    </div>
@else
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-warn bg-warn-container px-5 py-4">
        <p class="text-sm text-on-warn-container">
            Borrador. Ningún docente lo ve todavía. Al publicarlo reemplaza a la versión en uso.
        </p>

        <div class="flex items-center gap-2">
            <a href="{{ route('admin.flows.steps.create', $version) }}"
               class="rounded-md border border-outline-variant bg-surface-lowest px-3 py-1.5 text-sm font-medium">
                Añadir paso
            </a>

            <form method="POST" action="{{ route('admin.flows.publish', $version) }}"
                  data-confirm="¿Publicar? Reemplazará a la versión que están usando los docentes.">
                @csrf
                <button type="submit" class="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-on-primary">
                    Publicar
                </button>
            </form>
        </div>
    </div>
@endif

@forelse ($steps as $step)
    <div class="mb-3 rounded-lg border border-outline-variant bg-surface-lowest p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <p class="font-mono text-xs text-on-surface-variant">
                    {{ $step->sort_order }} · {{ $step->step_key }}
                    @if ($step->component_key)
                        · muestra <span class="font-semibold">{{ $step->component_key }}</span>
                    @endif
                </p>

                <p class="mt-1 text-base font-medium text-on-surface">{{ $step->prompt_text }}</p>

                @if ($step->help_text)
                    <p class="mt-1 text-sm text-on-surface-variant">{{ $step->help_text }}</p>
                @endif

                @if ($step->is_terminal)
                    <p class="mt-2 inline-block rounded bg-surface-mid px-2 py-0.5 text-xs text-on-surface-variant">
                        Cierra el procedimiento ·
                        {{ $step->terminal_outcome === 'resolved' ? 'da por resuelto' : 'avisa a soporte' }}
                    </p>
                @else
                    <ul class="mt-2 space-y-1 text-sm">
                        @foreach ($step->answer_options ?? [] as $opcion)
                            @php $destino = ($step->next_step_map ?? [])[$opcion['value']] ?? null; @endphp
                            <li class="text-on-surface-variant">
                                · {{ $opcion['label'] }}
                                <span class="font-mono text-xs">
                                    → {{ $destino ?? '¿se solucionó?' }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            @if (! $version->published_at)
                <div class="flex shrink-0 items-center gap-3 text-sm">
                    <a href="{{ route('admin.flows.steps.edit', $step) }}"
                       class="text-on-surface-variant underline underline-offset-2">Editar</a>

                    <form method="POST" action="{{ route('admin.flows.steps.destroy', $step) }}"
                          data-confirm="¿Eliminar este paso?">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-danger underline underline-offset-2">Eliminar</button>
                    </form>
                </div>
            @endif
        </div>
    </div>
@empty
    <p class="rounded-lg border border-outline-variant bg-surface-lowest px-5 py-6 text-center text-sm text-on-surface-variant">
        Todavía no hay pasos. El primero de la lista es por donde empieza el docente.
    </p>
@endforelse

<a href="{{ route('admin.flows.index') }}" class="mt-5 inline-block text-sm text-on-surface-variant underline underline-offset-2">
    Volver a procedimientos
</a>
@endsection
