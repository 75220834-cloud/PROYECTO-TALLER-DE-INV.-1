@extends('teacher.layout')
@section('title', 'Vamos a revisar')
@section('step', $incident->room?->code . ' · paso ' . $stepNumber)

@section('content')

    {{-- LA IMAGEN VA ARRIBA Y GRANDE.
         Decir "revisa el cable HDMI" solo funciona para quien ya sabe qué es
         un HDMI: justo el docente que no necesita el sistema. La imagen es
         lo que hace el paso utilizable, no un adorno (plan §13.6). --}}
    @if ($primary)
        <figure class="mb-5">
            <div class="overflow-hidden rounded-xl border-2 border-outline-variant bg-surface-lowest">
                @if ($primary->isInlineSvg())
                    <div class="w-full">{!! file_get_contents(Storage::disk(config('incidencias.media.disk'))->path($primary->file_path)) !!}</div>
                @else
                    <img src="{{ $primary->url() }}" alt="{{ $primary->alt_text }}"
                         class="w-full" loading="eager" decoding="async">
                @endif
            </div>

            @if ($primary->caption)
                <figcaption class="mt-2 text-center text-base text-on-surface-variant">{{ $primary->caption }}</figcaption>
            @endif
        </figure>
    @endif

    <h1 class="mb-2 text-2xl font-bold leading-snug">{{ $step->prompt_text }}</h1>

    @if ($step->help_text)
        <p class="mb-5 text-lg leading-relaxed text-on-surface-variant">{{ $step->help_text }}</p>
    @endif

    <form method="POST" action="{{ route('teacher.diagnostic.answer') }}" class="space-y-3">
        @csrf
        <input type="hidden" name="step_id" value="{{ $step->id }}">
        <input type="hidden" name="shown_at" value="{{ $shownAt }}">
        <input type="hidden" name="media_shown" value="{{ $primary?->id }}">

        @foreach ($step->options() as $option)
            {{-- Cada respuesta es un botón de envío directo: sin marcar y
                 luego confirmar. Un toque menos por paso, y son muchos pasos. --}}
            <button type="submit" name="answer" value="{{ $option['value'] }}"
                    class="flex min-h-[64px] w-full items-center justify-between gap-3 rounded-xl border-2 border-outline-variant bg-surface-lowest px-5 py-4 text-left text-lg font-medium text-on-surface transition active:scale-[.99] hover:border-primary hover:bg-surface-high">
                <span>{{ $option['label'] }}</span>
                <span aria-hidden="true" class="text-2xl leading-none text-outline">&rsaquo;</span>
            </button>
        @endforeach
    </form>

    <div class="mt-6 space-y-2">
        @if ($reference)
            {{-- Resuelve el problema de vocabulario sin obligar al docente a
                 admitir que no conoce el nombre: no pregunta nada, muestra. --}}
            <a href="{{ route('teacher.diagnostic.reference', ['componentKey' => $step->component_key]) }}"
               class="block text-center text-base text-on-surface-variant underline underline-offset-4">
                ¿Cuál es esa pieza?
            </a>
        @endif

        @if ($alternates->isNotEmpty())
            <details class="text-center">
                <summary class="cursor-pointer text-base text-on-surface-variant underline underline-offset-4">Ver otra vista</summary>
                <div class="mt-3 space-y-3">
                    @foreach ($alternates as $alt)
                        <figure>
                            <img src="{{ $alt->url() }}" alt="{{ $alt->alt_text }}"
                                 class="w-full rounded-xl border-2 border-outline-variant" loading="lazy">
                            @if ($alt->caption)
                                <figcaption class="mt-1 text-sm text-on-surface-variant">{{ $alt->caption }}</figcaption>
                            @endif
                        </figure>
                    @endforeach
                </div>
            </details>
        @endif
    </div>
@endsection

@section('back')
    {{-- Salida siempre disponible. Un docente con una clase esperando no
         puede quedar obligado a terminar un cuestionario. --}}
    <a href="{{ route('teacher.escalate') }}"
       class="block text-center text-base text-on-surface-variant underline underline-offset-4">
        Prefiero pedir soporte ahora
    </a>
@endsection
