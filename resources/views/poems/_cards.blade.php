{{-- One card per poem. Rendered with the full list, and on its own when the
     visitor asks for more of the catalogue ("load more"). The favourite button
     is a sibling of the link: a button inside an anchor would be invalid. --}}
@foreach ($poems as $poem)
    <div data-card class="group relative">
        <a href="{{ \App\Support\Seo::poemUrl($poem) }}"
           class="flex items-center gap-3 rounded-2xl border border-stone-200 bg-white py-3 pl-3.5 pr-12 shadow-sm transition hover:border-amber-200 hover:shadow-md hover:shadow-stone-900/5">
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

                    @foreach ($poem->themeLabels() as $themeLabel)
                        <span class="rounded-full bg-stone-100 px-1.5 py-0.5 text-stone-500">{{ $themeLabel }}</span>
                    @endforeach

                    <span class="tabular-nums" title="Լսումների քանակ">▶ {{ $poem->playsLabel() }} լսում</span>
                </span>
            </span>
        </a>

        <button type="button"
                data-favorite
                data-slug="{{ $poem->slug }}"
                data-title="{{ $poem->title }}"
                data-url="{{ \App\Support\Seo::poemUrl($poem) }}"
                data-excerpt="{{ $poem->excerpt(90) }}"
                aria-pressed="false"
                aria-label="Ավելացնել «{{ $poem->title }}» իմ ընտրանուն"
                title="Իմ ընտրանին"
                class="absolute right-2 top-1/2 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-full text-stone-400 transition hover:bg-stone-100 hover:text-rose-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-400 active:scale-90">
            <svg data-favorite-icon class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1L12 21l7.7-7.6 1.1-1a5.5 5.5 0 0 0 0-7.8Z" />
            </svg>
        </button>
    </div>
@endforeach
