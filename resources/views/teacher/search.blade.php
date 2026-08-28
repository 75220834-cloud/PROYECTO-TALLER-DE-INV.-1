@extends('teacher.layout')
@section('title', 'Buscar aula')
@section('step', 'Buscar por código')

@section('content')
    <h1 class="mb-5 text-2xl font-bold">Escribe el código del aula</h1>

    <form method="GET" action="{{ route('teacher.search') }}" class="space-y-3">
        <input name="q" value="{{ $term }}" autofocus autocomplete="off"
               inputmode="text" autocapitalize="characters" placeholder="Por ejemplo: C305"
               class="block w-full rounded-xl border-2 border-outline-variant px-5 py-4 text-center font-mono text-2xl uppercase tracking-wide focus:border-primary focus:ring-0">

        <button type="submit"
                class="flex min-h-[60px] w-full items-center justify-center rounded-xl bg-primary px-5 py-4 text-lg font-semibold text-on-primary hover:opacity-90">
            Buscar
        </button>
    </form>

    @if ($term !== '')
        <div class="mt-6 space-y-3">
            @forelse ($results as $room)
                <x-big-option :href="route('teacher.confirm', [
                    'site' => $room->floor?->building?->site_id,
                    'building' => $room->floor?->building_id,
                    'floor' => $room->floor_id,
                    'room' => $room->id,
                ])">
                    <span class="font-mono font-semibold">{{ $room->code }}</span>
                    <span class="block text-sm font-normal text-on-surface-variant">{{ $room->floor?->building?->name }}</span>
                </x-big-option>
            @empty
                <p class="rounded-lg border-2 border-warn bg-warn-container px-4 py-3 text-base text-on-warn-container">
                    No encontramos ningún aula con ese código. Revisa que esté bien escrito
                    o elige tu aula paso a paso.
                </p>
            @endforelse
        </div>
    @endif
@endsection

@section('back')
    <a href="{{ route('teacher.start') }}" class="block text-center text-base text-on-surface-variant underline underline-offset-4">
        Elegir paso a paso
    </a>
@endsection
