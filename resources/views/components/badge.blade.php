@props(['active' => true, 'demo' => false])

@if ($demo)
    <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">DEMO</span>
@endif

<span {{ $attributes->merge(['class' => 'inline-flex rounded-full px-2 py-0.5 text-xs font-medium '.($active
    ? 'bg-emerald-100 text-emerald-800'
    : 'bg-slate-200 text-slate-600')]) }}>
    {{ $active ? 'Activo' : 'Inactivo' }}
</span>
