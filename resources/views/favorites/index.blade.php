@extends('layouts.app')

@section('title', 'Իմ ընտրանին · '.config('app.name'))
@section('meta_description', 'Ձեր ընտրած Պարույր Սևակի բանաստեղծությունները՝ պահված միայն այս դիտարկիչում։')
@section('canonical', \App\Support\Seo::canonical('favorites'))

@push('meta')
    <meta name="robots" content="noindex, follow">
@endpush

@section('content')
    <div data-favorites-page>
        <header class="mb-5">
            <h1 class="text-2xl font-bold tracking-tight">Իմ ընտրանին</h1>
            <p class="mt-1 text-sm leading-relaxed text-stone-500">
                Սրտիկով նշված բանաստեղծությունները։ Ցանկը պահվում է միայն այս դիտարկիչում և մեզ չի ուղարկվում։
                <a href="{{ route('terms') }}" class="underline decoration-stone-300 underline-offset-2 hover:text-stone-700">Մանրամասներ</a>
            </p>
        </header>

        <div data-favorites-list class="grid gap-1.5 sm:grid-cols-2"></div>

        <div data-favorites-empty class="rounded-3xl border border-dashed border-stone-300 bg-white px-6 py-14 text-center">
            <p class="text-sm text-stone-500">Դեռ ոչինչ ընտրված չէ։</p>
            <p class="mt-1 text-xs text-stone-400">Սեղմիր սրտիկը ցանկացած բանաստեղծության կողքին։</p>
            <a href="{{ route('poems.index') }}"
               class="mt-4 inline-flex items-center justify-center rounded-full px-5 py-2.5 text-sm font-semibold text-stone-900 shadow-sm transition active:scale-[0.98]"
               style="background-color: #e8c46a;">
                Բոլոր բանաստեղծությունները
            </a>
        </div>
    </div>

    {{-- Cloned by favorites.js for every stored poem. --}}
    <template id="favorite-card-template">
        <div data-card class="group relative">
            <a data-favorite-link href="#"
               class="flex items-center gap-3 rounded-2xl border border-stone-200 bg-white py-3 pl-3.5 pr-12 shadow-sm transition hover:border-amber-200 hover:shadow-md hover:shadow-stone-900/5">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full border border-stone-200 text-[11px] text-stone-500 transition group-hover:border-transparent group-hover:bg-stone-900 group-hover:text-amber-200">
                    ▶
                </span>

                <span class="min-w-0 flex-1">
                    <span data-favorite-title class="block text-sm font-semibold leading-snug"></span>
                    <span data-favorite-excerpt class="mt-0.5 block text-xs leading-relaxed text-stone-500"></span>
                </span>
            </a>

            <button type="button"
                    data-favorite
                    aria-pressed="true"
                    aria-label="Հեռացնել իմ ընտրանուց"
                    title="Հեռացնել իմ ընտրանուց"
                    class="absolute right-2 top-1/2 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-full text-rose-500 transition hover:bg-stone-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-400 active:scale-90">
                <svg data-favorite-icon class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1L12 21l7.7-7.6 1.1-1a5.5 5.5 0 0 0 0-7.8Z" />
                </svg>
            </button>
        </div>
    </template>
@endsection
