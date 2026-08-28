@extends('layouts.app')
@section('title', 'Incidencias')
@section('heading', 'Incidencias')
@section('subheading', 'Bandeja de atención')

@section('content')

<div class="mb-5 grid gap-3 sm:grid-cols-4">
    @foreach ([
        ['Abiertas', $counts['open'], null, 'text-primary'],
        ['Sin asignar', $counts['unassigned'], 'unassigned', 'text-warn'],
        ['Mías', $counts['mine'], 'mine', 'text-primary'],
        ['Clase detenida', $counts['blocking'], null, 'text-danger'],
    ] as [$label, $value, $scope, $color])
        <a href="{{ route('support.incidents.index', $scope ? ['scope' => $scope] : []) }}"
           class="glass px-4 py-3 transition hover:border-outline">
            <p class="label-tech text-on-surface-variant">{{ $label }}</p>
            <p class="mt-1 text-2xl font-semibold {{ $color }}">{{ $value }}</p>
        </a>
    @endforeach
</div>

<form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
    <input name="q" value="{{ $search }}" placeholder="Ticket o aula…"
           class="rounded-md border-outline-variant px-3 py-2 text-sm">

    <select name="status" class="rounded-md border-outline-variant px-3 py-2 text-sm">
        <option value="">Solo abiertas</option>
        @foreach ($statuses as $s)
            <option value="{{ $s->code }}" @selected(request('status') === $s->code)>{{ $s->name }}</option>
        @endforeach
    </select>

    <select name="category_id" class="rounded-md border-outline-variant px-3 py-2 text-sm">
        <option value="">Todas las categorías</option>
        @foreach ($categories as $c)
            <option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>
        @endforeach
    </select>

    <button class="rounded-md border border-outline-variant px-3 py-2 text-sm hover:bg-surface-low">Filtrar</button>

    @if (request()->hasAny(['q', 'status', 'category_id', 'scope']))
        <a href="{{ route('support.incidents.index') }}" class="text-sm text-on-surface-variant underline underline-offset-2">Limpiar</a>
    @endif
</form>

<div class="overflow-hidden glass">
    <table class="min-w-full divide-y divide-outline-variant text-sm">
        <thead class="bg-surface-low text-left label-tech text-on-surface-variant">
            <tr>
                <th class="px-4 py-3">Ticket</th>
                <th class="px-4 py-3">Aula</th>
                <th class="px-4 py-3">Problema</th>
                <th class="px-4 py-3">Prioridad</th>
                <th class="px-4 py-3">Estado</th>
                <th class="px-4 py-3">Asignado</th>
                <th class="px-4 py-3">Hace</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-surface-mid">
        @forelse ($incidents as $i)
            <tr class="cursor-pointer hover:bg-surface-low" data-row-href="{{ route('support.incidents.show', $i) }}">
                <td class="px-4 py-3 font-mono text-xs">
                    <a href="{{ route('support.incidents.show', $i) }}" class="font-semibold text-primary hover:underline">
                        #{{ $i->ticket_number ?? '—' }}
                    </a>
                    @if ($i->blocks_class)
                        {{-- Señal más importante de la bandeja: hay una clase
                             detenida ahora mismo. --}}
                        <span class="ml-1 inline-flex rounded bg-danger-container px-1.5 py-0.5 text-[10px] font-bold text-on-danger-container">CLASE</span>
                    @endif
                </td>
                <td class="px-4 py-3 font-mono text-xs font-semibold">{{ $i->room?->code }}</td>
                <td class="px-4 py-3">{{ $i->category?->name ?? '—' }}</td>
                <td class="px-4 py-3">
                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                          style="background-color: {{ $i->priority?->color }}22; color: {{ $i->priority?->color }}">
                        {{ $i->priority?->name ?? '—' }}
                    </span>
                </td>
                <td class="px-4 py-3">
                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                          style="background-color: {{ $i->status?->color }}22; color: {{ $i->status?->color }}">
                        {{ $i->status?->name }}
                    </span>
                </td>
                <td class="px-4 py-3 text-on-surface-variant">{{ $i->assignee?->name ?? 'Sin asignar' }}</td>
                <td class="px-4 py-3 text-on-surface-variant">{{ $i->created_at->diffForHumans(null, true) }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-4 py-10 text-center text-on-surface-variant">No hay incidencias que coincidan.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $incidents->links() }}</div>
@endsection
