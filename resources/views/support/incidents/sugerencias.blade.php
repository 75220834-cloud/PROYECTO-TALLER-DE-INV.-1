{{-- Sugerencias de la IA. Se inserta dentro de la ficha de la incidencia
     (ver show.blade.php): es un fragmento, no una página. --}}
<div class="space-y-5">
    <div>
        <h3 class="mb-2 text-sm font-semibold text-on-surface">Casos parecidos que ya se resolvieron</h3>

        @forelse ($sugerencias->cases as $caso)
            @php $pasado = $caso['incident']; @endphp
            <a href="{{ route('support.incidents.show', $pasado) }}"
               class="mb-2 block rounded-[var(--radius-md)] border border-outline-variant bg-surface-lowest px-4 py-3 transition hover:border-[var(--gold)]">
                <div class="flex flex-wrap items-center gap-2 text-xs text-on-surface-variant">
                    <span class="font-mono font-semibold text-on-surface">{{ $pasado->room?->code }}</span>
                    @if ($caso['sameRoom'])
                        <span class="chip bg-gold-container text-on-gold-container">Misma aula</span>
                    @endif
                    <span>{{ $pasado->resolved_at?->timezone(config('incidencias.display_timezone'))->format('d/m/Y') }}</span>
                    @if ($pasado->resolution_type)
                        <span>· {{ \App\Shared\Support\Label::for('resolution-types', $pasado->resolution_type) }}</span>
                    @endif
                </div>
                <p class="mt-1 text-sm text-on-surface">{{ \Illuminate\Support\Str::limit($pasado->resolution_notes, 220) }}</p>
                @if ($pasado->reported_description)
                    <p class="mt-1 text-xs text-outline">El docente dijo: «{{ \Illuminate\Support\Str::limit($pasado->reported_description, 120) }}»</p>
                @endif
            </a>
        @empty
            <p class="text-sm text-on-surface-variant">
                Todavía no hay casos resueltos de este tipo. Cuando se cierren con lo que se hizo anotado,
                aparecerán aquí.
            </p>
        @endforelse
    </div>

    <div>
        <h3 class="mb-2 text-sm font-semibold text-on-surface">Guía recomendada</h3>

        @forelse ($sugerencias->guides as $guia)
            @php $documento = $guia->chunk->version?->document; @endphp
            <div class="mb-2 rounded-[var(--radius-md)] border border-outline-variant bg-surface-lowest px-4 py-3">
                <p class="text-xs font-medium text-gold-text">{{ $guia->citation() }}</p>
                <p class="mt-1 text-sm text-on-surface">{{ \Illuminate\Support\Str::limit($guia->content(), 260) }}</p>
                @if ($documento)
                    @can('knowledge.view')
                        <a href="{{ route('admin.knowledge.show', $documento) }}"
                           class="mt-1 inline-block text-xs text-on-surface-variant underline underline-offset-2">Abrir la guía</a>
                    @endcan
                @endif
            </div>
        @empty
            <p class="text-sm text-on-surface-variant">
                Ninguna guía habla de este problema. Si la solución se repite, conviértela en artículo al
                cerrar la incidencia.
            </p>
        @endforelse
    </div>

    <p class="text-xs text-outline">
        {{-- Cómo se buscaron los casos solo se dice si hay casos: sin ninguno
             que comparar, la IA no llegó a usarse y diría algo falso. --}}
        @if ($sugerencias->cases->isNotEmpty())
            {{ $sugerencias->semantic
                ? 'Casos buscados por significado con la IA (Ollama).'
                : 'La IA no está disponible: los casos se buscaron por palabras en común.' }}
        @endif
        Son sugerencias: revísalas antes de aplicarlas.
    </p>
</div>
