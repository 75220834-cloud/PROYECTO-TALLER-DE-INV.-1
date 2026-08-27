@props(['label', 'name', 'required' => false, 'help' => null])

<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-slate-700">
        {{ $label }}@if ($required)<span class="text-rose-600"> *</span>@endif
    </label>

    {{ $slot }}

    @if ($help)
        <p class="mt-1 text-xs text-slate-500">{{ $help }}</p>
    @endif

    @error($name)
        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
    @enderror
</div>
