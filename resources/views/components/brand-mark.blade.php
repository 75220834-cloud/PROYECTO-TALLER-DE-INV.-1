@props(['size' => 36])

@php
    /*
     * IDENTIFICADOR INSTITUCIONAL
     *
     * Si existe `public/marca/logo.png` (o .svg / .webp), se muestra ese
     * archivo. Si no, sale un distintivo neutro con las iniciales.
     *
     * POR QUÉ NO VIENE UN LOGO DENTRO DEL REPOSITORIO: el logotipo de la
     * universidad es de la universidad. Ponerlo aquí significaría distribuir
     * su marca dentro del código y, sobre todo, que cualquier copia del
     * sistema —incluida una mal configurada— parecería oficial. El archivo
     * lo coloca el equipo, que es quien sabe si tiene permiso y cuál es la
     * versión buena.
     *
     * Para ponerlo: guarda el archivo como `public/marca/logo.png`. No hay
     * que tocar nada más.
     */
    $logo = collect(['svg', 'png', 'webp'])
        ->map(fn (string $ext): string => "marca/logo.{$ext}")
        ->first(fn (string $ruta): bool => file_exists(public_path($ruta)));
@endphp

@if ($logo)
    <img src="{{ asset($logo) }}" alt="{{ config('app.name') }}"
         style="height: {{ $size }}px"
         {{ $attributes->merge(['class' => 'w-auto object-contain']) }}>
@else
    {{-- Distintivo propio del sistema, no una imitación del institucional.
         Sirve para que la cabecera no quede coja mientras no haya logo. --}}
    <span style="height: {{ $size }}px; width: {{ $size }}px"
          {{ $attributes->merge([
              'class' => 'flex items-center justify-center rounded-md border border-[var(--glass-stroke)] '
                  .'bg-[linear-gradient(135deg,var(--accent),var(--primary))] '
                  .'text-on-accent',
          ]) }}
          aria-hidden="true">
        <span class="material-symbols-outlined" style="font-size: {{ (int) ($size * 0.55) }}px">school</span>
    </span>
@endif
