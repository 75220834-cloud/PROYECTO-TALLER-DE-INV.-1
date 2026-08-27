@extends('teacher.layout')
@section('title', 'Buscar aula')
@section('step', 'Buscar por código')

@section('content')
    <h1 class="mb-5 text-2xl font-bold">Escribe el código del aula</h1>

    <form method="GET" action="{{ route('teacher.search') }}" class="space-y-3">
        <input name="q" value="{{ $term }}" autofocus autocomplete="off"
               inputmode="text" autocapitalize="characters" placeholder="Por ejemplo: C305"
               class="block w-full rounded-xl border-2 border-slate-300 px-5 py-4 text-center font-mono text-2xl uppercase tracking-wide focus:border-slate-900 focus:ring-0">

        <button type="submit"
                class="flex min-h-[60px] w-full items-center justify-center rounded-xl bg-slate-900 px-5 py-4 text-lg font-semibold text-white hover:bg-slate-800">
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
                    <span class="block text-sm font-normal text-slate-500">{{ $room->floor?->building?->name }}</span>
                </x-big-option>
            @empty
                <p class="rounded-lg border-2 border-amber-300 bg-amber-50 px-4 py-3 text-base text-amber-900">
                    No encontramos ningún aula con ese código. Revisa que esté bien escrito
                    o elige tu aula paso a paso.
                </p>
            @endforelse
        </div>
    @endif
@endsection

@section('back')
    <a href="{{ route('teacher.start') }}" class="block text-center text-base text-slate-600 underline underline-offset-4">
        Elegir paso a paso
    </a>
@endsection
