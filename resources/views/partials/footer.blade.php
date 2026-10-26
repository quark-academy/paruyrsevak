{{-- Minimal site footer: the name, the useful links and the mark of the
     selection. Kept short on purpose — the poems are the page. --}}
<footer class="mt-10 border-t border-stone-200/80">
    <div class="mx-auto flex max-w-3xl flex-col items-center gap-4 px-4 py-7 text-center text-xs text-stone-400 sm:flex-row sm:justify-between sm:text-left">
        <p class="leading-relaxed">
            © {{ date('Y') }} {{ config('app.name') }}
            <span class="hidden sm:inline">·</span>
            <span class="block sm:inline">Նվիրված Պարույր Սևակին</span>
        </p>

        <nav class="flex flex-wrap items-center justify-center gap-x-4 gap-y-2" aria-label="Ֆուտեր">
            <a href="{{ route('favorites.index') }}" class="transition hover:text-stone-700">Իմ ընտրանին</a>
            <a href="{{ route('terms') }}" class="transition hover:text-stone-700">Օգտագործման պայմաններ և տվյալներ</a>
            <span class="font-medium tracking-wide text-stone-500" title="Իմ ընտրանին">Im @Ntranin</span>
        </nav>
    </div>
</footer>
