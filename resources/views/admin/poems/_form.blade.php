@php
    $p = $poem ?? new App\Models\Poem();
    $selectedThemes = old('themes', $p->themes ?? []);
    $selectedThemes = is_array($selectedThemes) ? $selectedThemes : [];
@endphp

<div class="grid gap-4 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-4">
        <div class="rounded-2xl border border-stone-200 bg-white p-5 space-y-4">
            <div>
                <label for="title" class="mb-1 block text-xs font-medium text-stone-600">Վերնագիր *</label>
                <input id="title" name="title" type="text" required value="{{ old('title', $p->title) }}"
                       class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:border-stone-500 focus:outline-none">
            </div>

            <div>
                <label for="slug" class="mb-1 block text-xs font-medium text-stone-600">URL (slug) — կամընտիր</label>
                <input id="slug" name="slug" type="text" value="{{ old('slug', $p->slug) }}"
                       placeholder="ավտոմատ կգեներացվի վերնագրից"
                       class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:border-stone-500 focus:outline-none">
            </div>

            <div>
                <label for="content" class="mb-1 block text-xs font-medium text-stone-600">Տեքստ *</label>
                <textarea id="content" name="content" rows="14" required
                          class="w-full rounded-lg border border-stone-300 px-3 py-2 font-mono text-sm leading-relaxed focus:border-stone-500 focus:outline-none">{{ old('content', $p->content) }}</textarea>
            </div>
        </div>
    </div>

    <div class="space-y-4">
        <div class="rounded-2xl border border-stone-200 bg-white p-5 space-y-4">
            <h2 class="text-sm font-semibold">Կարգավորումներ</h2>

            <label class="flex items-start gap-2 text-sm">
                <input type="hidden" name="is_original" value="0">
                <input type="checkbox" name="is_original" value="1" class="mt-0.5"
                       @checked(old('is_original', $p->is_original))>
                <span>Արխիվային (օրիգինալ) ձայն</span>
            </label>
            <p class="text-[11px] text-stone-500">Անջատ դեպքում օգտագործվում է ԱԻ գեներացված ձայնը։</p>

            <div>
                <span class="mb-1 block text-xs font-medium text-stone-600">Թեմաներ</span>
                <input type="hidden" name="themes[]" value="">
                <div class="grid grid-cols-2 gap-2">
                    @foreach (\App\Support\PoemThemes::LABELS as $themeKey => $themeLabel)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="themes[]" value="{{ $themeKey }}" class="rounded border-stone-300"
                                   @checked(in_array($themeKey, $selectedThemes, true))>
                            <span>{{ $themeLabel }}</span>
                        </label>
                    @endforeach
                </div>
                <p class="mt-1 text-[11px] text-stone-500">Ընտրիր մեկ կամ մի քանի թեմա։</p>
            </div>

            <div>
                <label for="meta_title" class="mb-1 block text-xs font-medium text-stone-600">SEO վերնագիր (կամընտիր)</label>
                <input id="meta_title" name="meta_title" type="text" value="{{ old('meta_title', $p->meta_title) }}"
                       placeholder="դատարկ թողեք՝ ավտոմատ գեներացնելու համար"
                       class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:border-stone-500 focus:outline-none">
                <p class="mt-1 text-[11px] leading-relaxed text-stone-500">
                    Դատարկ դեպքում վերնագիրը կառուցվում է ինքնաշխատ՝ «… — {{ \App\Support\Seo::READING_PHRASE }}»։
                </p>
            </div>

            <div>
                <label for="meta_description" class="mb-1 block text-xs font-medium text-stone-600">SEO նկարագրություն (կամընտիր)</label>
                <textarea id="meta_description" name="meta_description" rows="3"
                          placeholder="դատարկ թողեք՝ ավտոմատ գեներացնելու համար"
                          class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:border-stone-500 focus:outline-none">{{ old('meta_description', $p->meta_description) }}</textarea>
            </div>

            @if ($p->exists)
                @php($generated = \App\Support\Seo::poemPage($p))
                <div class="rounded-xl border border-stone-200 bg-stone-50 p-3">
                    <p class="text-[11px] font-medium uppercase tracking-wide text-stone-500">SEO-ն ինչպես կտեսնեն որոնողականները</p>
                    <p class="mt-2 text-sm font-medium leading-snug text-sky-800">{{ $generated['title'] }}</p>
                    <p class="mt-1 text-[11px] leading-relaxed text-stone-600">{{ $generated['description'] }}</p>
                </div>
            @endif
        </div>

        <div class="flex items-center justify-end gap-2">
            <a href="{{ route('admin.poems.index') }}" class="rounded-lg bg-stone-200 px-4 py-2 text-sm hover:bg-stone-300">Չեղարկել</a>
            <button type="submit" class="rounded-lg bg-stone-900 px-5 py-2 text-sm font-semibold text-white hover:bg-stone-800">Պահպանել</button>
        </div>
    </div>
</div>
