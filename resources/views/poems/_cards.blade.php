{{-- One card per poem. Rendered with the full list, and on its own when the
     visitor asks for more of the catalogue ("load more"). --}}
@foreach ($poems as $poem)
    <a href="{{ \App\Support\Seo::poemUrl($poem) }}"
       class="group flex items-center gap-3 rounded-2xl border border-stone-200 bg-white px-3.5 py-3 shadow-sm transition hover:border-amber-200 hover:shadow-md hover:shadow-stone-900/5">
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full border border-stone-200 text-[11px] text-stone-500 transition group-hover:border-transparent group-hover:bg-stone-900 group-hover:text-amber-200">
            ▶
        </span>

        <span class="min-w-0 flex-1">
            <span class="block text-sm font-semibold leading-snug">{{ $poem->title }}</span>

            <span class="mt-0.5 hidden text-xs leading-relaxed text-stone-500 sm:block">
                {{ $poem->excerpt(90) }}
            </span>

            <span class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-stone-400">
                <span class="rounded-full px-1.5 py-0.5 font-medium {{ $poem->is_original ? 'bg-amber-100 text-amber-800' : 'bg-sky-100 text-sky-800' }}">
                    {{ $poem->voiceSourceLabel() }}
                </span>
                <span class="tabular-nums" title="Լսումների քանակ">▶ {{ $poem->playsLabel() }} լսում</span>
            </span>
        </span>
    </a>
@endforeach