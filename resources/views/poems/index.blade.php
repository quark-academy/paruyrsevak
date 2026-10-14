@extends('layouts.app')

@section('title', $term !== '' ? "Որոնում՝ {$term}" : 'Պարույր Սևակ — բանաստեղծությունների ձայնը')
@section('meta_description', \App\Support\Seo::defaultDescription())
@section('canonical', \App\Support\Seo::canonical())

@section('content')
    @php
        $chip = 'inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full border px-3 py-1.5 text-xs font-medium transition';
        $chipIdle = 'border-stone-200 bg-white text-stone-600 hover:border-stone-300 hover:text-stone-900';
        $chipActive = 'border-stone-900 bg-stone-900 text-amber-200';
        $chipUrl = function (array $params) use ($term, $source, $sort): string {
            $query = array_filter([
                'q' => $term,
                'source' => $params['source'] ?? $source,
                'sort' => $params['sort'] ?? $sort,
            ], fn ($value) => filled($value));

            return route('poems.index', $query);
        };
    @endphp

    {{-- Hero: on a phone this is the full width background with the buttons and
         the search; the texts are part of the desktop layout. --}}
    <section class="relative -mx-4 -mt-6 overflow-hidden bg-[#0b0a09] px-5 py-5 text-stone-100 shadow-xl shadow-stone-900/10 sm:mx-0 sm:mt-0 sm:rounded-3xl sm:px-8 sm:py-8">
        <div class="pointer-events-none absolute inset-0"
             style="background: radial-gradient(120% 90% at 12% 0%, rgba(232,196,106,0.18) 0%, rgba(11,10,9,0) 62%);"></div>

        {{-- The author's mark sits quietly in the corner, where the letter Ս used to be. --}}
        <img src="{{ asset('images/sevak-logo.png') }}" alt="" aria-hidden="true"
             class="pointer-events-none absolute -right-4 -top-6 h-40 w-40 select-none opacity-[0.06] sm:-right-6 sm:-top-10 sm:h-56 sm:w-56">

        <div class="relative">
            <p class="hidden text-[11px] font-medium uppercase tracking-[0.22em] text-amber-200/70 sm:block">
                Ձայնագրությունների արխիվ
            </p>

            <h1 class="mt-2.5 hidden text-3xl font-bold leading-tight sm:block sm:text-4xl">Պարույր Սևակ</h1>

            <p class="mt-1.5 hidden max-w-xl text-sm leading-relaxed text-stone-300 sm:block">
                Լսիր բանաստեղծությունները՝ բառացի կարաոկե համաժամեցմամբ։
            </p>

            <div class="flex items-center gap-2 sm:mt-5">
                @if ($listenPoem)
                    <a href="{{ \App\Support\Seo::poemUrl($listenPoem) }}" data-start-listening
                       class="inline-flex flex-1 items-center justify-center gap-2 rounded-full px-4 py-3 text-sm font-semibold text-stone-900 shadow-lg shadow-amber-300/10 transition active:scale-[0.98] sm:flex-none sm:px-5"
                       style="background-color: #e8c46a;">
                        ▶ <span class="sm:hidden">Սկսել</span><span class="hidden sm:inline">Սկսել վայելել</span>
                    </a>
                @endif

                <button type="button" data-random-poem="{{ route('api.poems.random') }}"
                        class="inline-flex flex-1 items-center justify-center gap-2 rounded-full border border-white/15 bg-white/5 px-4 py-3 text-sm font-medium text-stone-200 transition hover:border-amber-300/40 hover:text-white sm:flex-none">
                    🎲 Պատահական
                </button>
            </div>

            <p class="mt-4 hidden text-xs tabular-nums text-white/45 sm:block">
                {{ number_format((int) $stats['recordings'], 0, ',', ' ') }} ձայնագրություն
                · {{ number_format((int) $stats['plays'], 0, ',', ' ') }} լսում
            </p>
        </div>
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

