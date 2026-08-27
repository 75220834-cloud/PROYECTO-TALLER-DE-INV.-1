@extends('layouts.app')
@section('title', 'Código QR')
@section('heading', 'Código QR de acceso')
@section('subheading', 'Un único código para todas las aulas')

@section('content')
<div class="grid max-w-4xl gap-6 md:grid-cols-2">

    <div class="rounded-lg border border-slate-200 bg-white p-6 text-center">
        <img src="{{ $pngDataUri }}" alt="Código QR de acceso al sistema" class="mx-auto h-64 w-64">

        <p class="mt-4 break-all font-mono text-xs text-slate-500">{{ $targetUrl }}</p>

        <div class="mt-5 flex justify-center gap-3">
            <a href="{{ route('admin.qr.poster') }}" target="_blank"
               class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                Ver cartel para imprimir
            </a>
            <a href="{{ route('admin.qr.download') }}"
               class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                Descargar SVG
            </a>
        </div>
    </div>

    <div class="space-y-4 text-sm text-slate-600">
        <div class="rounded-lg border border-sky-200 bg-sky-50 p-4">
            <p class="font-semibold text-sky-900">Es el mismo código para todas las aulas</p>
            <p class="mt-1 text-sky-800">
                Imprime este cartel una vez y pégalo igual en A101, C305, E407 o en cualquier aula del
                piloto. El código abre el sistema; <strong>no identifica el aula</strong>. El docente
                elige su ubicación dentro y el sistema la valida contra el catálogo.
            </p>
        </div>

        <div>
            <p class="font-semibold text-slate-800">Dónde pegarlo</p>
            <p class="mt-1">
                A la altura de los ojos, cerca del escritorio del docente o junto a los controles del
                proyector. Evita ponerlo detrás de la pizarra o en un lugar donde haya que apartar algo
                para verlo.
            </p>
        </div>

        <div>
            <p class="font-semibold text-slate-800">Si cambia la dirección del servidor</p>
            <p class="mt-1">
                El código se genera a partir de <code class="rounded bg-slate-100 px-1">APP_URL</code>.
                Si el sistema se muda a otro servidor, hay que <strong>volver a imprimir los carteles</strong>:
                los que ya estén pegados dejarán de funcionar.
            </p>
        </div>

        <div>
            <p class="font-semibold text-slate-800">Antes de imprimir en cantidad</p>
            <p class="mt-1">
                Prueba el código impreso con un celular <em>dentro de un aula</em>, conectado a la red
                que usan los docentes. Es la verificación más importante del piloto y la que puede
                invalidarlo si se descubre tarde.
            </p>
        </div>
    </div>
</div>
@endsection
