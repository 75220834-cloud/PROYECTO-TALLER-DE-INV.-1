@extends('teacher.layout')
@section('title', 'Elige tu sede')
@section('step', 'Paso 1 de 4')

@section('content')
    <h1 class="mb-5 text-[28px] font-semibold leading-9 tracking-[-0.02em]">¿En qué sede estás?</h1>

    <div class="space-y-3">
        @foreach ($sites as $site)
            <x-big-option :href="route('teacher.buildings', ['site' => $site->id])">
                {{ $site->name }}
            </x-big-option>
        @endforeach
    </div>
@endsection
