@extends('layouts.app')
@section('title', 'Señales de riesgo')
@section('heading', 'Señales de riesgo')
@section('subheading', 'Horizonte de ' . $horizon . ' días')

@section('content')

{{-- Advertencia deliberadamente arriba y no en letra pequeña al final.
     Quien lee esta pantalla debe saber ANTES de mirar los números qué son
     y qué no son (plan 15.2). --}}
<div class="mb-5 rounded-lg border border-warn bg-warn-container px-4 py-3 text-sm text-on-warn-container">
    <p class="font-medium">Cómo leer esta pantalla</p>
    <p class="mt-1">
        Estos números <strong>ordenan por dónde conviene empezar una revisión</strong>. No son
        probabilidades de avería: no están calibrados contra datos observados, porque el piloto
        todavía no tiene volumen suficiente para hacerlo. Un aula arriba en la lista es un aula
        que ha dado problemas recientemente, no un aula que vaya a fallar.
    </p>
</div>

@php
    $bandStyles = [
        'high' => ['Alto', 'bg-danger-container border-danger', 'text-danger'],
        'medium' => ['Medio', 'bg-warn-container border-warn', 'text-warn'],
        'low' => ['Bajo', 'bg-surface-low border-outline-variant', 'text-on-surface-variant'],
    ];
@endphp

<h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-on-surface-variant">Por aula</h2>

<div class="mb-8 grid gap-3 md:grid-cols-2">
    @forelse ($rooms as $score)
        @php [$bandLabel, $bandBox, $bandText] = $bandStyles[$score->risk_band] ?? $bandStyles['low']; @endphp

        <div class="rounded-lg border {{ $bandBox }} p-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="font-mono text-lg font-semibold">{{ $roomCodes[$score->scope_id] ?? 'Aula #' . $score->scope_id }}</p>
                    <p class="text-xs text-on-surface-variant">
                        {{ $score->model_code }} v{{ $score->model_version }} ·
                        {{ $score->computed_at?->timezone(config('incidencias.display_timezone'))->format('d/m/Y H:i') }}
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-2xl font-bold {{ $bandText }}">{{ number_format($score->score, 2) }}</p>
                    <p class="text-xs font-medium uppercase {{ $bandText }}">{{ $bandLabel }}</p>
                </div>
            </div>

            {{-- Los factores no son opcionales ni plegables: son la razón por
                 la que el número es utilizable. --}}
            <dl class="mt-3 space-y-1 border-t border-surface-lowest/60 pt-3 text-sm">
                @foreach ($score->factors as $factor)
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-on-surface-variant">
                            {{ $factor['label'] }}
                            <span class="text-on-surface-variant">— {{ $factor['detail'] }}</span>
                        </dt>
                        <dd class="shrink-0 font-mono text-xs text-on-surface-variant">
                            {{ $factor['contribution'] > 0 ? '+' . number_format($factor['contribution'], 2) : '—' }}
                        </dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @empty
        <p class="text-sm text-on-surface-variant">
            Todavía no se ha calculado ninguna señal. Se generan con <code class="font-mono">php artisan risk:compute</code>,
            que además corre de madrugada de forma automática.
        </p>
    @endforelse
</div>

<h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-on-surface-variant">Por aula y tipo de problema</h2>

<div class="grid gap-3 md:grid-cols-2">
    @forelse ($pairs as $score)
        @php [$bandLabel, $bandBox, $bandText] = $bandStyles[$score->risk_band] ?? $bandStyles['low']; @endphp

        <div class="rounded-lg border {{ $bandBox }} p-4">
            <div class="flex items-start justify-between gap-3">
                <p class="font-medium">
                    <span class="font-mono">{{ $roomCodes[$score->scope_id] ?? 'Aula #' . $score->scope_id }}</span>
                    <span class="text-on-surface-variant">·</span>
                    {{ $categoryNames[$score->category_id] ?? 'Sin categoría' }}
                </p>
                <div class="text-right">
                    <p class="text-xl font-bold {{ $bandText }}">{{ number_format($score->score, 2) }}</p>
                    <p class="text-xs font-medium uppercase {{ $bandText }}">{{ $bandLabel }}</p>
                </div>
            </div>

            <dl class="mt-3 space-y-1 border-t border-surface-lowest/60 pt-3 text-sm">
                @foreach ($score->factors as $factor)
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-on-surface-variant">
                            {{ $factor['label'] }}
                            <span class="text-on-surface-variant">— {{ $factor['detail'] }}</span>
                        </dt>
                        <dd class="shrink-0 font-mono text-xs text-on-surface-variant">
                            {{ $factor['contribution'] > 0 ? '+' . number_format($factor['contribution'], 2) : '—' }}
                        </dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @empty
        <p class="text-sm text-on-surface-variant">Sin pares con historial reciente.</p>
    @endforelse
</div>
@endsection
