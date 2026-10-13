@extends('layouts.admin')

@section('title', $poem->title)

@section('content')
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('admin.poems.index') }}" class="text-xs text-stone-500 hover:text-stone-900">← Բոլորը</a>
            <h1 class="text-xl font-bold">{{ $poem->title }}</h1>
            <p class="mt-1 flex flex-wrap items-center gap-2 text-xs text-stone-500">
                <code class="rounded bg-stone-100 px-1">{{ $poem->slug }}</code>
                <span class="rounded-full px-2 py-0.5 {{ $poem->is_original ? 'bg-amber-100 text-amber-800' : 'bg-sky-100 text-sky-800' }}">
                    {{ $poem->voiceSourceLabel() }}
                </span>
                <span>{{ $poem->words->count() }} բառ</span>
                <span class="tabular-nums">▶ {{ $poem->playsLabel() }} լսում</span>
            </p>
        </div>
        <div class="flex flex-wrap gap-2 text-sm">
            <a href="{{ route('poems.show', ['poem' => $poem]) }}" target="_blank" class="rounded-lg bg-stone-200 px-3 py-2 hover:bg-stone-300">Կայք ↗</a>
            <a href="{{ route('admin.poems.timings', $poem) }}" class="rounded-lg bg-stone-200 px-3 py-2 hover:bg-stone-300">Ժամանակացույց</a>
            <a href="{{ route('admin.poems.edit', $poem) }}" class="rounded-lg bg-stone-900 px-3 py-2 font-medium text-white hover:bg-stone-800">Խմբագրել</a>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-stone-200 bg-white p-5">
            <h2 class="mb-3 text-sm font-semibold">Աուդիո</h2>

            @if ($poem->is_original)
                <p class="mb-3 rounded-lg bg-amber-50 px-3 py-2 text-[11px] leading-relaxed text-amber-900">
                    Սա արխիվային ձայնագրություն է։ Դրանից <b>տեքստ և ժամանակացույց</b> ստանալու համար օգտագործիր
                    «Համաժամեցնել ձայնագրությունից» կոճակը (ներքևի ԱԻ բլոկը ստեղծում է նոր ԱԻ ձայն)։
                </p>
            @endif

            @if ($poem->hasAudio())
                <audio controls controlsList="nodownload noremoteplayback" disablepictureinpicture preload="metadata" src="{{ $poem->audioUrl() }}" class="mb-3 w-full"></audio>
                <p class="mb-3 break-all text-xs text-stone-500">{{ $poem->audio_path }}</p>
            @else
                <p class="mb-3 rounded-lg border border-dashed border-stone-300 bg-stone-50 px-3 py-2 text-xs text-stone-500">
                    Աուդիո ֆայլ չկա։
                </p>
            @endif

            <form method="POST" action="{{ route('admin.poems.audio.store', $poem) }}" enctype="multipart/form-data"
                  data-confirm="Վերբեռնե՞լ նոր աուդիո և փոխարինել ընթացիկը։"
                  class="mb-4 space-y-2 border-t border-stone-100 pt-4">
                @csrf
                <label class="block text-xs font-medium text-stone-600">Վերբեռնել օրիգինալ .mp3 (կփոխարինի ԱԻ-ին)</label>
                <input type="file" name="audio" accept="audio/*" required
                       class="w-full rounded-lg border border-stone-300 px-3 py-2 text-xs">
                <button type="submit" class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-600">
                    Վերբեռնել
                </button>
            </form>

            {{-- Safest path for archive recordings: words + timings straight from the audio. --}}
            <form method="POST" action="{{ route('admin.poems.audio.transcribe', $poem) }}"
                  data-confirm="Բառացի ժամանակացույցը կվերագրվի ձայնագրության հիման վրա։ Շարունակե՞լ։"
                  class="space-y-2 border-t border-stone-100 pt-4">
                @csrf
                <label class="block text-xs font-medium text-stone-600">Կարաոկե բառեր ձայնագրությունից</label>
                <p class="text-[11px] leading-relaxed text-stone-500">
                    @if ($poem->hasRealText())
                        Տեքստը կհամաժամեցվի աուդիոյի հետ (ElevenLabs forced alignment)։
                    @else
                        Տեքստ դեռ չկա․ ձայնագրությունը կներմուծվի բառերով, տեքստը կլրացվի ավտոմատ։
                    @endif
                </p>
                <button type="submit" @disabled(! $poem->hasAudio())
                        class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">
                    Համաժամեցնել ձայնագրությունից
                </button>
            </form>

            <form method="POST" action="{{ route('admin.poems.audio.generate', $poem) }}"
                  data-confirm="Գեներացնե՞լ ԱԻ ձայն և դարձնել այն ակտիվ աուդիո։"
                  class="space-y-2 border-t border-stone-100 pt-4">
                @csrf
                <label class="block text-xs font-medium text-stone-600">ElevenLabs ԱԻ գեներացիա</label>
                <div class="flex flex-wrap items-center gap-3 text-xs text-stone-600">
                    <label class="flex items-center gap-1">
                        <input type="checkbox" name="force" value="1"
                               data-confirm-when-checked="Հարկադրել վերագեներացիա՞ն․ կսպառվի նոր ElevenLabs քվոտա։">
                        Հարկադրել վերագեներացիա
                    </label>
                    <label class="flex items-center gap-1">
                        <input type="checkbox" name="sync_words" value="1" checked>
                        Ներմուծել բառային ժամանակացույցը
                    </label>
                </div>
                @if ($poem->is_original && $poem->hasAudio())
                    <label class="flex items-start gap-2 rounded-lg bg-amber-50 px-3 py-2 text-[11px] text-amber-900">
                        <input type="checkbox" name="replace_original" value="1" class="mt-0.5"
                               data-confirm-when-checked="Փոխարինե՞լ արխիվային ձայնագրությունը ԱԻ ձայնով։ Բնօրինակը կդադարի ակտիվ աուդիո լինելուց (ֆայլը մնում է սկավառակի վրա)։">
                        <span>Փոխարինել բնօրինակ (արխիվային) ձայնագրությունը ԱԻ ձայնով։ Առանց այս նշիչի ԱԻ ձայնը չի դառնա ակտիվ աուդիո։</span>
                    </label>
                @endif
                <ul class="list-inside list-disc space-y-1 text-[11px] leading-relaxed text-stone-500">
                    <li>«Հարկադրել վերագեներացիա» — նոր ԱԻ աուդիո՝ անտեսելով քեշը (ծախսվում է ElevenLabs քվոտա)։</li>
                    <li>«Ներմուծել բառային ժամանակացույցը» — ԱԻ աուդիոյի ժամանակացույցը կգրանցվի որպես կարաոկե բառեր (կվերագրի ընթացիկները)։</li>
                    <li>Արխիվային ձայնագրության դեպքում այս բլոկը ոչինչ չի փոխի, քանի դեռ նշված չէ «Փոխարինել բնօրինակը»։</li>
                </ul>
                <button type="submit" class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">
                    Գեներացնել ԱԻ ձայն
                </button>
            </form>

            @if ($poem->hasAudio())
                <form method="POST" action="{{ route('admin.poems.audio.destroy', $poem) }}" class="border-t border-stone-100 pt-4"
                      data-confirm="Հեռացնե՞լ աուդիոն։">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700 hover:bg-rose-100">Հեռացնել աուդիոն</button>
                </form>
            @endif
        </div>

        <div class="rounded-2xl border border-stone-200 bg-white p-5">
            <h2 class="mb-3 text-sm font-semibold">Տեքստ</h2>
            <div class="max-h-96 overflow-auto whitespace-pre-line rounded-lg bg-stone-50 p-3 text-sm leading-relaxed text-stone-700">{{ $poem->content }}</div>

            @if ($poem->words->isNotEmpty())
                <h3 class="mt-4 mb-2 text-xs font-semibold text-stone-600">Առաջին բառերը (կարաոկե)</h3>
                <div class="flex max-h-40 flex-wrap gap-1 overflow-auto text-xs">
                    @foreach ($poem->words->take(40) as $word)
                        <span class="rounded bg-stone-100 px-1.5 py-0.5 tabular-nums text-stone-600">
                            {{ $word->word }} <span class="text-stone-400">{{ number_format($word->start_time, 2) }}–{{ number_format($word->end_time, 2) }}</span>
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
