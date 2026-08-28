@extends('layouts.app')
@section('title', 'Cobertura visual')
@section('heading', 'Pasos sin imagen')
@section('subheading', 'Pasos que mencionan una pieza física y no la enseñan')

@section('content')

<div class="mb-5 rounded-lg border border-slate-200 bg-white px-5 py-4 text-sm text-slate-700">
    Cada fila es un paso donde el docente lee «revisa el cable HDMI» y no ve cuál es. Para quien ya
    sabe qué es un HDMI da igual; para quien no —que es el usuario que este sistema existe para
    atender— el paso no comunica nada y acaba pidiendo soporte sin haber intentado nada.
</div>

@forelse ($pending as $step)
    <div class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-5 py-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="font-medium text-amber-900">{{ $step->prompt_text }}</p>
                <p class="mt-1 text-sm text-amber-800">
                    {{ $step->category ?? 'Sin categoría' }} · paso <code class="font-mono">{{ $step->key }}</code>
                </p>
            </div>

            <div class="text-right">
                <p class="text-xs uppercase tracking-wide text-amber-700">Falta la imagen de</p>
                <p class="font-mono text-sm font-semibold text-amber-900">{{ $step->component_key }}</p>
            </div>
        </div>

        <a href="{{ route('admin.media.create') }}"
           class="mt-3 inline-block rounded-md border border-amber-300 bg-white px-3 py-1.5 text-sm font-medium text-amber-900">
            Subir imagen para «{{ $step->component_key }}»
        </a>
    </div>
@empty
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-5 py-6 text-center">
        <p class="text-lg font-semibold text-emerald-900">Cobertura completa</p>
        <p class="mt-1 text-sm text-emerald-800">
            Todos los pasos que mencionan una pieza física la muestran.
        </p>
    </div>
@endforelse

<a href="{{ route('admin.media.index') }}" class="mt-5 inline-block text-sm text-slate-600 underline underline-offset-2">
    Volver al banco de imágenes
</a>
@endsection
