@extends('teacher.layout')
@section('title', 'Elige tu pabellón')
@section('step', 'Paso 1 de 3')

@section('content')
    <h1 class="mb-5 text-2xl font-bold">¿En qué pabellón estás?</h1>

    <div class="space-y-3">
        @foreach ($buildings as $building)
            <x-big-option :href="route('teacher.floors', ['site' => $site, 'building' => $building->id])">
                {{ $building->name }}
            </x-big-option>
        @endforeach
    </div>

    <a href="{{ route('teacher.search') }}"
       class="mt-6 block text-center text-base text-on-surface-variant underline underline-offset-4">
        Prefiero escribir el código del aula
    </a>
@endsection
