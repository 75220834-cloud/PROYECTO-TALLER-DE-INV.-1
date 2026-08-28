@extends('layouts.app')
@section('title', $document->exists ? 'Editar documento' : 'Cargar documento')
@section('heading', $document->exists ? 'Editar documento' : 'Cargar documento')

@section('content')
<form method="POST"
      action="{{ $document->exists ? route('admin.knowledge.update', $document) : route('admin.knowledge.store') }}"
      enctype="multipart/form-data"
      class="max-w-lg space-y-4 rounded-lg border border-outline-variant bg-surface-lowest p-6">
    @csrf
    @if ($document->exists) @method('PUT') @endif

    <x-field label="Título" name="title" :required="true">
        <input id="title" name="title" value="{{ old('title', $document->title) }}" required maxlength="200"
               class="mt-1 block w-full rounded-md border-outline-variant px-3 py-2 text-sm shadow-sm">
    </x-field>

    <x-field label="Tipo" name="type" :required="true">
        <select id="type" name="type" required class="mt-1 block w-full rounded-md border-outline-variant px-3 py-2 text-sm">
            @foreach ($types as $value => $label)
                <option value="{{ $value }}" @selected(old('type', $document->type) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </x-field>

    <x-field label="Categoría" name="category_id"
             help="Acotar el documento a una categoría hace la búsqueda más precisa: al atender un problema de audio no hace falta mirar el manual del proyector.">
        <select id="category_id" name="category_id" class="mt-1 block w-full rounded-md border-outline-variant px-3 py-2 text-sm">
            <option value="">Sin categoría (aplica a todo)</option>
            @foreach ($categories as $c)
                <option value="{{ $c->id }}" @selected(old('category_id', $document->category_id) == $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
    </x-field>

    <x-field label="Resumen" name="summary" help="Una o dos frases sobre qué contiene. Opcional.">
        <textarea id="summary" name="summary" rows="2" maxlength="1000"
                  class="mt-1 block w-full rounded-md border-outline-variant px-3 py-2 text-sm">{{ old('summary', $document->summary) }}</textarea>
    </x-field>

    @unless ($document->exists)
        <x-field label="Archivo" name="document" :required="true"
                 help="PDF, Word, texto o Markdown. Máximo 20 MB. Un PDF escaneado sin capa de texto no se puede indexar.">
            <input id="document" name="document" type="file" required accept=".pdf,.docx,.doc,.txt,.md"
                   class="mt-1 block w-full text-sm text-on-surface-variant file:mr-3 file:rounded-md file:border-0 file:bg-primary file:px-3 file:py-2 file:text-sm file:text-on-primary">
        </x-field>
    @endunless

    <div class="flex gap-3 pt-2">
        <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-on-primary hover:bg-on-surface">
            {{ $document->exists ? 'Guardar' : 'Cargar y procesar' }}
        </button>
        <a href="{{ route('admin.knowledge.index') }}" class="rounded-md border border-outline-variant px-4 py-2 text-sm text-on-surface-variant hover:bg-surface-low">Cancelar</a>
    </div>
</form>
@endsection
