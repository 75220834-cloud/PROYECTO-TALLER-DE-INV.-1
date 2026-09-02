@extends('layouts.app')
@section('title', 'Convertir en artículo')
@section('heading', 'Convertir esta solución en artículo')
@section('subheading', 'Ticket #' . ($incident->ticket_number ?? $incident->id) . ' · ' . ($incident->room?->code ?? ''))

@section('content')
<div class="grid gap-6 lg:grid-cols-3">

    <form method="POST" action="{{ route('admin.knowledge.from-incident.store', $incident) }}"
          class="glass space-y-4 p-5 lg:col-span-2">
        @csrf

        <div>
            <label for="title" class="label-tech text-on-surface-variant">Título</label>
            <input id="title" name="title" required maxlength="200" value="{{ old('title', $titulo) }}"
                   class="mt-1 block w-full rounded-md border-outline-variant px-3 py-2">
            <p class="mt-1 text-xs text-on-surface-variant">
                Escríbelo como lo buscaría alguien que tiene el problema delante, no como el
                nombre de una categoría. «El proyector se ve morado» encuentra más que «Video».
            </p>
            @error('title')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="summary" class="label-tech text-on-surface-variant">Resumen (opcional)</label>
            <input id="summary" name="summary" maxlength="500" value="{{ old('summary') }}"
                   class="mt-1 block w-full rounded-md border-outline-variant px-3 py-2">
        </div>

        <div>
            <label for="body" class="label-tech text-on-surface-variant">Contenido</label>
            <textarea id="body" name="body" rows="18" required
                      class="mt-1 block w-full rounded-md border-outline-variant px-3 py-2 font-mono text-sm">{{ old('body', $cuerpo) }}</textarea>
            <p class="mt-1 text-xs text-on-surface-variant">
                Admite Markdown: las líneas que empiezan con <code>##</code> se usan como títulos de
                sección y ayudan al asistente a citar el trozo correcto.
            </p>
            @error('body')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
        </div>

        <button class="btn-primary focus-ring px-5 py-2.5 text-sm font-medium">
            Crear como borrador
        </button>
    </form>

    <div class="space-y-4">

        {{-- Lo que el sistema ya sabe del ticket, al lado, para que el técnico
             no tenga que abrir otra pestaña mientras redacta. --}}
        <div class="glass p-5">
            <h2 class="label-tech mb-3 text-on-surface-variant">De qué ticket sale</h2>

            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-on-surface-variant">Aula</dt>
                    <dd class="font-mono">{{ $incident->room?->code ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-on-surface-variant">Problema</dt>
                    <dd>{{ $incident->category?->name ?? 'Sin clasificar' }}</dd>
                </div>
                @if ($incident->reported_description)
                    <div>
                        <dt class="text-on-surface-variant">En palabras del docente</dt>
                        <dd>{{ $incident->reported_description }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-on-surface-variant">Lo que se hizo</dt>
                    <dd>{{ $incident->resolution_notes }}</dd>
                </div>
            </dl>
        </div>

        {{-- Advertencia deliberada, no relleno legal. Lo que se guarde aquí
             el asistente se lo va a decir a docentes que no estuvieron en
             esa aula. --}}
        <div class="glass border-warn/60 bg-warn-container/60 p-5 text-sm text-on-warn-container">
            <p class="font-semibold">Antes de guardar</p>
            <ul class="mt-2 list-disc space-y-1 pl-4">
                <li>Quita nombres de personas y datos de contacto.</li>
                <li>Si lo que hiciste fue un apaño para esa aula concreta, dilo en el texto.</li>
                <li>Escribe los pasos completos: quien lea esto no estuvo ahí.</li>
            </ul>
            <p class="mt-3">
                Se guarda como <strong>borrador</strong>. El asistente no lo usará hasta que
                alguien lo publique.
            </p>
        </div>

        <a href="{{ route('support.incidents.show', $incident) }}"
           class="block text-center text-sm text-on-surface-variant underline underline-offset-2">
            Volver al ticket
        </a>
    </div>
</div>
@endsection
