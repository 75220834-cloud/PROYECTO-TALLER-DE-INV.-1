@props(['active' => false])

<a {{ $attributes->merge(['class' => 'block rounded-md px-3 py-2 transition '.($active
        ? 'bg-on-surface font-medium text-surface-lowest'
        : 'text-outline hover:bg-on-surface/60 hover:text-surface-mid')]) }}>
    {{ $slot }}
</a>
