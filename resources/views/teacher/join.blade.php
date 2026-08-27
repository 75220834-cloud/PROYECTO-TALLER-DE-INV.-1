@extends('teacher.layout')
@section('title', 'Ya hay una solicitud abierta')
@section('step', $draft->room?->code)

@section('content')
    {{-- Un rechazo NUNCA deja al docente sin salida. Si ya hay un ticket
         abierto para lo mismo, se le ofrece sumarse en lugar de decirle que
         no y dejarlo con el proyector averiado (plan §16.4). --}}

    <h1 class="mb-4 text-2xl font-bold">Ya avisamos de este problema</h1>

    <div class="rounded-xl border-2 border-amber-300 bg-amber-50 px-5 py-4">
        <p class="text-base text-amber-900">
            Ya hay una solicitud abierta para
            <strong>{{ $target->category?->name }}</strong>
            en <strong class="font-mono">{{ $target->room?->code }}</strong>,
            creada hace {{ $target->created_at->diffForHumans(null, true) }}.
        </p>

        @if ($target->ticket_number)
            <p class="mt-2 font-mono text-sm text-amber-800">Ticket #{{ $target->ticket_number }}</p>
        @endif
    </div>

    <p class="mt-5 text-lg text-slate-700">
        Puedes sumarte a esa solicitud para que soporte sepa que hay más de una
        persona afectada.
    </p>

    <form method="POST" action="{{ route('teacher.join.store', ['uuid' => $target->uuid]) }}" class="mt-5">
        @csrf
        <button type="submit"
                class="flex min-h-[64px] w-full items-center justify-center rounded-xl bg-slate-900 px-5 py-4 text-lg font-semibold text-white hover:bg-slate-800">
            Sumarme a esta solicitud
        </button>
    </form>

    <a href="{{ route('teacher.category') }}"
       class="mt-3 flex min-h-[60px] w-full items-center justify-center rounded-xl border-2 border-slate-300 px-5 py-3 text-lg font-medium text-slate-700 hover:bg-slate-50">
        Es otro problema distinto
    </a>
@endsection
