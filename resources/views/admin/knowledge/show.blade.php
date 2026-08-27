@extends('layouts.app')
@section('title', $document->title)
@section('heading', $document->title)
@section('subheading', $document->category?->name ?? 'Sin categoría asignada')

@section('content')
<div class="grid gap-6 lg:grid-cols-3">

    <div class="space-y-4 lg:col-span-2">

        @if ($document->summary)
            <div class="rounded-lg border border-slate-200 bg-white p-5">
                <p class="text-sm text-slate-700">{{ $document->summary }}</p>
            </div>
        @endif

        <div class="rounded-lg border border-slate-200 bg-white p-5">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Versiones</h2>
                <p class="text-xs text-slate-400">La versión más reciente es la que usa el asistente</p>
            </div>

            <div class="space-y-3">
                @foreach ($document->versions as $version)
                    <div class="rounded-md border border-slate-200 p-4 {{ $loop->first ? 'bg-slate-50' : '' }}">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="font-medium text-slate-900">
                                    v{{ $version->version }}
                                    @if ($loop->first)
                                        <span class="ml-1 rounded bg-slate-900 px-1.5 py-0.5 text-[10px] font-bold text-white">ACTUAL</span>
                                    @endif
                                </p>
                                <p class="text-xs text-slate-500">
                                    {{ $version->original_name }} ·
                                    {{ round($version->file_size / 1024) }} KB ·
                                    {{ $version->created_at->timezone(config('incidencias.display_timezone'))->format('d/m/Y H:i') }}
                                </p>
                            </div>

                            <span @class([
                                'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                'bg-emerald-100 text-emerald-800' => $version->processing_status === 'indexed',
                                'bg-rose-100 text-rose-800' => $version->processing_status === 'failed',
                                'bg-sky-100 text-sky-800' => ! in_array($version->processing_status, ['indexed', 'failed']),
                            ])>{{ $statusLabels[$version->processing_status] ?? $version->processing_status }}</span>
                        </div>

                        @if ($version->processing_error)
                            {{-- El motivo del fallo va a la vista, no solo al log: quien
                                 subió el documento tiene que poder entender qué pasó. --}}
                            <p class="mt-2 rounded bg-rose-50 px-3 py-2 text-xs text-rose-800">
                                {{ $version->processing_error }}
                            </p>
                        @endif

                        @if ($version->isIndexed())
                            <p class="mt-2 text-xs text-slate-500">
                                {{ $version->chunk_count }} fragmentos indexados
                                @if ($version->indexed_at)
                                    · {{ $version->indexed_at->timezone(config('incidencias.display_timezone'))->format('d/m/Y H:i') }}
                                @endif
                            </p>
                        @endif

                        <div class="mt-3 flex gap-3 text-sm">
                            <a href="{{ route('admin.knowledge.download', $version) }}"
                               class="text-slate-600 underline-offset-2 hover:text-slate-900 hover:underline">Descargar</a>

                            @can('knowledge.manage')
                                <form method="POST" action="{{ route('admin.knowledge.reindex', $version) }}">
                                    @csrf
                                    <button class="text-slate-600 underline-offset-2 hover:text-slate-900 hover:underline">
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
            <div class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Disponibilidad</h2>

                @if ($document->isUsable())
                    <p class="mb-3 rounded bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                        Disponible para el asistente.
                    </p>
                @else
                    <p class="mb-3 rounded bg-slate-100 px-3 py-2 text-sm text-slate-600">
                        Todavía no lo usa el asistente. Necesita estar publicado <em>e</em> indexado.
                    </p>
                @endif

                @if ($document->status !== 'published')
                    <form method="POST" action="{{ route('admin.knowledge.publish', $document) }}" class="mb-2">
                        @csrf
                        <button class="w-full rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                            Publicar
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.knowledge.unpublish', $document) }}" class="mb-2">
                        @csrf
                        <button class="w-full rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">
                            Despublicar
                        </button>
                    </form>
                @endif

                @if ($document->status !== 'archived')
                    <form method="POST" action="{{ route('admin.knowledge.archive', $document) }}"
                          onsubmit="return confirm('¿Archivar? Dejará de alimentar al asistente, pero se conserva.')">
                        @csrf
                        <button class="w-full rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">
                            Archivar
                        </button>
                    </form>
                @endif
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Nueva versión</h2>

                <form method="POST" action="{{ route('admin.knowledge.version', $document) }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <input type="file" name="document" required accept=".pdf,.docx,.doc,.txt,.md"
                           class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-900 file:px-3 file:py-2 file:text-sm file:text-white">

                    <p class="text-xs text-slate-500">
                        No sustituye a la anterior: se añade. Las incidencias ya atendidas siguen
                        apuntando a la versión que de verdad se usó.
                    </p>

                    <button class="w-full rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Subir</button>
                    @error('document')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                </form>
            </div>

            <a href="{{ route('admin.knowledge.edit', $document) }}"
               class="block rounded-md border border-slate-300 bg-white px-4 py-2 text-center text-sm hover:bg-slate-50">
                Editar datos
            </a>
        @endcan

        <a href="{{ route('admin.knowledge.index') }}" class="block text-center text-sm text-slate-500 underline underline-offset-2">
            Volver a la lista
        </a>
    </div>
</div>
@endsection
