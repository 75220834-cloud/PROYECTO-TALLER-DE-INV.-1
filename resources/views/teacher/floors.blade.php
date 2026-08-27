@extends('teacher.layout')
@section('title', 'Elige tu piso')
@section('step', 'Paso 2 de 3')

@section('content')
    <h1 class="mb-5 text-2xl font-bold">¿En qué piso estás?</h1>

    <div class="space-y-3">
        @foreach ($floors as $floor)
            <x-big-option :href="route('teacher.rooms', ['site' => $site, 'building' => $building, 'floor' => $floor->id])">
                {{ $floor->label }}
            </x-big-option>
        @endforeach
    </div>
@endsection

@section('back')
    <a href="{{ route('teacher.buildings', ['site' => $site]) }}"
       class="block text-center text-base text-slate-600 underline underline-offset-4">
        Volver
    </a>
@endsection
