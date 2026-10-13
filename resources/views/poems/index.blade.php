@extends('layouts.app')

@section('title', $term !== '' ? "Որոնում՝ {$term}" : 'Բանաստեղծություններ')
@section('meta_description', \App\Support\Seo::defaultDescription())
@section('canonical', \App\Support\Seo::canonical())

@section('content')
    <section class="mb-6 rounded-2xl bg-stone-900 px-5 py-6 text-stone-50">
        <h1 class="text-2xl font-bold leading-tight">Պարույր Սևակի ձայնը</h1>
        <p class="mt-2 text-sm text-stone-300">
            Արխիվային ձայնագրություններ և ԱԻ ընթերցում՝ բառացի կարաոկե համաժամեցմամբ։
        </p>

        <form method="GET" action="{{ route('poems.index') }}" class="mt-4 flex gap-2">
            <input type="search" name="q" value="{{ $term }}" placeholder="Փնտրել վերնագիր կամ տող…"
                   class="w-full rounded-xl border border-stone-700 bg-stone-800 px-3 py-2 text-sm text-stone-100 placeholder-stone-500 focus:border-stone-500 focus:outline-none">
            <button type="submit" class="rounded-xl bg-stone-100 px-4 py-2 text-sm font-semibold text-stone-900">Փնտրել</button>
        </form>
    </section>

    @if ($poems->isEmpty())
        <div class="rounded-2xl border border-dashed border-stone-300 bg-white px-5 py-10 text-center text-sm text-stone-500">
            Բանաստեղծություններ դեռ չկան։
        </div>
    @else
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ($poems as $poem)
                <a href="{{ \App\Support\Seo::poemUrl($poem) }}"
                   class="group flex flex-col rounded-2xl border border-stone-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="text-base font-semibold leading-snug">{{ $poem->title }}</h2>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium {{ $poem->is_original ? 'bg-amber-100 text-amber-800' : 'bg-sky-100 text-sky-800' }}">
                            {{ $poem->is_original ? 'Արխիվ' : 'ԱԻ' }}
                        </span>
                    </div>

                    <p class="mt-2 line-clamp-3 text-sm text-stone-600">{{ $poem->excerpt(120) }}</p>

                    <div class="mt-3 flex items-center gap-3 text-[11px] text-stone-400">
                        <span class="tabular-nums" title="Լսումների քանակ">▶ {{ $poem->playsLabel() }} լսում</span>
                        <span>· {{ $poem->words_count }} բառ</span>
                        @if ($poem->hasAudio())
                            <span>· աուդիո պատրաստ է</span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $poems->links() }}
        </div>
    @endif
@endsection
