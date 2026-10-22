@props(['active' => false])

{{-- La sección activa lleva la franja violeta con letra dorada, como la
     cabecera; las demás, texto sobrio que se tiñe de lila al pasar. --}}
<a {{ $attributes->merge(['class' => 'block rounded-md px-3 py-2 transition '.($active
        ? 'brand-bar font-semibold shadow-[inset_3px_0_0_var(--gold)]'
        : 'text-on-surface-variant hover:bg-primary-container hover:text-on-primary-container')]) }}>
    {{ $slot }}
</a>
