@extends('layouts.admin')

@section('title', 'Ժամանակացույց՝ '.$poem->title)

@section('content')
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('admin.poems.show', $poem) }}" class="text-xs text-stone-500 hover:text-stone-900">← {{ $poem->title }}</a>
            <h1 class="text-xl font-bold">Բառային ժամանակացույց</h1>
            <p class="text-sm text-stone-500">Ճշգրիտ կարաոկե համաժամեցում՝ ըստ վայրկյանների։</p>
        </div>
        <div class="flex flex-wrap gap-2 text-sm">
            <form method="POST" action="{{ route('admin.poems.timings.sync', $poem) }}">
                @csrf
                <button class="rounded-lg bg-stone-200 px-3 py-2 hover:bg-stone-300">Վերագեներացնել բառերը</button>
            </form>
            <form method="POST" action="{{ route('admin.poems.audio.align', $poem) }}">
                @csrf
                <button class="rounded-lg bg-sky-600 px-3 py-2 font-medium text-white hover:bg-sky-700">Համաժամեցնել ElevenLabs-ից</button>
            </form>
        </div>
    </div>

    @if ($poem->hasAudio())
        <div class="mb-5 sticky top-0 z-20 rounded-2xl border border-stone-200 bg-white/95 p-4 backdrop-blur">
            <audio id="player" controls preload="metadata" src="{{ $poem->audioUrl() }}" class="w-full"></audio>
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

            <div class="overflow-hidden rounded-2xl border border-stone-200 bg-white">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead class="bg-stone-50 text-left text-xs uppercase tracking-wide text-stone-500">
                        <tr>
                            <th class="w-10 px-3 py-2">#</th>
                            <th class="px-3 py-2">Բառ</th>
                            <th class="w-32 px-3 py-2">Սկիզբ (վրկ)</th>
                            <th class="w-32 px-3 py-2">Ավարտ (վրկ)</th>
                            <th class="w-24 px-3 py-2">Նշել</th>
                            <th class="w-16 px-3 py-2">Ջնջել</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($words as $index => $word)
                            <tr class="hover:bg-stone-50" data-row="{{ $index }}">
                                <td class="px-3 py-1.5 text-xs tabular-nums text-stone-400">{{ $index + 1 }}</td>
                                <td class="px-3 py-1.5">
                                    <input type="text" name="words[{{ $index }}][word]" value="{{ $word->word }}"
                                           class="w-full rounded border border-stone-200 px-2 py-1 text-sm focus:border-stone-500 focus:outline-none">
                                    <input type="hidden" name="words[{{ $index }}][word_order]" value="{{ $index }}">
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
        })();
        </script>
        @endpush
    @endif
@endsection
