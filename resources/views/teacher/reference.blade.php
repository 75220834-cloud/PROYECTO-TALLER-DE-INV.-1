@extends('teacher.layout')
@section('title', $asset->title)
@section('step', 'Referencia')

@section('content')
    <h1 class="mb-4 text-2xl font-bold">{{ $asset->title }}</h1>

    <div class="overflow-hidden rounded-xl border-2 border-slate-300 bg-white">
        @if ($asset->isInlineSvg())
            <div class="w-full">{!! file_get_contents(Storage::disk(config('incidencias.media.disk'))->path($asset->file_path)) !!}</div>
        @else
            <img src="{{ $asset->url() }}" alt="{{ $asset->alt_text }}" class="w-full">
        @endif
    </div>

    @if ($asset->caption)
        <p class="mt-4 text-lg leading-relaxed text-slate-700">{{ $asset->caption }}</p>
    @endif

    <a href="{{ route('teacher.diagnostic') }}"
       class="mt-6 flex min-h-[60px] w-full items-center justify-center rounded-xl bg-slate-900 px-5 py-3 text-lg font-semibold text-white hover:bg-slate-800">
        Ya lo encontré, continuar
    </a>
@endsection
