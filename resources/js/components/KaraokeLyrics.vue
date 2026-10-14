<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';

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

/** How long a manual scroll holds the auto-follow back (ms). */
const HOLD_MS = 4000;

let holdTimer = null;

/**
 * True while the visitor reads the poem by hand: the karaoke keeps singing but
 * stops pulling the text back to the active word, so the page can be scrolled
 * up and down (and back). A new poem, or the timeout, hands the follow back.
 */
const held = ref(false);

function holdFollow() {
    held.value = true;
    window.clearTimeout(holdTimer);
    holdTimer = window.setTimeout(() => {
        held.value = false;
    }, HOLD_MS);
}

/** Centre a word in the list without moving anything around it. */
function centre(index, behavior = 'smooth') {
    const element = scroller.value;

    if (!element || index < 0) {
        return;
    }

    const node = element.querySelector(`[data-index="${index}"]`);

    if (!node) {
        return;
    }

    const target = node.offsetTop - element.clientHeight / 2 + node.clientHeight / 2;

    element.scrollTo({ top: Math.max(0, target), behavior });
}

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

// Follow the singing, unless the visitor took the text over for a moment.
watch(
    () => props.activeIndex,
    (index) => {
        if (!props.autoscroll || held.value) {
            return;
        }

        centre(index);
    },
);

// Another poem: its text starts at the beginning and the player follows again.
watch(
    () => props.lines,
    () => {
        held.value = false;
        window.clearTimeout(holdTimer);

        scroller.value?.scrollTo({ top: 0, behavior: 'instant' });
    },
);

onBeforeUnmount(() => window.clearTimeout(holdTimer));
</script>

<template>
    <div
        v-if="indexedLines.length"
        ref="scroller"
        class="karaoke-mask karaoke-scroller relative max-h-[42dvh] overflow-y-auto overscroll-contain px-4 py-6 text-center"
        @touchstart.passive="holdFollow"
        @touchmove.passive="holdFollow"
        @wheel.passive="holdFollow"
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
