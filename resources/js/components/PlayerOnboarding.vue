<script setup>
import { ref } from 'vue';

defineProps({
    gold: { type: String, default: '#e8c46a' },
});

const emit = defineEmits(['done']);

const SLIDES = [
    {
        icon: '↕',
        title: 'Սահեցրու՝ հաջորդ բանաստեղծությունը',
        text: 'Սահեցրու վերև կամ ներքև՝ անցնելու հաջորդ ձայնագրությանը (համակարգչով կան նաև աջ կողքի սլաքները)։',
    },
    {
        icon: '⏻',
        title: 'Ավտո · Հերթով · Կրկնել · Պատահական',
        text: '«Ավտոն» ինքն է սկսում նվագարկումը էջը բացելիս։ Ռեժիմը որոշում է, թե ինչ լինի հետո՝ հերթով հաջորդը, նույնի կրկնությունը կամ պատահական բանաստեղծություն։ Արագությունը սկսվում է 0.8×-ից։',
    },
    {
        icon: '✧',
        title: 'Կարաոկե՝ բառ առ բառ',
        text: 'Տողերը լուսավորվում են ձայնի հետ։ Սեղմիր ցանկացած բառի վրա՝ հենց այնտեղից լսելու համար։',
    },
];

const index = ref(0);

function next() {
    if (index.value < SLIDES.length - 1) {
        index.value += 1;

        return;
    }

    emit('done');
}

function back() {
    if (index.value > 0) {
        index.value -= 1;
    }
}
</script>

<template>
    <div class="absolute inset-0 z-40 flex items-center justify-center bg-black/70 px-6 backdrop-blur-sm">
        <div class="w-full max-w-sm rounded-3xl border border-white/10 bg-white/[0.06] p-6 text-center shadow-2xl">
            <p class="text-[10px] font-medium uppercase tracking-[0.22em] text-white/35">Ինչպես լսել</p>

            <div
                class="mx-auto mt-4 grid h-14 w-14 place-items-center rounded-full border text-2xl"
                :style="{ borderColor: gold + '66', color: gold }"
                aria-hidden="true"
            >
                {{ SLIDES[index].icon }}
            </div>

            <h2 class="mt-4 text-base font-semibold text-white">{{ SLIDES[index].title }}</h2>
            <p class="mt-2 text-[13px] leading-relaxed text-white/60">{{ SLIDES[index].text }}</p>

            <div class="mt-5 flex items-center justify-center gap-1.5">
                <button
                    v-for="(slide, i) in SLIDES"
                    :key="i"
                    type="button"
                    class="h-1.5 rounded-full transition-all"
                    :class="i === index ? 'w-5' : 'w-1.5 bg-white/25'"
                    :style="i === index ? { backgroundColor: gold } : null"
                    :aria-label="'Սլայդ ' + (i + 1)"
                    @click="index = i"
                ></button>
            </div>

            <div class="mt-5 flex items-center justify-between gap-3">
                <button
                    type="button"
                    class="text-[11px] text-white/40 transition hover:text-white/70"
                    @click="emit('done')"
                >
                    Բաց թողնել
                </button>

                <div class="flex items-center gap-2">
                    <button
                        v-if="index > 0"
                        type="button"
                        class="rounded-full border border-white/15 px-4 py-2 text-xs text-white/70 transition hover:text-white"
                        @click="back"
                    >
                        Հետ
                    </button>

                    <button
                        type="button"
                        class="rounded-full px-5 py-2 text-xs font-semibold text-stone-900 transition active:scale-[0.98]"
                        :style="{ backgroundColor: gold }"
                        @click="next"
                    >
                        {{ index === SLIDES.length - 1 ? 'Սկսենք' : 'Հաջորդ' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
