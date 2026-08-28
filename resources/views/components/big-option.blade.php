@props(['href'])

{{--
    El botón que más se toca en todo el sistema: es con el que el docente
    elige pabellón, piso, aula, tipo de problema y cada respuesta del
    diagnóstico. Por eso vive en un componente y no repetido en ocho vistas.

    Alto mínimo de 64 px, muy por encima de los 44 que exige el plan (§17.9):
    se toca de pie, con prisa y a veces con el móvil en una mano.
--}}
<a href="{{ $href }}"
   {{ $attributes->merge([
       'class' => 'glass glass-hover focus-ring flex min-h-[64px] w-full items-center '
           .'justify-between gap-4 px-5 py-4 text-left text-lg font-medium '
           .'text-on-surface active:scale-[.99]',
   ]) }}>
    <span class="min-w-0">{{ $slot }}</span>

    {{-- La flecha se tiñe del color de acento al pasar por encima: indica
         que la tarjeta entera es pulsable, no solo el texto. --}}
    <span aria-hidden="true"
          class="material-symbols-outlined shrink-0 text-on-surface-variant transition group-hover:text-accent">
        chevron_right
    </span>
</a>
