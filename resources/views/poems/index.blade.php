@extends('layouts.app')

@section('title', $term !== '' ? "Որոնում՝ {$term}" : 'Պարույր Սևակ — բանաստեղծությունների ձայնը')
@section('meta_description', \App\Support\Seo::defaultDescription())
@section('canonical', \App\Support\Seo::canonical())

@section('content')
    @php
        $chip = 'inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition';
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

    {{-- Hero --}}
    <section class="relative overflow-hidden rounded-3xl bg-[#0b0a09] px-5 py-7 text-stone-100 shadow-xl shadow-stone-900/10 sm:px-8 sm:py-9">
        <div class="pointer-events-none absolute inset-0"
             style="background: radial-gradient(120% 90% at 12% 0%, rgba(232,196,106,0.18) 0%, rgba(11,10,9,0) 62%);"></div>
        <span class="pointer-events-none absolute -right-6 -top-10 select-none text-[11rem] font-black leading-none text-white/[0.035]">Ս</span>

        <div class="relative">
            <p class="text-[11px] font-medium uppercase tracking-[0.22em] text-amber-200/70">
                Ձայնագրությունների արխիվ
            </p>

            <h1 class="mt-3 text-3xl font-bold leading-tight sm:text-4xl">Պարույր Սևակ</h1>

            <p class="mt-2 max-w-xl text-sm leading-relaxed text-stone-300">
                Արխիվային ձայնագրություններ և ԱԻ ընթերցում՝ բառացի կարաոկե համաժամեցմամբ։
            </p>

            <div class="mt-5 flex flex-wrap items-center gap-2">
                @if ($listenPoem)
                    <a href="{{ \App\Support\Seo::poemUrl($listenPoem) }}" data-start-listening
                       class="inline-flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold text-stone-900 shadow-lg shadow-amber-300/10 transition active:scale-[0.98]"
                       style="background-color: #e8c46a;">
                        ▶ Սկսել լսելը
                    </a>
                @endif

                <button type="button" data-random-poem="{{ route('api.poems.random') }}"
                        class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-4 py-2.5 text-sm font-medium text-stone-200 transition hover:border-amber-300/40 hover:text-white">
                    🎲 Պատահական
                </button>

                <a href="#catalogue"
                   class="inline-flex items-center gap-2 rounded-full px-4 py-2.5 text-sm font-medium text-stone-400 transition hover:text-white">
                    Բոլորը ↓
                </a>
            </div>

            <dl class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ([
                    ['Բանաստեղծություն', $stats['poems']],
                    ['Ձայնագրություն', $stats['recordings']],
                    ['Լսում', $stats['plays']],
                    ['Բառ', $stats['words']],
                ] as [$label, $value])
                    <div class="rounded-2xl border border-white/10 bg-white/[0.04] px-3 py-2.5">
                        <dt class="text-[10px] uppercase tracking-wider text-white/40">{{ $label }}</dt>
                        <dd class="mt-0.5 text-lg font-semibold tabular-nums" style="color: #e8c46a;">
                            {{ number_format((int) $value, 0, ',', ' ') }}
                        </dd>
                    </div>
                @endforeach
            </dl>

            <form method="GET" action="{{ route('poems.index') }}"
                  class="mt-5 flex items-center gap-2 rounded-2xl border border-white/10 bg-white/[0.06] p-1.5 transition focus-within:border-amber-300/40">
                <span class="pl-2 text-white/30">⌕</span>
                <input type="search" name="q" value="{{ $term }}" placeholder="Փնտրել վերնագիր կամ տող…"
                       class="w-full bg-transparent px-1 py-2 text-sm text-stone-100 placeholder-white/35 focus:outline-none">
                @if ($source !== '')
                    <input type="hidden" name="source" value="{{ $source }}">
                @endif
                @if ($sort !== 'new')
                    <input type="hidden" name="sort" value="{{ $sort }}">
                @endif
                <button type="submit"
                        class="shrink-0 rounded-xl px-4 py-2 text-sm font-semibold text-stone-900"
                        style="background-color: #e8c46a;">
                    Փնտրել
                </button>
            </form>
        </div>
    </section>

    @if ($spotlight->isNotEmpty() && ! $isFiltered)
        <section class="mt-8">
            <div class="mb-3 flex items-end justify-between gap-3">
                <h2 class="text-base font-semibold">Ամենաշատ լսվածը</h2>
                <a href="{{ $chipUrl(['sort' => 'popular']) }}" class="text-xs text-stone-500 hover:text-stone-900">
                    Տեսնել բոլորը →
                </a>
            </div>

            <div class="-mx-4 flex snap-x snap-mandatory gap-3 overflow-x-auto px-4 pb-1 sm:mx-0 sm:px-0">
                @foreach ($spotlight as $index => $poem)
                    <a href="{{ \App\Support\Seo::poemUrl($poem) }}"
                       class="group flex w-56 shrink-0 snap-start flex-col rounded-2xl border border-stone-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-amber-200 hover:shadow-md">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold tabular-nums text-amber-600">#{{ $index + 1 }}</span>
                            <span class="text-[11px] tabular-nums text-stone-400">▶ {{ $poem->playsLabel() }}</span>
                        </div>
                        <h3 class="mt-2 line-clamp-2 text-sm font-semibold leading-snug">{{ $poem->title }}</h3>
                        <p class="mt-1 line-clamp-2 text-xs leading-relaxed text-stone-500">{{ $poem->excerpt(70) }}</p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Catalogue --}}
    <section id="catalogue" class="mt-8">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold">
                    {{ $isFiltered ? 'Արդյունքներ' : 'Բոլոր բանաստեղծությունները' }}
                </h2>
                <p class="mt-0.5 text-xs text-stone-500">Գտնվեց՝ {{ $poems->total() }}</p>
            </div>

            @if ($isFiltered)
                <a href="{{ route('poems.index') }}" class="text-xs text-stone-500 hover:text-stone-900">
                    ✕ Մաքրել զտիչները
                </a>
            @endif
        </div>

        <div class="mb-5 flex flex-wrap items-center gap-2">
            <a href="{{ $chipUrl(['source' => '']) }}"
               class="{{ $chip }} {{ $source === '' ? $chipActive : $chipIdle }}">Բոլորը</a>
            <a href="{{ $chipUrl(['source' => 'original']) }}"
               class="{{ $chip }} {{ $source === 'original' ? $chipActive : $chipIdle }}">Արխիվային ձայն</a>
            <a href="{{ $chipUrl(['source' => 'ai']) }}"
               class="{{ $chip }} {{ $source === 'ai' ? $chipActive : $chipIdle }}">ԱԻ ձայն</a>

            <span class="mx-1 hidden h-4 w-px bg-stone-200 sm:block"></span>

            <a href="{{ $chipUrl(['sort' => 'new']) }}"
               class="{{ $chip }} {{ $sort === 'new' ? $chipActive : $chipIdle }}">Նորերը</a>
            <a href="{{ $chipUrl(['sort' => 'popular']) }}"
               class="{{ $chip }} {{ $sort === 'popular' ? $chipActive : $chipIdle }}">Ամենալսվածը</a>
        </div>

        @if ($poems->isEmpty())
            <div class="rounded-3xl border border-dashed border-stone-300 bg-white px-6 py-14 text-center">
                <p class="text-sm text-stone-500">Ոչինչ չգտնվեց։</p>
                <a href="{{ route('poems.index') }}" class="mt-3 inline-block text-sm font-medium text-stone-900 underline">
                    Ցուցադրել բոլորը
                </a>
            </div>
        @else
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($poems as $poem)
                    <a href="{{ \App\Support\Seo::poemUrl($poem) }}"
                       class="group relative flex flex-col overflow-hidden rounded-2xl border border-stone-200 bg-white p-4 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-amber-200 hover:shadow-lg hover:shadow-stone-900/5">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-base font-semibold leading-snug">{{ $poem->title }}</h3>

                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full border border-stone-200 text-[10px] text-stone-500 transition group-hover:border-transparent group-hover:bg-stone-900 group-hover:text-amber-200">
                                ▶
                            </span>
                        </div>

                        <p class="mt-2 line-clamp-3 text-sm leading-relaxed text-stone-600">{{ $poem->excerpt(140) }}</p>

                        <div class="mt-auto flex flex-wrap items-center gap-x-3 gap-y-1.5 pt-3 text-[11px] text-stone-400">
                            <span class="rounded-full px-2 py-0.5 font-medium {{ $poem->is_original ? 'bg-amber-100 text-amber-800' : 'bg-sky-100 text-sky-800' }}">
                                {{ $poem->voiceSourceLabel() }}
                            </span>
                            <span class="tabular-nums" title="Լսումների քանակ">▶ {{ $poem->playsLabel() }} լսում</span>
                            <span class="tabular-nums">{{ $poem->words_count }} բառ</span>
                            @if ($poem->hasAudio())
                                <span class="text-emerald-600">● ձայն կա</span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $poems->links() }}
            </div>
        @endif
    </section>
@endsection

@push('scripts')
<script>
// Hero shortcuts: make sure autoplay is switched on before leaving the page,
// so the player starts the recording as soon as it opens.
(function () {
    const PREF_KEY = 'sevak:player-prefs';

    function enableAutoplay() {
        try {
            const prefs = JSON.parse(window.localStorage.getItem(PREF_KEY) || '{}') || {};
            prefs.autoplay = true;
            window.localStorage.setItem(PREF_KEY, JSON.stringify(prefs));
        } catch (error) {
            // Preferences are a nice-to-have: never block navigation.
        }
    }

    const start = document.querySelector('[data-start-listening]');

    if (start) {
        start.addEventListener('click', enableAutoplay);
    }

    const random = document.querySelector('[data-random-poem]');

    if (!random) {
        return;
    }

    random.addEventListener('click', async function () {
        enableAutoplay();
        random.disabled = true;
        random.classList.add('opacity-60');

        try {
            const response = await fetch(random.dataset.randomPoem, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json();

            if (data.url) {
                window.location.href = data.url;

                return;
            }
        } catch (error) {
            // fall through: re-enable the button
        }

        random.disabled = false;
        random.classList.remove('opacity-60');
    });
})();
</script>
@endpush
