@extends('layouts.app')
@section('title', $document->title)
@section('heading', $document->title)
@section('subheading', $document->category?->name ?? 'Sin categoría asignada')

@section('content')
<div class="grid gap-6 lg:grid-cols-3">

    <div class="space-y-4 lg:col-span-2">

        @if ($document->summary)
            <div class="rounded-lg border border-outline-variant bg-surface-lowest p-5">
                <p class="text-sm text-on-surface-variant">{{ $document->summary }}</p>
            </div>
        @endif

        <div class="rounded-lg border border-outline-variant bg-surface-lowest p-5">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-on-surface-variant">Versiones</h2>
                <p class="text-xs text-outline">La versión más reciente es la que usa el asistente</p>
            </div>

            <div class="space-y-3">
                @foreach ($document->versions as $version)
                    <div class="rounded-md border border-outline-variant p-4 {{ $loop->first ? 'bg-surface-low' : '' }}">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="font-medium text-primary">
                                    v{{ $version->version }}
                                    @if ($loop->first)
                                        <span class="ml-1 rounded bg-primary px-1.5 py-0.5 text-[10px] font-bold text-on-primary">ACTUAL</span>
                                    @endif
                                </p>
                                <p class="text-xs text-on-surface-variant">
                                    {{ $version->original_name }} ·
                                    {{ round($version->file_size / 1024) }} KB ·
                                    {{ $version->created_at->timezone(config('incidencias.display_timezone'))->format('d/m/Y H:i') }}
                                </p>
                            </div>

                            <span @class([
                                'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                'bg-ok-container text-on-ok-container' => $version->processing_status === 'indexed',
                                'bg-danger-container text-on-danger-container' => $version->processing_status === 'failed',
                                'bg-primary-container text-on-primary-container' => ! in_array($version->processing_status, ['indexed', 'failed']),
                            ])>{{ $statusLabels[$version->processing_status] ?? $version->processing_status }}</span>
                        </div>

                        @if ($version->processing_error)
                            {{-- El motivo del fallo va a la vista, no solo al log: quien
                                 subió el documento tiene que poder entender qué pasó. --}}
                            <p class="mt-2 rounded bg-danger-container px-3 py-2 text-xs text-on-danger-container">
                                {{ $version->processing_error }}
                            </p>
                        @endif

                        @if ($version->isIndexed())
                            <p class="mt-2 text-xs text-on-surface-variant">
                                {{ $version->chunk_count }} fragmentos indexados
                                @if ($version->indexed_at)
                                    · {{ $version->indexed_at->timezone(config('incidencias.display_timezone'))->format('d/m/Y H:i') }}
                                @endif
                            </p>
                        @endif

                        <div class="mt-3 flex gap-3 text-sm">
                            <a href="{{ route('admin.knowledge.download', $version) }}"
                               class="text-on-surface-variant underline-offset-2 hover:text-primary hover:underline">Descargar</a>

                            @can('knowledge.manage')
                                <form method="POST" action="{{ route('admin.knowledge.reindex', $version) }}">
                                    @csrf
                                    <button class="text-on-surface-variant underline-offset-2 hover:text-primary hover:underline">
                                        Reindexar
                                    </button>
                                </form>
                            @endcan
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="space-y-4">
        @can('knowledge.manage')
            <div class="rounded-lg border border-outline-variant bg-surface-lowest p-5">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-on-surface-variant">Disponibilidad</h2>

                @if ($document->isUsable())
                    <p class="mb-3 rounded bg-ok-container px-3 py-2 text-sm text-on-ok-container">
                        Disponible para el asistente.
                    </p>
                @else
                    <p class="mb-3 rounded bg-surface-mid px-3 py-2 text-sm text-on-surface-variant">
                        Todavía no lo usa el asistente. Necesita estar publicado <em>e</em> indexado.
                    </p>
                @endif

                @if ($document->status !== 'published')
                    <form method="POST" action="{{ route('admin.knowledge.publish', $document) }}" class="mb-2">
                        @csrf
                        <button class="w-full rounded-md bg-ok px-4 py-2 text-sm font-medium text-surface-lowest hover:bg-ok">
                            Publicar
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.knowledge.unpublish', $document) }}" class="mb-2">
                        @csrf
                        <button class="w-full rounded-md border border-outline-variant px-4 py-2 text-sm hover:bg-surface-low">
                            Despublicar
                        </button>
                    </form>
                @endif

                @if ($document->status !== 'archived')
                    <form method="POST" action="{{ route('admin.knowledge.archive', $document) }}"
                          data-confirm="¿Archivar? Dejará de alimentar al asistente, pero se conserva.">
                        @csrf
                        <button class="w-full rounded-md border border-outline-variant px-4 py-2 text-sm text-on-surface-variant hover:bg-surface-low">
                            Archivar
                        </button>
                    </form>
                @endif
            </div>

            <div class="rounded-lg border border-outline-variant bg-surface-lowest p-5">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-on-surface-variant">Nueva versión</h2>

                <form method="POST" action="{{ route('admin.knowledge.version', $document) }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <input type="file" name="document" required accept=".pdf,.docx,.doc,.txt,.md"
                           class="block w-full text-sm text-on-surface-variant file:mr-3 file:rounded-md file:border-0 file:bg-primary file:px-3 file:py-2 file:text-sm file:text-on-primary">

                    <p class="text-xs text-on-surface-variant">
                        No sustituye a la anterior: se añade. Las incidencias ya atendidas siguen
                        apuntando a la versión que de verdad se usó.
                    </p>

                    <button class="w-full rounded-md border border-outline-variant px-4 py-2 text-sm hover:bg-surface-low">Subir</button>
                    @error('document')<p class="text-xs text-danger">{{ $message }}</p>@enderror
                </form>
            </div>

            <a href="{{ route('admin.knowledge.edit', $document) }}"
               class="block rounded-md border border-outline-variant bg-surface-lowest px-4 py-2 text-center text-sm hover:bg-surface-low">
                Editar datos
            </a>
        @endcan

        <a href="{{ route('admin.knowledge.index') }}" class="block text-center text-sm text-on-surface-variant underline underline-offset-2">
            Volver a la lista
        </a>
    </div>
</div>
@endsection
