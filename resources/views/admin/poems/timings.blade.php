@extends('layouts.admin')

@section('title', 'Ժամանակացույց՝ '.$poem->title)

@section('content')
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('admin.poems.show', $poem) }}" class="text-xs text-stone-500 hover:text-stone-900">← {{ $poem->title }}</a>
            <h1 class="text-xl font-bold">Բառային ժամանակացույց</h1>
            <p class="text-sm text-stone-500">Ճշգրիտ կարաոկե համաժամեցում՝ ըստ վայրկյանների։</p>
            <p class="mt-1 max-w-2xl text-[11px] leading-relaxed text-stone-500">
                «Համաժամեցնել ձայնագրությունից» — բառերն ու ժամանակացույցը ստացվում են առկա աուդիոյից
                (տեքստի դեպքում՝ forced alignment, առանց տեքստի՝ transcription)։ Անվտանգ է արխիվային ձայնագրությունների համար։
                «Համաժամեցնել ElevenLabs-ից» — գեներացնում է ԱԻ ձայն և ներմուծում նրա ժամանակացույցը։
            </p>
        </div>
        <div class="flex flex-wrap gap-2 text-sm">
            <form method="POST" action="{{ route('admin.poems.audio.transcribe', $poem) }}"
                  data-confirm="Բառերն ու ժամանակացույցը կվերագրվեն աուդիոյի հիման վրա։ Շարունակե՞լ։">
                @csrf
                <button type="submit" @disabled(! $poem->hasAudio())
                        class="rounded-lg bg-emerald-600 px-3 py-2 font-medium text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">
                    Համաժամեցնել ձայնագրությունից
                </button>
            </form>
            <form method="POST" action="{{ route('admin.poems.timings.sync', $poem) }}"
                  data-confirm="Բառերը կվերագեներացվեն տեքստից․ ձեռքով արված ժամանակացույցը կարող է կորչել։ Շարունակե՞լ։">
                @csrf
                <button type="submit" class="rounded-lg bg-stone-200 px-3 py-2 hover:bg-stone-300">Վերագեներացնել բառերը</button>
            </form>
            <form method="POST" action="{{ route('admin.poems.audio.align', $poem) }}"
                  data-confirm="Կգեներացվի ԱԻ ձայն (կսպառվի ElevenLabs քվոտա) և կվերագրվի բառային ժամանակացույցը։ Շարունակե՞լ։">
                @csrf
                <button type="submit" class="rounded-lg bg-sky-600 px-3 py-2 font-medium text-white hover:bg-sky-700">Համաժամեցնել ElevenLabs-ից</button>
            </form>
        </div>
    </div>

    @if ($poem->hasAudio())
        <div class="mb-5 sticky top-0 z-20 rounded-2xl border border-stone-200 bg-white/95 p-4 backdrop-blur">
            <audio id="player" controls controlsList="nodownload noremoteplayback" disablepictureinpicture preload="metadata" src="{{ $poem->audioUrl() }}" class="w-full"></audio>
            <div class="mt-2 flex flex-wrap items-center gap-4 text-xs text-stone-600">
                <span>Ընթացիկ ժամանակ՝ <b id="now" class="tabular-nums">0.00</b> վրկ</span>
                <span class="text-stone-400">Խորհուրդ՝ հետևիր տեքստին և ընթերցման պահին սեղմիր «S» / «E»։</span>
            </div>
        </div>
    @else
        <div class="mb-5 rounded-xl border border-dashed border-stone-300 bg-white px-4 py-3 text-sm text-stone-500">
            Աուդիո չկա։ Սկզբում գեներացրու ԱԻ ձայն կամ վերբեռնիր օրիգինալ .mp3։
        </div>
    @endif

    @if ($words->isEmpty())
        <div class="rounded-2xl border border-dashed border-stone-300 bg-white px-4 py-10 text-center text-sm text-stone-500">
            Բառեր չկան։ Սեղմիր «Վերագեներացնել բառերը»։
        </div>
    @else
        <form method="POST" action="{{ route('admin.poems.timings.update', $poem) }}">
            @csrf

            @php
                // Words that no longer match the poem text (typical alignment
                // drift): the editor flags every one of them.
                $drifted = $words->filter(
                    fn ($word, $index) => isset($tokens[$index]) && $tokens[$index] !== $word->word
                )->count();
            @endphp

            <div class="mb-3 rounded-2xl border border-stone-200 bg-white p-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs text-stone-600">
                        Ընդամենը՝ <b class="tabular-nums">{{ $words->count() }}</b> բառ
                        @if ($tokens === [])
                            · <span class="text-stone-400">բանաստեղծության տեքստ չկա՝ համեմատելու համար</span>
                        @elseif ($drifted > 0)
                            · <span class="font-semibold text-amber-700">{{ $drifted }} բառ տարբերվում է տեքստից</span>
                        @else
                            · <span class="text-emerald-700">բոլորը համընկնում են տեքստի հետ</span>
                        @endif
                    </p>

                    <div class="flex flex-wrap items-center gap-2">
                        <input type="search" id="word-filter" placeholder="Զտել բառը…"
                               class="w-40 rounded-lg border border-stone-200 px-2 py-1.5 text-xs focus:border-stone-500 focus:outline-none">
                        <label class="flex items-center gap-1.5 text-xs text-stone-600">
                            <input type="checkbox" id="follow-playback" class="rounded border-stone-300">
                            Հետևել նվագարկմանը
                        </label>
                        <button type="submit"
                                class="rounded-lg bg-stone-900 px-4 py-2 text-sm font-semibold text-amber-200 hover:bg-stone-800">
                            Պահպանել
                        </button>
                    </div>
                </div>

                <div class="mt-2 flex flex-wrap items-center gap-2 border-t border-stone-100 pt-2">
                    <span class="text-[11px] uppercase tracking-wide text-stone-400">Փոխարինել</span>
                    <input type="text" id="find-text" placeholder="գտնել…"
                           class="w-36 rounded-lg border border-stone-200 px-2 py-1.5 text-xs focus:border-stone-500 focus:outline-none">
                    <span class="text-stone-400">→</span>
                    <input type="text" id="replace-text" placeholder="փոխարինել…"
                           class="w-36 rounded-lg border border-stone-200 px-2 py-1.5 text-xs focus:border-stone-500 focus:outline-none">
                    <button type="button" id="apply-replace"
                            class="rounded-lg bg-amber-200 px-3 py-1.5 text-xs font-semibold text-stone-900 hover:bg-amber-300">
                        Փոխարինել բոլորին
                    </button>
                    <button type="button" id="restore-all" title="Վերադարձնել տեքստի ուղղագրությունը"
                            class="rounded-lg border border-stone-200 px-3 py-1.5 text-xs hover:bg-stone-100">
                        ↺ Վերականգնել տեքստից
                    </button>
                    <span id="spell-result" class="text-xs font-medium text-emerald-700"></span>
                </div>

                <p class="mt-2 text-[11px] leading-relaxed text-stone-400">
                    Դեղին բառերը չեն համընկնում բանաստեղծության տեքստին․ սեղմիր «↺ դարձնել …»-ը կամ ուղղիր ձեռքով։
                    Ավելորդ բառը հեռացնելու համար՝ դատարկիր դաշտը կամ նշիր «Ջնջել»-ը ու սեղմիր «Պահպանել»։
                    «⇥ միավորել հաջորդի հետ»-ը միացնում է ԱԻ-ի բաժանած բառը, «Enter»-ը տանում է հաջորդ բառին, «▶»-ը լսում է այդ բառից։
                </p>

                @if (filled($poem->content))
                    <details class="mt-2 rounded-xl border border-stone-200 bg-stone-50 px-3 py-2">
                        <summary class="cursor-pointer text-xs font-medium text-stone-600">
                            Բանաստեղծության տեքստը (ուղղագրության աղբյուրը)
                        </summary>
                        <p class="mt-2 max-h-64 overflow-y-auto whitespace-pre-line text-sm leading-relaxed text-stone-700">{{ $poem->content }}</p>
                    </details>
                @endif
            </div>

            <div class="overflow-hidden rounded-2xl border border-stone-200 bg-white">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead class="bg-stone-50 text-left text-xs uppercase tracking-wide text-stone-500">
                        <tr>
                            <th class="w-16 px-3 py-2">#</th>
                            <th class="px-3 py-2">Բառ</th>
                            <th class="w-32 px-3 py-2">Սկիզբ (վրկ)</th>
                            <th class="w-32 px-3 py-2">Ավարտ (վրկ)</th>
                            <th class="w-24 px-3 py-2">Նշել</th>
                            <th class="w-16 px-3 py-2">Ջնջել</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($words as $index => $word)
                            @php
                                $token = $tokens[$index] ?? null;
                                $differs = $token !== null && $token !== $word->word;
                            @endphp
                            <tr class="hover:bg-stone-50" data-row="{{ $index }}" data-word-row>
                                <td class="px-3 py-1.5 align-top">
                                    <div class="flex items-center gap-1">
                                        <span class="text-xs tabular-nums text-stone-400">{{ $index + 1 }}</span>
                                        <button type="button" data-play-word="{{ $index }}" title="Լսել այս բառից"
                                                class="rounded px-1.5 py-1 text-[11px] text-stone-500 hover:bg-stone-200">▶</button>
                                    </div>
                                </td>
                                <td class="px-3 py-1.5">
                                    <input type="text" name="words[{{ $index }}][word]" value="{{ $word->word }}" data-word-input
                                           @if ($differs) title="Տեքստում՝ «{{ $token }}»" @endif
                                           class="w-full rounded border px-2 py-1 text-sm focus:border-stone-500 focus:outline-none {{ $differs ? 'border-amber-300 bg-amber-50' : 'border-stone-200' }}">
                                    <input type="hidden" name="words[{{ $index }}][word_order]" value="{{ $index }}">

                                    @if ($differs)
                                        <button type="button" data-restore="{{ $index }}" data-token="{{ $token }}"
                                                class="mt-1 text-[11px] font-medium text-amber-700 hover:underline">
                                            ↺ դարձնել «{{ $token }}»
                                        </button>
                                    @endif

                                    <button type="button" data-merge-next
                                            title="ԱԻ-ն երբեմն մեկ բառը բաժանում է երկուսի՝ այս կոճակը միացնում է հաջորդ բառի հետ"
                                            class="mt-1 block text-[11px] font-medium text-stone-400 hover:text-stone-700 hover:underline">
                                        ⇥ միավորել հաջորդի հետ
                                    </button>
                                </td>
                                <td class="px-3 py-1.5">
                                    <input type="number" step="0.01" min="0" name="words[{{ $index }}][start_time]"
                                           value="{{ number_format($word->start_time, 2, '.', '') }}" data-field="start"
                                           class="w-full rounded border border-stone-200 px-2 py-1 text-sm tabular-nums focus:border-stone-500 focus:outline-none">
                                </td>
                                <td class="px-3 py-1.5">
                                    <input type="number" step="0.01" min="0" name="words[{{ $index }}][end_time]"
                                           value="{{ number_format($word->end_time, 2, '.', '') }}" data-field="end"
                                           class="w-full rounded border border-stone-200 px-2 py-1 text-sm tabular-nums focus:border-stone-500 focus:outline-none">
                                </td>
                                <td class="px-3 py-1.5">
                                    <div class="flex gap-1">
                                        <button type="button" data-mark="start" data-row="{{ $index }}"
                                                class="rounded bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800 hover:bg-emerald-200">S</button>
                                        <button type="button" data-mark="end" data-row="{{ $index }}"
                                                class="rounded bg-rose-100 px-2 py-1 text-xs font-semibold text-rose-800 hover:bg-rose-200">E</button>
                                    </div>
                                </td>
                                <td class="px-3 py-1.5 text-center">
                                    <input type="checkbox" name="words[{{ $index }}][delete]" value="1" class="rounded border-stone-300">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-3 flex items-center justify-end">
                <button type="submit"
                        class="rounded-lg bg-stone-900 px-4 py-2 text-sm font-semibold text-amber-200 hover:bg-stone-800">
                    Պահպանել ժամանակացույցը
                </button>
            </div>
        </form>
    @endif

    @if ($poem->hasAudio() && $words->isNotEmpty())
        @push('scripts')
        <script>
        (function () {
            const player = document.getElementById('player');
            const now = document.getElementById('now');
            if (!player) return;

            const fmt = (t) => (isFinite(t) ? t.toFixed(2) : '0.00');

            player.addEventListener('timeupdate', () => {
                if (now) now.textContent = fmt(player.currentTime);
            });

            document.querySelectorAll('[data-mark]').forEach((button) => {
                button.addEventListener('click', () => {
                    const row = button.dataset.row;
                    const field = button.dataset.mark;
                    const input = document.querySelector('[name="words[' + row + '][' + field + '_time]"]');
                    if (!input) return;

                    input.value = player.currentTime.toFixed(2);
                    input.classList.add('bg-amber-100');

                    const next = field === 'start'
                        ? document.querySelector('[name="words[' + row + '][end_time]"]')
                        : document.querySelector('[name="words[' + (Number(row) + 1) + '][start_time]"]');

                    if (next) next.focus();
                });
            });

            document.addEventListener('keydown', (event) => {
                const tag = (event.target.tagName || '').toLowerCase();
                if (tag === 'input' || tag === 'textarea' || tag === 'select') return;

                if (event.code === 'Space') {
                    event.preventDefault();
                    player.paused ? player.play() : player.pause();
                }
            });

            // Listen from a single word: spelling is easiest to check by ear.
            document.querySelectorAll('[data-play-word]').forEach((button) => {
                button.addEventListener('click', () => {
                    const start = document.querySelector('[name="words[' + button.dataset.playWord + '][start_time]"]');
                    if (!start) return;

                    player.currentTime = parseFloat(start.value || '0') || 0;
                    player.play();
                });
            });

            // Highlight the word being played so a wrong one is easy to spot.
            const rows = Array.from(document.querySelectorAll('tr[data-word-row]'));
            const follow = document.getElementById('follow-playback');
            let activeRow = -1;

            const bounds = (row) => {
                const start = document.querySelector('[name="words[' + row + '][start_time]"]');
                const end = document.querySelector('[name="words[' + row + '][end_time]"]');

                return [parseFloat(start?.value || '0') || 0, parseFloat(end?.value || '0') || 0];
            };

            function markActive(time) {
                let found = -1;

                for (let i = 0; i < rows.length; i++) {
                    const [start, end] = bounds(i);

                    if (time >= start && time < end) { found = i; break; }
                    if (start <= time) found = i;
                }

                if (found === activeRow) return;

                rows[activeRow]?.classList.remove('bg-amber-100');
                activeRow = found;

                const row = rows[activeRow];

                if (row) {
                    row.classList.add('bg-amber-100');

                    if (follow?.checked) {
                        row.scrollIntoView({ block: 'center', behavior: 'smooth' });
                    }
                }
            }

            player.addEventListener('timeupdate', () => markActive(player.currentTime));
        })();
        </script>
        @endpush
    @endif

    @if ($words->isNotEmpty())
        @push('scripts')
        <script>
        // Spelling tools: they work with or without a recording, because the
        // word list can be corrected before (or after) the audio is generated.
        (function () {
            const inputs = Array.from(document.querySelectorAll('[data-word-input]'));
            if (inputs.length === 0) return;

            const result = document.getElementById('spell-result');
            const notify = (text) => {
                if (!result) return;

                result.textContent = text;
                window.setTimeout(() => {
                    if (result.textContent === text) result.textContent = '';
                }, 4000);
            };

            const inputFor = (row) => document.querySelector('[data-word-input][name="words[' + row + '][word]"]');

            // Enter jumps to the next word: a whole poem can be fixed quickly.
            inputs.forEach((input, position) => {
                input.addEventListener('keydown', (event) => {
                    if (event.key !== 'Enter') return;

                    event.preventDefault();
                    const next = inputs[position + 1];

                    if (next) {
                        next.focus();
                        next.select();
                    }
                });
            });

            document.getElementById('apply-replace')?.addEventListener('click', () => {
                const find = document.getElementById('find-text').value;
                const replace = document.getElementById('replace-text').value;

                if (find === '') {
                    notify('Գրիր, թե ինչ փնտրել։');

                    return;
                }

                let changed = 0;

                inputs.forEach((input) => {
                    if (!input.value.includes(find)) return;

                    input.value = input.value.split(find).join(replace);
                    changed++;
                });

                notify(changed > 0
                    ? 'Փոխվեց ' + changed + ' բառ․ մի մոռացիր պահպանել։'
                    : 'Ոչինչ չգտնվեց։');
            });

            document.querySelectorAll('[data-restore]').forEach((button) => {
                button.addEventListener('click', () => {
                    const input = inputFor(button.dataset.restore);

                    if (input) input.value = button.dataset.token;
                });
            });

            document.getElementById('restore-all')?.addEventListener('click', () => {
                let changed = 0;

                document.querySelectorAll('[data-restore]').forEach((button) => {
                    const input = inputFor(button.dataset.restore);

                    if (input && input.value !== button.dataset.token) {
                        input.value = button.dataset.token;
                        changed++;
                    }
                });

                notify(changed > 0
                    ? 'Վերականգնվեց ' + changed + ' բառ։'
                    : 'Բոլոր բառերն արդեն համընկնում են տեքստի հետ։');
            });

            document.getElementById('word-filter')?.addEventListener('input', (event) => {
                const needle = event.target.value.trim().toLowerCase();

                document.querySelectorAll('tr[data-word-row]').forEach((row) => {
                    const input = row.querySelector('[data-word-input]');
                    const show = needle === '' || (input?.value.toLowerCase().includes(needle) ?? false);

                    row.classList.toggle('hidden', !show);
                });
            });

            // Deleting: a ticked "delete" box dims the row, and emptying the
            // text ticks it automatically — the quickest way to drop a word
            // that is not in the recording.
            const rowOf = (input) => input.closest('tr[data-word-row]');
            const deleteBox = (row) => row?.querySelector('input[name$="[delete]"]');

            const paintDeleted = (row) => {
                if (!row) return;

                const dropped = Boolean(deleteBox(row)?.checked);
                row.classList.toggle('opacity-40', dropped);
                row.classList.toggle('line-through', dropped);
            };

            document.querySelectorAll('input[name$="[delete]"]').forEach((box) => {
                const row = box.closest('tr[data-word-row]');

                paintDeleted(row);
                box.addEventListener('change', () => paintDeleted(row));
            });

            inputs.forEach((input) => {
                input.addEventListener('input', () => {
                    const row = rowOf(input);
                    const box = deleteBox(row);

                    if (!box) return;

                    if (input.value.trim() === '') {
                        box.checked = true;
                        box.dataset.auto = '1';
                    } else if (box.dataset.auto === '1') {
                        box.checked = false;
                        delete box.dataset.auto;
                    }

                    paintDeleted(row);
                });
            });

            // The AI sometimes splits one word into two («Ինչ» + «էր»): this
            // merges the pair, keeps the timing span and drops the second row.
            document.querySelectorAll('[data-merge-next]').forEach((button) => {
                button.addEventListener('click', () => {
                    const row = button.closest('tr[data-word-row]');
                    const next = row?.nextElementSibling;
                    const input = row?.querySelector('[data-word-input]');
                    const nextInput = next?.querySelector('[data-word-input]');

                    if (!row || !next || !input || !nextInput) return;

                    input.value = (input.value.trim() + ' ' + nextInput.value.trim()).trim();

                    const end = next.querySelector('input[name$="[end_time]"]');
                    const rowEnd = row.querySelector('input[name$="[end_time]"]');

                    if (end && rowEnd) rowEnd.value = end.value;

                    const box = deleteBox(next);

                    if (box) {
                        box.checked = true;
                        delete box.dataset.auto;
                    }

                    paintDeleted(next);
                    input.focus();
                });
            });
        })();
        </script>
        @endpush
    @endif
@endsection
