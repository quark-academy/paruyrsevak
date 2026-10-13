<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    /** Array of lines, each line an array of { w, s, e } words. */
    lines: { type: Array, default: () => [] },
    /** Global index of the word being pronounced (or -1). */
    activeIndex: { type: Number, default: -1 },
    /** Maps a global index to: active | sung | pending. */
    stateFor: { type: Function, required: true },
    autoscroll: { type: Boolean, default: true },
});

const emit = defineEmits(['seek']);

const scroller = ref(null);

/** Flatten the lines once, assigning a stable global index to every word. */
const indexedLines = computed(() => {
    let index = 0;

    return props.lines.map((line) =>
        line.map((word) => ({ ...word, i: index++ })),
    );
});

function wordClass(index) {
    switch (props.stateFor(index)) {
        case 'active':
            return 'karaoke-word karaoke-word--active';
        case 'sung':
            return 'karaoke-word karaoke-word--sung';
        default:
            return 'karaoke-word karaoke-word--pending';
    }
}

watch(
    () => props.activeIndex,
    (index) => {
        if (!props.autoscroll || index < 0 || !scroller.value) {
            return;
        }

        const node = scroller.value.querySelector(`[data-index="${index}"]`);

        if (!node) {
            return;
        }

        const target = node.offsetTop - scroller.value.clientHeight / 2 + node.clientHeight / 2;

        scroller.value.scrollTo({ top: Math.max(0, target), behavior: 'smooth' });
    },
);
</script>

<template>
    <div
        v-if="indexedLines.length"
        ref="scroller"
        class="karaoke-mask karaoke-scroller relative max-h-[42dvh] overflow-y-auto px-4 py-6 text-center"
    >
        <p
            v-for="(line, lineIndex) in indexedLines"
            :key="lineIndex"
            class="mb-3 flex flex-wrap items-center justify-center gap-x-2 gap-y-1"
        >
            <button
                v-for="word in line"
                :key="word.i"
                type="button"
                :data-index="word.i"
                class="rounded-lg px-1.5 py-0.5 text-2xl leading-snug transition-all duration-200 sm:text-3xl"
                :class="wordClass(word.i)"
                @click="emit('seek', word.s)"
            >
                {{ word.w }}
            </button>
        </p>
    </div>
</template>
