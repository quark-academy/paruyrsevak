@extends('layouts.app')

@section('content')
    @php
        $chip = 'inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full border px-3 py-1.5 text-xs font-medium transition';
        $chipIdle = 'border-stone-200 bg-white text-stone-600 hover:border-stone-300 hover:text-stone-900';
        $chipActive = 'border-stone-900 bg-stone-900 text-amber-200';
        $chipUrl = function (array $params) use ($term, $source, $sort, $theme): string {
            $query = array_filter([
                'q' => $term,
                'source' => $params['source'] ?? $source,
                'sort' => $params['sort'] ?? $sort,
                'theme' => $params['theme'] ?? $theme,
            ], fn ($value) => filled($value));

            return route('poems.index', $query);
        };
    @endphp

    {{-- Hero: on a phone this is the full width background with the button and
         the texts follow the layout of a player — the name, the line about the
         recording, the numbers — and the single play control sits on the right
         of all of it on a wide screen. --}}
    <section class="relative -mx-4 -mt-6 overflow-hidden bg-[#0b0a09] px-5 py-5 text-stone-100 shadow-xl shadow-stone-900/10 sm:mx-0 sm:mt-0 sm:rounded-3xl sm:px-8 sm:py-8">
        <div class="pointer-events-none absolute inset-0"
             style="background: radial-gradient(120% 90% at 12% 0%, rgba(232,196,106,0.18) 0%, rgba(11,10,9,0) 62%);"></div>

        {{-- The author's mark sits quietly in the corner, where the letter Ս used to be. --}}
        <img src="{{ asset('images/sevak-logo.png') }}" alt="" aria-hidden="true"
             class="pointer-events-none absolute -right-4 -top-6 h-40 w-40 select-none opacity-[0.06] sm:-right-6 sm:-top-10 sm:h-56 sm:w-56">

        <div class="relative flex items-center gap-6 sm:justify-between">
            {{-- The texts are the desktop half of the player; on a phone the
                 wrapper is out of the layout altogether, so the play pill takes
                 the full width of the band without a gap pushing it out. --}}
            <div class="hidden min-w-0 sm:block">
                <h1 class="hidden text-3xl font-bold leading-tight sm:block sm:text-4xl">Պարույր Սևակ</h1>

                <p class="mt-2 hidden max-w-xl text-sm leading-relaxed text-stone-300 sm:block">
                    Լսիր բանաստեղծությունները՝ հեղինակի կատարմամբ։
                </p>

                <p class="mt-4 hidden text-xs tabular-nums text-white/45 sm:block">
                    {{ number_format((int) $stats['recordings'], 0, ',', ' ') }} ձայնագրություն
                    · {{ number_format((int) $stats['plays'], 0, ',', ' ') }} լսում
                </p>
            </div>

            @if ($listenPoem)
                {{-- The one play control of the page: a pill to tap on a phone,
                     the round button of a player on a wide screen. The colour is
                     an inline hex, so it reads as the primary control even in a
                     browser that mishandles the modern colour functions. --}}
                <a href="{{ \App\Support\Seo::poemUrl($listenPoem) }}" data-start-listening
                   aria-label="Սկսել վայելել" title="Սկսել վայելել"
                   class="inline-flex w-full shrink-0 items-center justify-center gap-2 rounded-full px-4 py-3 text-sm font-semibold text-stone-900 shadow-lg shadow-amber-300/10 outline-none transition hover:brightness-105 focus-visible:ring-4 focus-visible:ring-amber-200/70 active:scale-[0.98] sm:h-20 sm:w-20 sm:p-0 sm:ring-8 sm:ring-amber-300/10 sm:hover:ring-amber-300/20"
                   style="background-color: #e8c46a;">
                    <svg class="h-4 w-4 sm:h-7 sm:w-7" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M8 5.14v13.72a1 1 0 0 0 1.54.84l10.3-6.86a1 1 0 0 0 0-1.68L9.54 4.3A1 1 0 0 0 8 5.14Z"/>
                    </svg>

                    <span class="sm:hidden">Սկսել վայելել</span>
                </a>
            @endif
        </div>

        {{-- Themes: one quiet line of words under the numbers — the shortest
             way to pick a mood. The chosen word turns gold; nothing else is
             added to the page. On a phone the line scrolls sideways instead of
             wrapping, so the hero stays the same height. --}}
        <nav class="relative mt-4 flex flex-nowrap items-center gap-x-2 overflow-x-auto border-t border-white/10 pt-3 text-[13px] leading-none [scrollbar-width:none] sm:mt-5 sm:flex-wrap sm:overflow-visible [&::-webkit-scrollbar]:hidden"
             aria-label="Ըստ թեմայի">
            <span class="mr-0.5 shrink-0 text-[10px] uppercase tracking-[0.2em] text-white/30">Թեմա</span>

            <a href="{{ $chipUrl(['theme' => '']) }}"
               class="shrink-0 whitespace-nowrap rounded px-0.5 transition {{ $theme === '' ? 'font-semibold text-amber-200' : 'text-white/50 hover:text-white' }}"
               @if ($theme === '') aria-current="page" @endif>Բոլորը</a>

            @foreach ($themes as $themeKey => $themeLabel)
                <span class="shrink-0 text-white/15" aria-hidden="true">·</span>
                <a href="{{ $chipUrl(['theme' => $themeKey]) }}"
                   class="shrink-0 whitespace-nowrap rounded px-0.5 transition {{ $theme === $themeKey ? 'font-semibold text-amber-200' : 'text-white/50 hover:text-white' }}"
                   @if ($theme === $themeKey) aria-current="page" @endif>{{ $themeLabel }}</a>
            @endforeach
        </nav>
    </section>

    @if ($spotlight->isNotEmpty() && ! $isFiltered)
        <section class="mt-6">
            <div class="mb-2 flex items-end justify-between gap-3">
                <h2 class="text-sm font-semibold">Ամենաշատ լսվածը</h2>
                <a href="{{ $chipUrl(['sort' => 'popular']) }}" class="text-xs text-stone-500 hover:text-stone-900">
                    Բոլորը →
                </a>
            </div>

            <div class="grid gap-1.5">
                @foreach ($spotlight->take(3) as $index => $poem)
                    <a href="{{ \App\Support\Seo::poemUrl($poem) }}"
                       class="flex items-center gap-3 rounded-2xl border border-stone-200 bg-white px-3 py-2.5 shadow-sm transition hover:border-amber-200">
                        <span class="w-4 shrink-0 text-xs font-bold tabular-nums text-amber-600">{{ $index + 1 }}</span>
                        <span class="min-w-0 flex-1 text-sm font-medium leading-snug">{{ $poem->title }}</span>
                        <span class="shrink-0 text-[11px] tabular-nums text-stone-400">▶ {{ $poem->playsLabel() }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Catalogue: a plain, tappable list --}}
    <section id="catalogue" class="mt-6">
        <div class="mb-3 flex items-end justify-between gap-3">
            <h2 class="text-sm font-semibold">
                {{ $isFiltered ? 'Արդյունքներ' : 'Բոլոր բանաստեղծությունները' }}
                <span class="ml-1 font-normal tabular-nums text-stone-400">{{ $poems->total() }}</span>
            </h2>

            @if ($isFiltered)
                <a href="{{ route('poems.index') }}" class="text-xs text-stone-500 hover:text-stone-900">
                    ✕ Մաքրել
                </a>
            @endif
        </div>

        {{-- One scrollable row on phones, a wrapped row on desktop --}}
        <div class="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0">
            <a href="{{ $chipUrl(['source' => '']) }}" class="{{ $chip }} {{ $source === '' ? $chipActive : $chipIdle }}">Բոլորը</a>
            <a href="{{ $chipUrl(['source' => 'original']) }}" class="{{ $chip }} {{ $source === 'original' ? $chipActive : $chipIdle }}">Օրիգինալ</a>
            <a href="{{ $chipUrl(['source' => 'ai']) }}" class="{{ $chip }} {{ $source === 'ai' ? $chipActive : $chipIdle }}">AI</a>
            <a href="{{ $chipUrl(['sort' => 'new']) }}" class="{{ $chip }} {{ $sort === 'new' ? $chipActive : $chipIdle }}">Նորերը</a>
            <a href="{{ $chipUrl(['sort' => 'popular']) }}" class="{{ $chip }} {{ $sort === 'popular' ? $chipActive : $chipIdle }}">Ամենալսվածը</a>
        </div>

        @if ($poems->isEmpty())
            <div class="rounded-3xl border border-dashed border-stone-300 bg-white px-6 py-14 text-center">
                <p class="text-sm text-stone-500">Ոչինչ չգտնվեց։</p>
                <a href="{{ route('poems.index') }}" class="mt-3 inline-block text-sm font-medium text-stone-900 underline">
                    Ցուցադրել բոլորը
                </a>
            </div>
        @else
            <div data-cards class="grid gap-1.5 sm:grid-cols-2">
                @include('poems._cards', ['poems' => $poems])
            </div>

            @if ($moreUrl)
                {{-- Painted like the hero's primary button: the colour is an
                     inline hex, so the button reads as a button even in a
                     browser that mishandles the modern colour functions. --}}
                <div class="mt-5 text-center">
                    <a href="{{ $moreUrl }}" data-load-more
                       class="inline-flex items-center justify-center gap-2 rounded-full px-6 py-3 text-sm font-semibold text-stone-900 shadow-lg shadow-amber-300/10 transition active:scale-[0.98]"
                       style="background-color: #e8c46a;">
                        <span aria-hidden="true">↓</span>
                        Ցուցադրել ավելին
                    </a>
                </div>
            @endif
        @endif
    </section>
@endsection

