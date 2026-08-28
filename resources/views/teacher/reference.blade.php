@extends('teacher.layout')
@section('title', $asset->title)
@section('step', 'Referencia')

@section('content')
    <h1 class="mb-4 text-2xl font-bold">{{ $asset->title }}</h1>

    <div class="overflow-hidden rounded-xl border-2 border-outline-variant bg-surface-lowest">
        @if ($asset->isInlineSvg())
            <div class="w-full">{!! file_get_contents(Storage::disk(config('incidencias.media.disk'))->path($asset->file_path)) !!}</div>
        @else
            <img src="{{ $asset->url() }}" alt="{{ $asset->alt_text }}" class="w-full">
        @endif
    </div>

    @if ($asset->caption)
        <p class="mt-4 text-lg leading-relaxed text-on-surface-variant">{{ $asset->caption }}</p>
    @endif

    <a href="{{ route('teacher.diagnostic') }}"
       class="mt-6 flex min-h-[60px] w-full items-center justify-center rounded-xl bg-primary px-5 py-3 text-lg font-semibold text-on-primary hover:opacity-90">
        Ya lo encontré, continuar
    </a>
@endsection
