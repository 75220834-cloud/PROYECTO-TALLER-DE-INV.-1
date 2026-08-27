@extends('layouts.app')
@section('title', 'Incidencias')
@section('heading', 'Incidencias')
@section('subheading', 'Bandeja de atención')

@section('content')

<div class="mb-5 grid gap-3 sm:grid-cols-4">
    @foreach ([
        ['Abiertas', $counts['open'], null, 'text-slate-900'],
        ['Sin asignar', $counts['unassigned'], 'unassigned', 'text-amber-700'],
        ['Mías', $counts['mine'], 'mine', 'text-sky-700'],
        ['Clase detenida', $counts['blocking'], null, 'text-rose-700'],
    ] as [$label, $value, $scope, $color])
        <a href="{{ route('support.incidents.index', $scope ? ['scope' => $scope] : []) }}"
           class="rounded-lg border border-slate-200 bg-white px-4 py-3 transition hover:border-slate-400">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ $label }}</p>
            <p class="mt-1 text-2xl font-semibold {{ $color }}">{{ $value }}</p>
        </a>
    @endforeach
</div>

<form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
    <input name="q" value="{{ $search }}" placeholder="Ticket o aula…"
           class="rounded-md border-slate-300 px-3 py-2 text-sm">

    <select name="status" class="rounded-md border-slate-300 px-3 py-2 text-sm">
        <option value="">Solo abiertas</option>
        @foreach ($statuses as $s)
            <option value="{{ $s->code }}" @selected(request('status') === $s->code)>{{ $s->name }}</option>
        @endforeach
    </select>

    <select name="category_id" class="rounded-md border-slate-300 px-3 py-2 text-sm">
        <option value="">Todas las categorías</option>
        @foreach ($categories as $c)
            <option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>
        @endforeach
    </select>

    <button class="rounded-md border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Filtrar</button>

    @if (request()->hasAny(['q', 'status', 'category_id', 'scope']))
        <a href="{{ route('support.incidents.index') }}" class="text-sm text-slate-500 underline underline-offset-2">Limpiar</a>
    @endif
</form>

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
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
        <tbody class="divide-y divide-slate-100">
        @forelse ($incidents as $i)
            <tr class="cursor-pointer hover:bg-slate-50" data-row-href="{{ route('support.incidents.show', $i) }}">
                <td class="px-4 py-3 font-mono text-xs">
                    <a href="{{ route('support.incidents.show', $i) }}" class="font-semibold text-slate-900 hover:underline">
                        #{{ $i->ticket_number ?? '—' }}
                    </a>
                    @if ($i->blocks_class)
                        {{-- Señal más importante de la bandeja: hay una clase
                             detenida ahora mismo. --}}
                        <span class="ml-1 inline-flex rounded bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold text-rose-800">CLASE</span>
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
                <td class="px-4 py-3 text-slate-500">{{ $i->assignee?->name ?? 'Sin asignar' }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $i->created_at->diffForHumans(null, true) }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">No hay incidencias que coincidan.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $incidents->links() }}</div>
@endsection
