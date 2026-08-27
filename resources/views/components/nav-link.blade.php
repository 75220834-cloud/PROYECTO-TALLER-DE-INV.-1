@props(['active' => false])

<a {{ $attributes->merge(['class' => 'block rounded-md px-3 py-2 transition '.($active
        ? 'bg-slate-800 font-medium text-white'
        : 'text-slate-400 hover:bg-slate-800/60 hover:text-slate-100')]) }}>
    {{ $slot }}
</a>
