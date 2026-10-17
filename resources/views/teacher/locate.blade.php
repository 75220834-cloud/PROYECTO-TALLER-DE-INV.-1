@extends('teacher.layout')
@section('title', '¿Dónde estás?')
@section('step', 'Ubica tu aula')

@section('content')
    <h1 class="mb-2 text-[28px] font-semibold leading-9 tracking-[-0.02em]">¿Dónde estás?</h1>
    <p class="mb-6 text-base text-on-surface-variant">
        Cada pregunta aparece al responder la anterior.
    </p>

    @if (session('error'))
        <div class="mb-5 glass border-danger/70 bg-danger-container/70 px-4 py-3">
            <p class="text-base text-on-danger-container">{{ session('error') }}</p>
        </div>
    @endif

    {{-- data-cascade marca el formulario entero. Cada lista declara de quién
         depende con data-depends-on, y cada opción a qué padre pertenece con
         data-parent. El JavaScript no sabe nada de sedes ni de aulas: solo
         filtra por esos atributos, así que añadir un nivel más el día de
         mañana no le obliga a cambiar. --}}
    <form method="POST" action="{{ route('teacher.locate') }}" data-cascade class="space-y-5">
        @csrf

        {{-- SEDE. Con una sola, ya viene elegida y la pregunta no se muestra:
             una única opción no es una decisión, y el docente tiene la clase
             esperando. Es la misma regla que aplica la cascada a pabellones,
             pisos y aulas. El día que exista una segunda sede, la pregunta
             aparece sola: no hay nada atado a una sede. --}}
        @php($sedeUnica = $sites->count() === 1)
        <div @if ($sedeUnica) hidden data-sede-unica @endif>
            <label for="site_id" class="mb-2 block text-lg font-semibold">
                ¿En qué sede estás?
            </label>
            <select id="site_id" name="site_id" required
                    class="focus-ring min-h-[60px] w-full rounded-xl border-2 border-outline-variant bg-surface-lowest px-4 text-lg">
                <option value="">Elige tu sede…</option>
                @foreach ($sites as $site)
                    <option value="{{ $site->id }}" @selected($sedeUnica || old('site_id') == $site->id)>{{ $site->name }}</option>
                @endforeach
            </select>
            @error('site_id')
                <p class="mt-2 text-base text-danger">{{ $message }}</p>
            @enderror
        </div>

        {{-- PABELLÓN --}}
        <div data-cascade-step data-depends-on="site_id" hidden>
            <label for="building_id" class="mb-2 block text-lg font-semibold">
                ¿En qué pabellón estás?
            </label>
            <select id="building_id" name="building_id" required
                    class="focus-ring min-h-[60px] w-full rounded-xl border-2 border-outline-variant bg-surface-lowest px-4 text-lg">
                <option value="">Elige tu pabellón…</option>
                @foreach ($buildings as $building)
                    <option value="{{ $building->id }}" data-parent="{{ $building->site_id }}"
                            @selected(old('building_id') == $building->id)>{{ $building->name }}</option>
                @endforeach
            </select>
            @error('building_id')
                <p class="mt-2 text-base text-danger">{{ $message }}</p>
            @enderror
        </div>

        {{-- PISO --}}
        <div data-cascade-step data-depends-on="building_id" hidden>
            <label for="floor_id" class="mb-2 block text-lg font-semibold">
                ¿En qué piso estás?
            </label>
            <select id="floor_id" name="floor_id" required
                    class="focus-ring min-h-[60px] w-full rounded-xl border-2 border-outline-variant bg-surface-lowest px-4 text-lg">
                <option value="">Elige tu piso…</option>
                @foreach ($floors as $floor)
                    <option value="{{ $floor->id }}" data-parent="{{ $floor->building_id }}"
                            @selected(old('floor_id') == $floor->id)>{{ $floor->label }}</option>
                @endforeach
            </select>
            @error('floor_id')
                <p class="mt-2 text-base text-danger">{{ $message }}</p>
            @enderror
        </div>

        {{-- AULA --}}
        <div data-cascade-step data-depends-on="floor_id" hidden>
            <label for="room_id" class="mb-2 block text-lg font-semibold">
                ¿En qué aula estás?
            </label>
            <select id="room_id" name="room_id" required
                    class="focus-ring min-h-[60px] w-full rounded-xl border-2 border-outline-variant bg-surface-lowest px-4 text-lg">
                <option value="">Elige tu aula…</option>
                @foreach ($rooms as $room)
                    <option value="{{ $room->id }}" data-parent="{{ $room->floor_id }}"
                            @selected(old('room_id') == $room->id)>{{ $room->code }}</option>
                @endforeach
            </select>
            @error('room_id')
                <p class="mt-2 text-base text-danger">{{ $message }}</p>
            @enderror
        </div>

        {{-- El botón aparece solo cuando hay aula elegida: un botón que no
             hace nada todavía se pulsa igual, y el rebote sin explicación se
             lee como que el sistema está roto. --}}
        <div data-cascade-step data-depends-on="room_id" hidden>
            <button type="submit"
                    class="flex min-h-[64px] w-full items-center justify-center btn-primary focus-ring px-5 py-4 text-lg font-semibold transition active:scale-[.99] hover:opacity-90">
                Continuar
            </button>
        </div>
    </form>
@endsection

@section('back')
    {{-- Atajo para quien ya sabe su código y no quiere tocar cuatro listas. --}}
    <a href="{{ route('teacher.search') }}"
       class="block text-center text-base text-on-surface-variant underline underline-offset-4">
        Sé el código de mi aula
    </a>
@endsection
