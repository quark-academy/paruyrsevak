@extends('layouts.admin')

@section('title', 'Բանաստեղծություններ')

@section('content')
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold">Բանաստեղծություններ</h1>
            <p class="text-sm text-stone-500">{{ $poems->total() }} գրառում</p>
        </div>
        <div class="flex items-center gap-2">
            <form method="GET" action="{{ route('admin.poems.index') }}" class="flex gap-2">
                <input type="search" name="q" value="{{ $term }}" placeholder="Փնտրել…"
                       class="rounded-lg border border-stone-300 px-3 py-2 text-sm focus:border-stone-500 focus:outline-none">
                <button class="rounded-lg bg-stone-200 px-3 py-2 text-sm font-medium hover:bg-stone-300">Փնտրել</button>
            </form>
            <a href="{{ route('admin.poems.create') }}"
               class="rounded-lg bg-stone-900 px-4 py-2 text-sm font-semibold text-white hover:bg-stone-800">+ Նոր</a>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-stone-200 bg-white">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50 text-left text-xs uppercase tracking-wide text-stone-500">
                <tr>
                    <th class="px-4 py-3">Վերնագիր</th>
                    <th class="px-4 py-3">URL (slug)</th>
                    <th class="px-4 py-3">Բառեր</th>
                    <th class="px-4 py-3">Ձայն</th>
                    <th class="px-4 py-3 text-right">Գործողություններ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($poems as $poem)
                    <tr class="hover:bg-stone-50">
                        <td class="px-4 py-3 font-medium">{{ $poem->title }}</td>
                        <td class="px-4 py-3 text-stone-500"><code class="text-xs">{{ $poem->slug }}</code></td>
                        <td class="px-4 py-3 tabular-nums text-stone-600">{{ $poem->words_count }}</td>
                        <td class="px-4 py-3">
                            @if ($poem->hasAudio())
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-medium {{ $poem->is_original ? 'bg-amber-100 text-amber-800' : 'bg-sky-100 text-sky-800' }}">
                                    {{ $poem->is_original ? 'Արխիվ' : 'ԱԻ' }}
                                </span>
                            @else
                                <span class="text-xs text-stone-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2 text-xs">
                                <a href="{{ route('admin.poems.timings', $poem) }}" class="rounded-lg bg-stone-100 px-2 py-1 hover:bg-stone-200">Ժամանակացույց</a>
                                <a href="{{ route('admin.poems.show', $poem) }}" class="rounded-lg bg-stone-100 px-2 py-1 hover:bg-stone-200">Դիտել</a>
                                <a href="{{ route('admin.poems.edit', $poem) }}" class="rounded-lg bg-stone-100 px-2 py-1 hover:bg-stone-200">Խմբագրել</a>
                                <form method="POST" action="{{ route('admin.poems.destroy', $poem) }}"
                                      onsubmit="return confirm('Ջնջե՞լ {{ $poem->title }}‑ը։');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-lg bg-rose-50 px-2 py-1 text-rose-700 hover:bg-rose-100">Ջնջել</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-stone-400">Գրառումներ չկան։</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-5">{{ $poems->links() }}</div>
@endsection
