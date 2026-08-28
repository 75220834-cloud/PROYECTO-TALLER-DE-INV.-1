@extends('teacher.layout')
@section('title', 'Elige tu aula')
@section('step', 'Paso 3 de 3')

@section('content')
    <h1 class="mb-5 text-2xl font-bold">¿En qué aula estás?</h1>

    @if ($rooms->isEmpty())
        <p class="rounded-lg border-2 border-warn bg-warn-container px-4 py-3 text-base text-on-warn-container">
            No hay aulas disponibles en este piso.
        </p>
    @else
        <div class="space-y-3">
            @foreach ($rooms as $room)
                <x-big-option :href="route('teacher.confirm', ['site' => $site, 'building' => $building, 'floor' => $floor, 'room' => $room->id])">
                    <span class="font-mono font-semibold">{{ $room->code }}</span>
                </x-big-option>
            @endforeach
        </div>
    @endif
@endsection

@section('back')
    <a href="{{ route('teacher.floors', ['site' => $site, 'building' => $building]) }}"
       class="block text-center text-base text-on-surface-variant underline underline-offset-4">
        Volver
    </a>
@endsection
