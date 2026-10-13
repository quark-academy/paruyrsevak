@extends('layouts.admin')

@section('title', 'Գլխավոր')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold">Գլխավոր</h1>
            <p class="text-sm text-stone-500">Համակարգի ամփոփ վիճակագրություն</p>
        </div>
        <a href="{{ route('admin.poems.create') }}"
           class="rounded-lg bg-stone-900 px-4 py-2 text-sm font-semibold text-white hover:bg-stone-800">
            + Նոր բանաստեղծություն
        </a>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @php
            $cards = [
                ['Բանաստեղծություններ', $poemsCount, 'bg-white'],
                ['Բառեր (կարաոկե)', $wordsCount, 'bg-white'],
                ['Արխիվային ձայն', $originalCount, 'bg-amber-50'],
                ['ԱԻ ձայն', $aiCount, 'bg-sky-50'],
            ];
        @endphp

        @foreach ($cards as [$label, $value, $bg])
            <div class="rounded-2xl border border-stone-200 {{ $bg }} p-4">
                <div class="text-3xl font-bold tabular-nums">{{ number_format($value) }}</div>
                <div class="mt-1 text-xs text-stone-500">{{ $label }}</div>
            </div>
        @endforeach
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-stone-200 bg-white p-5">
            <h2 class="mb-2 text-sm font-semibold">ElevenLabs ինտեգրացիա</h2>
            @if ($elevenLabsReady)
                <p class="text-sm text-emerald-700">✓ Կարգավորված է։ ԱԻ ձայնը հասանելի է։</p>
            @else
                <p class="text-sm text-rose-700">✗ Կարգավորված չէ։</p>
                <p class="mt-2 text-xs text-stone-500">
                    Ավելացրու <code class="rounded bg-stone-100 px-1">ELEVENLABS_API_KEY</code> և
                    <code class="rounded bg-stone-100 px-1">ELEVENLABS_VOICE_ID</code> <code>.env</code> ֆայլում։
                </p>
            @endif
        </div>

        <div class="rounded-2xl border border-stone-200 bg-white p-5">
            <h2 class="mb-2 text-sm font-semibold">Աշխատանքային հոսք</h2>
            <ol class="list-inside list-decimal space-y-1 text-xs text-stone-600">
                <li>Ստեղծիր բանաստեղծություն և ավելացրու տեքստը։</li>
                <li>Վերբեռնիր օրիգինալ .mp3 կամ գեներացրու ԱԻ ձայնը։</li>
                <li>Բացիր «Ժամանակացույց» և ճշգրտիր բառային համաժամեցումը։</li>
                <li>Հրապարակիր․ բովանդակությունը հասանելի է հանրությանը։</li>
            </ol>
        </div>
    </div>
@endsection
