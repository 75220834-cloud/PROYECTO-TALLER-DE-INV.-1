@props(['href'])

<a href="{{ $href }}"
   class="flex min-h-[64px] w-full items-center justify-between gap-3 rounded-xl border-2 border-slate-300 bg-white px-5 py-4 text-left text-lg font-medium text-slate-900 transition active:scale-[.99] hover:border-slate-900 hover:bg-slate-50">
    <span>{{ $slot }}</span>
    <span aria-hidden="true" class="text-2xl leading-none text-slate-400">&rsaquo;</span>
</a>
