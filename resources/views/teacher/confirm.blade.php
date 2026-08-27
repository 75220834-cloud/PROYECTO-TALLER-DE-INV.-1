@extends('teacher.layout')
@section('title', 'Confirma tu ubicación')
@section('step', 'Confirmación')

@section('content')
    {{-- Pantalla de confirmacion explicita, obligatoria antes de reportar
         (plan 7.1, paso 3). Es barata para el docente y evita la clase de
         error mas costosa del sistema: un tecnico caminando hasta el aula
         equivocada. --}}

    <h1 class="mb-5 text-2xl font-bold">Confirma dónde estás</h1>

    <div class="rounded-xl border-2 border-slate-900 bg-slate-50 px-5 py-6 text-center">
        <p class="text-sm text-slate-600">Estás solicitando asistencia desde</p>

        <p class="mt-2 font-mono text-3xl font-bold tracking-tight">{{ $room->code }}</p>

        <p class="mt-2 text-lg text-slate-700">
            {{ $room->floor?->building?->name }}<br>
            {{ $room->floor?->label }}
        </p>
    </div>

    <form method="POST" action="{{ route('teacher.confirm.store') }}" class="mt-6">
        @csrf
        <input type="hidden" name="site_id" value="{{ $room->floor?->building?->site_id }}">
        <input type="hidden" name="building_id" value="{{ $room->floor?->building_id }}">
        <input type="hidden" name="floor_id" value="{{ $room->floor_id }}">
        <input type="hidden" name="room_id" value="{{ $room->id }}">

        <button type="submit"
                class="flex min-h-[64px] w-full items-center justify-center rounded-xl bg-slate-900 px-5 py-4 text-lg font-semibold text-white transition active:scale-[.99] hover:bg-slate-800">
            Sí, es correcto
        </button>
    </form>

    <a href="{{ route('teacher.start') }}"
       class="mt-3 flex min-h-[56px] w-full items-center justify-center rounded-xl border-2 border-slate-300 px-5 py-3 text-lg font-medium text-slate-700 hover:bg-slate-50">
        Cambiar aula
    </a>
@endsection
