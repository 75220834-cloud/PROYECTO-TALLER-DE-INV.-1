@props(['active' => true, 'demo' => false])

@if ($demo)
    <span class="inline-flex rounded-full bg-warn-container px-2 py-0.5 text-xs font-medium text-on-warn-container">DEMO</span>
@endif

<span {{ $attributes->merge(['class' => 'inline-flex rounded-full px-2 py-0.5 text-xs font-medium '.($active
    ? 'bg-ok-container text-on-ok-container'
    : 'bg-outline-variant text-on-surface-variant')]) }}>
    {{ $active ? 'Activo' : 'Inactivo' }}
</span>
