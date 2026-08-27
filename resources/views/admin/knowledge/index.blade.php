@extends('layouts.app')
@section('title', 'Base de conocimiento')
@section('heading', 'Base de conocimiento')
@section('subheading', 'Documentos que alimentan al asistente')

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <form method="GET" class="flex flex-wrap items-center gap-2">
        <select name="status" class="rounded-md border-slate-300 px-3 py-2 text-sm">
            <option value="">Todos los estados</option>
            <option value="draft" @selected(request('status')==='draft')>Borrador</option>
            <option value="published" @selected(request('status')==='published')>Publicado</option>
            <option value="archived" @selected(request('status')==='archived')>Archivado</option>
        </select>
        <select name="category_id" class="rounded-md border-slate-300 px-3 py-2 text-sm">
            <option value="">Todas las categorías</option>
            @foreach ($categories as $c)
                <option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
        <button class="rounded-md border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Filtrar</button>
    </form>
    <a href="{{ route('admin.knowledge.create') }}" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Cargar documento</a>
</div>

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-4 py-3">Documento</th><th class="px-4 py-3">Tipo</th>
                <th class="px-4 py-3">Categoría</th><th class="px-4 py-3">Procesamiento</th>
                <th class="px-4 py-3">Estado</th><th class="px-4 py-3">Fragmentos</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($documents as $doc)
            @php $v = $doc->currentVersion(); @endphp
            <tr class="hover:bg-slate-50">
                <td class="px-4 py-3">
                    <a href="{{ route('admin.knowledge.show', $doc) }}" class="font-medium text-slate-900 hover:underline">{{ $doc->title }}</a>
                    @if ($v) <span class="ml-1 text-xs text-slate-400">v{{ $v->version }}</span> @endif
                </td>
                <td class="px-4 py-3 text-slate-600">{{ $types[$doc->type] ?? $doc->type }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $doc->category?->name ?? '—' }}</td>
                <td class="px-4 py-3">
                    @if ($v)
                        <span @class([
                            'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                            'bg-emerald-100 text-emerald-800' => $v->processing_status === 'indexed',
                            'bg-rose-100 text-rose-800' => $v->processing_status === 'failed',
                            'bg-sky-100 text-sky-800' => ! in_array($v->processing_status, ['indexed', 'failed']),
                        ])>{{ $statusLabels[$v->processing_status] ?? $v->processing_status }}</span>
                    @else
                        <span class="text-slate-400">—</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <span @class([
                        'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                        'bg-emerald-100 text-emerald-800' => $doc->status === 'published',
                        'bg-slate-200 text-slate-600' => $doc->status !== 'published',
                    ])>{{ ['draft'=>'Borrador','published'=>'Publicado','archived'=>'Archivado'][$doc->status] }}</span>
                </td>
                <td class="px-4 py-3 text-slate-500">{{ $v?->chunk_count ?? 0 }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">
                No hay documentos cargados todavía. El asistente funcionará igual, pero sin poder citar fuentes.
            </td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $documents->links() }}</div>
@endsection
