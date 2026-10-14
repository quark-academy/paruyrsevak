<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    /** Search endpoint, taken from the player payload. */
    url: { type: String, default: '' },
    /** The poem's gold, so the panel belongs to the player. */
    accent: { type: String, default: '#e8c46a' },
});

const emit = defineEmits(['close', 'select']);

const IDLE = 'Գրիր առնվազն երկու տառ։';

const term = ref('');
const results = ref([]);
const allUrl = ref('');
const message = ref(IDLE);
const input = ref(null);

let timer = null;
let request = null;

watch(term, (value) => {
    window.clearTimeout(timer);

    const query = value.trim();

    if (query.length < 2) {
        results.value = [];
        message.value = IDLE;

        return;
    }

    timer = window.setTimeout(() => search(query), 220);
});

async function search(query) {
    if (!props.url) {
        return;
    }

    request?.abort();
    request = new AbortController();

    try {
        const response = await fetch(`${props.url}?q=${encodeURIComponent(query)}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            signal: request.signal,
        });
        const data = await response.json();

        results.value = Array.isArray(data.results) ? data.results : [];
        allUrl.value = typeof data.allUrl === 'string' ? data.allUrl : '';
        message.value = results.value.length ? '' : (data.message || 'Ոչինչ չգտնվեց։');
    } catch (error) {
        // An abort is only the next keystroke arriving first.
        if (error?.name !== 'AbortError') {
            results.value = [];
            message.value = 'Ցանցի սխալ։ Փորձիր նորից։';
        }
    }
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        emit('close');
    }
}

onMounted(() => {
    document.addEventListener('keydown', onKeydown);
    window.setTimeout(() => input.value?.focus(), 40);
});

onBeforeUnmount(() => {
    window.clearTimeout(timer);
    request?.abort();
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div
        class="absolute inset-0 z-40 flex items-start justify-center bg-black/60 px-4 pt-[12vh] backdrop-blur-sm"
        @click.self="emit('close')"
    >
        <div class="w-full max-w-lg overflow-hidden rounded-3xl border border-white/10 bg-[#141312] shadow-2xl">
            <div class="flex items-center gap-2 border-b border-white/10 px-4 py-3">
                <svg class="h-5 w-5 shrink-0 text-white/35" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="7" />
                    <path d="M20 20l-3.6-3.6" />
                </svg>

                <input
                    ref="input"
                    v-model="term"
                    type="search"
                    autocomplete="off"
                    enterkeyhint="search"
                    placeholder="Փնտրել վերնագիր կամ տող…"
                    class="w-full bg-transparent py-1 text-base text-stone-100 placeholder-white/35 focus:outline-none"
                >

                <button
                    type="button"
                    class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-white/40 transition hover:bg-white/10 hover:text-white"
                    aria-label="Փակել"
                    @click="emit('close')"
                >✕</button>
            </div>

            <div class="max-h-[55vh] overflow-y-auto p-2">
                <p v-if="message" class="px-3 py-6 text-center text-sm text-white/40">{{ message }}</p>

                <template v-else>
                    <!-- A match is played in place: the player keeps its gesture. -->
                    <button
                        v-for="result in results"
                        :key="result.slug"
                        type="button"
                        class="flex w-full items-center gap-3 rounded-2xl px-3 py-2.5 text-left transition hover:bg-white/[0.06]"
                        @click="emit('select', result)"
                    >
                        <span class="min-w-0 flex-1 truncate text-sm font-medium text-stone-100">{{ result.title }}</span>
                        <span
                            class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium text-white/60"
                            :style="{ backgroundColor: accent + '26' }"
                        >{{ result.label }}</span>
                    </button>

                    <a
                        v-if="allUrl"
                        :href="allUrl"
                        class="mt-1 block rounded-2xl px-3 py-2.5 text-center text-sm font-medium text-white/45 transition hover:bg-white/[0.06] hover:text-white"
                    >
                        Բոլոր արդյունքները →
                    </a>
                </template>
            </div>
        </div>
    </div>
</template>
