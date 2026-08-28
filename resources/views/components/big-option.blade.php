@props(['href'])

<a href="{{ $href }}"
   class="flex min-h-[64px] w-full items-center justify-between gap-3 rounded-xl border-2 border-outline-variant bg-surface-lowest px-5 py-4 text-left text-lg font-medium text-primary transition active:scale-[.99] hover:border-primary hover:bg-surface-low">
    <span>{{ $slot }}</span>
    <span aria-hidden="true" class="text-2xl leading-none text-outline">&rsaquo;</span>
</a>
