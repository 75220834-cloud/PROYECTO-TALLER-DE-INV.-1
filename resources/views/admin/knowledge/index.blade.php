@extends('layouts.app')
@section('title', 'Base de conocimiento')
@section('heading', 'Base de conocimiento')
@section('subheading', 'Documentos que alimentan al asistente')

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <form method="GET" class="flex flex-wrap items-center gap-2">
        <select name="status" class="rounded-md border-outline-variant px-3 py-2 text-sm">
            <option value="">Todos los estados</option>
            <option value="draft" @selected(request('status')==='draft')>Borrador</option>
            <option value="published" @selected(request('status')==='published')>Publicado</option>
            <option value="archived" @selected(request('status')==='archived')>Archivado</option>
        </select>
        <select name="category_id" class="rounded-md border-outline-variant px-3 py-2 text-sm">
            <option value="">Todas las categorías</option>
            @foreach ($categories as $c)
                <option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
        <button class="rounded-md border border-outline-variant px-3 py-2 text-sm hover:bg-surface-low">Filtrar</button>
    </form>
    <a href="{{ route('admin.knowledge.create') }}" class="btn-primary focus-ring px-4 py-2 text-sm font-medium hover:bg-on-surface">Cargar documento</a>
</div>

<div class="overflow-hidden glass">
    <table class="min-w-full divide-y divide-outline-variant text-sm">
        <thead class="bg-surface-low text-left label-tech text-on-surface-variant">
            <tr>
                <th class="px-4 py-3">Documento</th><th class="px-4 py-3">Tipo</th>
                <th class="px-4 py-3">Categoría</th><th class="px-4 py-3">Procesamiento</th>
                <th class="px-4 py-3">Estado</th><th class="px-4 py-3">Fragmentos</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-surface-mid">
        @forelse ($documents as $doc)
            @php $v = $doc->currentVersion(); @endphp
            <tr class="hover:bg-surface-low">
                <td class="px-4 py-3">
                    <a href="{{ route('admin.knowledge.show', $doc) }}" class="font-medium text-primary hover:underline">{{ $doc->title }}</a>
                    @if ($v) <span class="ml-1 text-xs text-outline">v{{ $v->version }}</span> @endif
                </td>
                <td class="px-4 py-3 text-on-surface-variant">{{ $types[$doc->type] ?? $doc->type }}</td>
                <td class="px-4 py-3 text-on-surface-variant">{{ $doc->category?->name ?? '—' }}</td>
                <td class="px-4 py-3">
                    @if ($v)
                        <span @class([
                            'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                            'bg-ok-container text-on-ok-container' => $v->processing_status === 'indexed',
                            'bg-danger-container text-on-danger-container' => $v->processing_status === 'failed',
                            'bg-primary-container text-on-primary-container' => ! in_array($v->processing_status, ['indexed', 'failed']),
                        ])>{{ $statusLabels[$v->processing_status] ?? $v->processing_status }}</span>
                    @else
                        <span class="text-outline">—</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <span @class([
                        'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                        'bg-ok-container text-on-ok-container' => $doc->status === 'published',
                        'bg-outline-variant text-on-surface-variant' => $doc->status !== 'published',
                    ])>{{ ['draft'=>'Borrador','published'=>'Publicado','archived'=>'Archivado'][$doc->status] }}</span>
                </td>
                <td class="px-4 py-3 text-on-surface-variant">{{ $v?->chunk_count ?? 0 }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-10 text-center text-on-surface-variant">
                No hay documentos cargados todavía. El asistente funcionará igual, pero sin poder citar fuentes.
            </td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $documents->links() }}</div>
@endsection
