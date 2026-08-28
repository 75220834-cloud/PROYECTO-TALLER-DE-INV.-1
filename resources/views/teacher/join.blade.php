@extends('teacher.layout')
@section('title', 'Ya hay una solicitud abierta')
@section('step', $draft->room?->code)

@section('content')
    {{-- Un rechazo NUNCA deja al docente sin salida. Si ya hay un ticket
         abierto para lo mismo, se le ofrece sumarse en lugar de decirle que
         no y dejarlo con el proyector averiado (plan §16.4). --}}

    <h1 class="mb-4 text-2xl font-bold">Ya avisamos de este problema</h1>

    <div class="glass border-warn/60 bg-warn-container/60 px-5 py-4">
        <p class="text-base text-on-warn-container">
            Ya hay una solicitud abierta para
            <strong>{{ $target->category?->name }}</strong>
            en <strong class="font-mono">{{ $target->room?->code }}</strong>,
            creada hace {{ $target->created_at->diffForHumans(null, true) }}.
        </p>

        @if ($target->ticket_number)
            <p class="mt-2 font-mono text-sm text-on-warn-container">Ticket #{{ $target->ticket_number }}</p>
        @endif
    </div>

    <p class="mt-5 text-lg text-on-surface-variant">
        Puedes sumarte a esa solicitud para que soporte sepa que hay más de una
        persona afectada.
    </p>

    <form method="POST" action="{{ route('teacher.join.store', ['uuid' => $target->uuid]) }}" class="mt-5">
        @csrf
        <button type="submit"
                class="flex min-h-[64px] w-full items-center justify-center btn-primary focus-ring px-5 py-4 text-lg font-semibold hover:opacity-90">
            Sumarme a esta solicitud
        </button>
    </form>

    <a href="{{ route('teacher.category') }}"
       class="mt-3 flex min-h-[60px] w-full items-center justify-center rounded-xl border-2 border-outline-variant px-5 py-3 text-lg font-medium text-on-surface-variant hover:bg-surface-high">
        Es otro problema distinto
    </a>
@endsection
