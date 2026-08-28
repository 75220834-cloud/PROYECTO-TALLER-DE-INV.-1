@props(['label', 'name', 'required' => false, 'help' => null])

<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-on-surface-variant">
        {{ $label }}@if ($required)<span class="text-danger"> *</span>@endif
    </label>

    {{ $slot }}

    @if ($help)
        <p class="mt-1 text-xs text-on-surface-variant">{{ $help }}</p>
    @endif

    @error($name)
        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
    @enderror
</div>
