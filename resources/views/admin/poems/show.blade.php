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
            </p>
        </div>
        <div class="flex flex-wrap gap-2 text-sm">
            <a href="{{ route('poems.show', $poem) }}" target="_blank" class="rounded-lg bg-stone-200 px-3 py-2 hover:bg-stone-300">Կայք ↗</a>
            <a href="{{ route('admin.poems.timings', $poem) }}" class="rounded-lg bg-stone-200 px-3 py-2 hover:bg-stone-300">Ժամանակացույց</a>
            <a href="{{ route('admin.poems.edit', $poem) }}" class="rounded-lg bg-stone-900 px-3 py-2 font-medium text-white hover:bg-stone-800">Խմբագրել</a>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-stone-200 bg-white p-5">
            <h2 class="mb-3 text-sm font-semibold">Աուդիո</h2>

            @if ($poem->hasAudio())
                <audio controls preload="metadata" src="{{ $poem->audioUrl() }}" class="mb-3 w-full"></audio>
                <p class="mb-3 break-all text-xs text-stone-500">{{ $poem->audio_path }}</p>
            @else
                <p class="mb-3 rounded-lg border border-dashed border-stone-300 bg-stone-50 px-3 py-2 text-xs text-stone-500">
                    Աուդիո ֆայլ չկա։
                </p>
            @endif

            <form method="POST" action="{{ route('admin.poems.audio.store', $poem) }}" enctype="multipart/form-data"
                  class="mb-4 space-y-2 border-t border-stone-100 pt-4">
                @csrf
                <label class="block text-xs font-medium text-stone-600">Վերբեռնել օրիգինալ .mp3 (կփոխարինի ԱԻ-ին)</label>
                <input type="file" name="audio" accept="audio/*" required
                       class="w-full rounded-lg border border-stone-300 px-3 py-2 text-xs">
                <button type="submit" class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-600">
                    Վերբեռնել
                </button>
            </form>

            <form method="POST" action="{{ route('admin.poems.audio.generate', $poem) }}"
                  class="space-y-2 border-t border-stone-100 pt-4">
                @csrf
                <label class="block text-xs font-medium text-stone-600">ElevenLabs ԱԻ գեներացիա</label>
                <div class="flex flex-wrap items-center gap-3 text-xs text-stone-600">
                    <label class="flex items-center gap-1"><input type="checkbox" name="force" value="1"> Հարկադրել վերագեներացիա</label>
                    <label class="flex items-center gap-1"><input type="checkbox" name="sync_words" value="1" checked> Ներմուծել բառային ժամանակացույցը</label>
                </div>
                <button type="submit" class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">
                    Գեներացնել ԱԻ ձայն
                </button>
            </form>

            @if ($poem->hasAudio())
                <form method="POST" action="{{ route('admin.poems.audio.destroy', $poem) }}" class="border-t border-stone-100 pt-4"
                      onsubmit="return confirm('Հեռացնե՞լ աուդիոն։');">
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
