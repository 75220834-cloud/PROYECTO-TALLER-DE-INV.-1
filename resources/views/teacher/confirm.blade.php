@extends('teacher.layout')
@section('title', 'Confirma tu ubicación')
@section('step', 'Confirmación')

@section('content')
    {{-- Pantalla de confirmacion explicita, obligatoria antes de reportar
         (plan 7.1, paso 3). Es barata para el docente y evita la clase de
         error mas costosa del sistema: un tecnico caminando hasta el aula
         equivocada. --}}

    <h1 class="mb-5 text-[28px] font-semibold leading-9 tracking-[-0.02em]">Confirma dónde estás</h1>

    <div class="glass px-5 py-6 text-center">
        <p class="text-sm text-on-surface-variant">Estás solicitando asistencia desde</p>

        <p class="mt-2 font-mono text-3xl font-bold tracking-tight">{{ $room->code }}</p>

        <p class="mt-2 text-lg text-on-surface-variant">
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
                class="flex min-h-[64px] w-full items-center justify-center btn-primary focus-ring px-5 py-4 text-lg font-semibold transition active:scale-[.99] hover:opacity-90">
            Sí, es correcto
        </button>
    </form>

    <a href="{{ route('teacher.start') }}"
       class="mt-3 flex min-h-[56px] w-full items-center justify-center rounded-xl border-2 border-outline-variant px-5 py-3 text-lg font-medium text-on-surface-variant hover:bg-surface-high">
        Cambiar aula
    </a>
@endsection
