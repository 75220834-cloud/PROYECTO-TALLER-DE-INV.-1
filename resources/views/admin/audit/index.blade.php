@extends('layouts.app')
@section('title', 'Auditoría')
@section('heading', 'Auditoría del sistema')
@section('subheading', 'Quién cambió qué, y cuándo')

@section('content')

<form method="GET" class="mb-5 flex flex-wrap items-center gap-2">
    <select name="action" data-auto-submit class="rounded-md border-slate-300 px-3 py-2 text-sm">
        <option value="">Todas las acciones</option>
        @foreach ($actions as $option)
            <option value="{{ $option }}" @selected($action === $option)>{{ $option }}</option>
        @endforeach
    </select>
</form>

<div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
    <table class="w-full text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-4 py-3">Cuándo</th>
                <th class="px-4 py-3">Quién</th>
                <th class="px-4 py-3">Acción</th>
                <th class="px-4 py-3">Sobre qué</th>
                <th class="px-4 py-3">Cambios</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($logs as $log)
                <tr class="border-b border-slate-100 last:border-0">
                    <td class="whitespace-nowrap px-4 py-3 text-slate-600">
                        {{ \Illuminate\Support\Carbon::parse($log->created_at)->timezone(config('incidencias.display_timezone'))->format('d/m/Y H:i') }}
                    </td>
                    <td class="px-4 py-3">{{ $log->user_name ?? 'Sistema' }}</td>
                    <td class="px-4 py-3 font-mono text-xs">{{ $log->action }}</td>
                    <td class="px-4 py-3 text-slate-600">
                        {{ $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : '—' }}
                    </td>
                    <td class="max-w-md px-4 py-3">
                        @if ($log->changes)
                            {{-- El diff se muestra como texto plano: es contenido
                                 guardado, no marcado, y renderizarlo como HTML
                                 sería abrir una vía de inyección. --}}
                            <code class="block truncate text-xs text-slate-500">{{ $log->changes }}</code>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">Sin registros todavía.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $logs->links() }}</div>

<p class="mt-4 text-xs text-slate-400">
    La dirección IP se guarda hasheada y no se muestra: sirve para agrupar acciones del mismo origen,
    no para identificar a nadie.
</p>
@endsection
