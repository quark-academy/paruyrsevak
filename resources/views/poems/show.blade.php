@extends('layouts.app')

@section('title', ($poem->meta_title ?: $poem->title).' · '.config('app.name'))
@section('meta_description', $poem->meta_description ?: $poem->excerpt(150))
@section('canonical', \App\Support\Seo::forPoem($poem))

@push('meta')
    <meta property="og:title" content="{{ $poem->meta_title ?: $poem->title }}">
    <meta property="og:description" content="{{ $poem->meta_description ?: $poem->excerpt(150) }}">
    <meta property="og:type" content="article">
@endpush

@section('content')
    <a href="{{ route('poems.index') }}" class="mb-4 inline-flex items-center gap-1 text-sm text-stone-500 hover:text-stone-900">
        ← Բոլոր բանաստեղծությունները
    </a>

    <a href="{{ \App\Support\Seo::poemUrl($poem) }}"
       class="mb-4 ml-2 inline-flex items-center gap-1 rounded-full bg-stone-900 px-3 py-1 text-xs font-medium text-white hover:bg-stone-800">
        ▶ Կարաոկե ռեժիմ
    </a>

    <article class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
        <header class="mb-4">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-2xl font-bold leading-tight">{{ $poem->title }}</h1>
                <span class="rounded-full px-2 py-0.5 text-[10px] font-medium {{ $poem->is_original ? 'bg-amber-100 text-amber-800' : 'bg-sky-100 text-sky-800' }}">
                    {{ $poem->voiceSourceLabel() }}
                </span>
            </div>
            <p class="mt-1 text-xs text-stone-400">
                <span id="plays-count" class="tabular-nums">▶ {{ $poem->playsLabel() }} լսում</span>
                · {{ $poem->words->count() }} բառ · կարաոկե համաժամեցում
            </p>
        </header>

        @if ($poem->hasAudio())
            <div class="mb-5 rounded-xl bg-stone-900 p-4 text-stone-50">
                <audio id="poem-audio" preload="metadata" src="{{ $poem->audioUrl() }}" class="hidden"></audio>

                <div class="flex items-center gap-3">
                    <button type="button" id="play-toggle"
                            class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-stone-50 text-lg font-bold text-stone-900">
                        ▶
                    </button>
                    <div class="min-w-0 flex-1">
                        <input id="seek" type="range" min="0" max="100" value="0" step="0.1"
                               class="h-1.5 w-full cursor-pointer appearance-none rounded-full bg-stone-700 accent-stone-100">
                        <div class="mt-1 flex justify-between text-[11px] tabular-nums text-stone-400">
                            <span id="time-current">0:00</span>
                            <span id="time-total">0:00</span>
                        </div>
                    </div>
                    <select id="speed" class="rounded-lg border border-stone-700 bg-stone-800 px-2 py-1 text-xs">
                        <option value="0.8" selected>0.8×</option>
                        <option value="1">1×</option>
                        <option value="1.2">1.2×</option>
                        <option value="1.5">1.5×</option>
                        <option value="0.75">0.75×</option>
                    </select>
                </div>

                <p id="sound-hint" hidden class="mt-2 text-[11px] text-amber-200">
                    🔇 Հպվիր էկրանին (կամ սեղմիր ▶)՝ ձայնը միացնելու համար։
                </p>
            </div>
        @else
            <div class="mb-5 rounded-xl border border-dashed border-stone-300 bg-stone-50 px-4 py-3 text-sm text-stone-500">
                Աուդիո դեռ ստեղծված չէ։ Այցելիր ադմին վահանակ՝ ստեղծելու ԱԻ ձայն։
            </div>
        @endif

        @if ($poem->hasAudio() && $poem->words->isNotEmpty())
            <div id="karaoke" class="flex flex-wrap gap-x-2 gap-y-1 text-xl leading-relaxed">
                @foreach ($poem->words as $index => $word)
                    <span data-word-index="{{ $index }}"
                          data-start="{{ number_format($word->start_time, 2, '.', '') }}"
                          data-end="{{ number_format($word->end_time, 2, '.', '') }}"
                          class="cursor-pointer rounded px-0.5 text-stone-400 transition-colors duration-150">{{ $word->word }}</span>
                @endforeach
            </div>
        @else
            <div class="whitespace-pre-line text-lg leading-relaxed text-stone-800">{{ $poem->content }}</div>
        @endif
    </article>

    @if ($poem->hasAudio() && $poem->words->isNotEmpty())
        @push('scripts')
        <script>
        (function () {
            const audio = document.getElementById('poem-audio');
            const toggle = document.getElementById('play-toggle');
            const seek = document.getElementById('seek');
            const current = document.getElementById('time-current');
            const total = document.getElementById('time-total');
            const words = Array.from(document.querySelectorAll('[data-word-index]'));

            if (!audio || words.length === 0) return;

            const fmt = (t) => {
                if (!isFinite(t)) return '0:00';
                const m = Math.floor(t / 60);
                const s = Math.floor(t % 60);
                return m + ':' + String(s).padStart(2, '0');
            };

            let active = -1;
            const setActive = (index) => {
                if (index === active) return;
                if (active > -1 && words[active]) {
                    words[active].classList.remove('text-stone-900', 'bg-amber-200', 'font-semibold');
                    words[active].classList.add('text-stone-400');
                }
                active = index;
                if (active > -1 && words[active]) {
                    words[active].classList.remove('text-stone-400');
                    words[active].classList.add('text-stone-900', 'bg-amber-200', 'font-semibold');
                    words[active].scrollIntoView({ block: 'center', behavior: 'smooth' });
                }
            };

            const highlight = (time) => {
                let found = -1;
                for (let i = 0; i < words.length; i++) {
                    const start = parseFloat(words[i].dataset.start || '0');
                    const end = parseFloat(words[i].dataset.end || '0');
                    if (time >= start && (time < end || end === 0)) { found = i; break; }
                    if (start <= time) found = i;
                }
                setActive(found);
            };

            toggle.addEventListener('click', () => {
                const helper = window.sevakPoem;

                // A muted autoplay stream is running: the tap asks for sound.
                if (helper && (audio.paused || audio.muted)) {
                    helper.wake();

                    return;
                }

                audio.paused ? audio.play() : audio.pause();
            });
            audio.addEventListener('play', () => { toggle.textContent = '❚❚'; });
            audio.addEventListener('pause', () => { toggle.textContent = '▶'; });
            audio.addEventListener('loadedmetadata', () => { total.textContent = fmt(audio.duration); });
            audio.addEventListener('timeupdate', () => {
                current.textContent = fmt(audio.currentTime);
                if (audio.duration > 0) seek.value = (audio.currentTime / audio.duration) * 100;
                highlight(audio.currentTime);
            });
            seek.addEventListener('input', () => {
                if (audio.duration > 0) audio.currentTime = (seek.value / 100) * audio.duration;
            });
            words.forEach((el) => el.addEventListener('click', () => {
                audio.currentTime = parseFloat(el.dataset.start || '0');
                audio.play();
            }));
        })();
        </script>
        @endpush
    @endif

    @if ($poem->hasAudio())
        @push('scripts')
        <script>
        // Listen counter: registered once per page load, when playback starts.
        (function () {
            const audio = document.getElementById('poem-audio');
            const counter = document.getElementById('plays-count');

            if (!audio) return;

            let tracked = false;

            audio.addEventListener('play', () => {
                if (tracked) return;
                tracked = true;

                fetch(@json(route('api.poems.play', $poem)), {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                    .then((response) => response.json())
                    .then((data) => {
                        if (counter && typeof data.label === 'string' && data.label) {
                            counter.textContent = '▶ ' + data.label + ' լսում';
                        }
                    })
                    .catch(() => {});
            });
        })();

        // Autoplay + repeat preferences are shared with the Shorts player
        // (localStorage): the visitor decides once how the poems behave.
        (function () {
            let autoplay = true;
            let mode = 'sequential';

            try {
                const stored = JSON.parse(window.localStorage.getItem('sevak:player-prefs') || '{}') || {};

                if (typeof stored.autoplay === 'boolean') {
                    autoplay = stored.autoplay;
                }

                if (typeof stored.mode === 'string') {
                    mode = stored.mode;
                }
            } catch (error) {
                // Unreadable storage: keep the defaults.
            }

            const audio = document.getElementById('poem-audio');

            if (!audio) return;

            audio.loop = mode === 'loop';

            // Speed: the select opens at 0.8× (poetry reads better a touch
            // slower) and works even when there is no word alignment yet.
            const speed = document.getElementById('speed');

            if (speed) {
                audio.playbackRate = parseFloat(speed.value);
                speed.addEventListener('change', () => { audio.playbackRate = parseFloat(speed.value); });
            }

            if (!autoplay) return;

            const hint = document.getElementById('sound-hint');

            // What the first interaction does: sound on, playing.
            window.sevakPoem = {
                wake() {
                    audio.muted = false;

                    if (hint) hint.hidden = true;

                    if (audio.paused) {
                        audio.play().catch(() => {});
                    }
                },
            };

            const armGesture = () => {
                const events = ['pointerdown', 'touchstart', 'keydown', 'wheel'];

                const resume = (event) => {
                    const target = event.target;
                    const onControl = target instanceof Element
                        && Boolean(target.closest('button, a, input, select, textarea, [role="button"]'));

                    // Space is the player's own play/pause shortcut, and every
                    // control looks after its own taps.
                    if (onControl || event.code === 'Space') {
                        return;
                    }

                    stopResume();
                    window.sevakPoem.wake();
                };

                const stopResume = () => events.forEach(
                    (name) => window.removeEventListener(name, resume, true),
                );

                events.forEach(
                    (name) => window.addEventListener(name, resume, { capture: true, passive: true }),
                );
            };

            // Audible autoplay first (allowed once the visitor interacted with
            // the document), then a muted stream — that IS allowed — whose
            // sound comes back at the first interaction. That is as close to
            // autoplay as phones get.
            audio.play().catch(async () => {
                audio.muted = true;

                if (hint) hint.hidden = false;

                try {
                    await audio.play();
                } catch (error) {
                    // Even muted playback was refused: leave it to the visitor.
                    audio.muted = false;

                    if (hint) hint.hidden = true;
                }

                armGesture();
            });
        })();
        </script>
        @endpush
    @endif
@endsection
