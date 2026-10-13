@php($p = $poem ?? new App\Models\Poem())

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
                <label for="meta_title" class="mb-1 block text-xs font-medium text-stone-600">SEO վերնագիր</label>
                <input id="meta_title" name="meta_title" type="text" value="{{ old('meta_title', $p->meta_title) }}"
                       class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:border-stone-500 focus:outline-none">
            </div>

            <div>
                <label for="meta_description" class="mb-1 block text-xs font-medium text-stone-600">SEO նկարագրություն</label>
                <textarea id="meta_description" name="meta_description" rows="3"
                          class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:border-stone-500 focus:outline-none">{{ old('meta_description', $p->meta_description) }}</textarea>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2">
            <a href="{{ route('admin.poems.index') }}" class="rounded-lg bg-stone-200 px-4 py-2 text-sm hover:bg-stone-300">Չեղարկել</a>
            <button type="submit" class="rounded-lg bg-stone-900 px-5 py-2 text-sm font-semibold text-white hover:bg-stone-800">Պահպանել</button>
        </div>
    </div>
</div>
