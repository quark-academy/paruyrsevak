<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    autoplay: { type: Boolean, default: false },
    mode: { type: String, default: 'sequential' },
});

const emit = defineEmits(['autoplay', 'mode']);

/**
 * One icon per choice, exactly like a media player: the button shows the mode
 * that is active and a single press walks to the next one, so the icon swaps in
 * place and the row stays compact. Every target is thumb-sized on purpose.
 */
const MODES = [
    { id: 'sequential', label: 'Հերթով', title: 'Հերթով՝ հաջորդ բանաստեղծությունը' },
    { id: 'loop', label: 'Կրկնել', title: 'Կրկնել նույն ձայնագրությունը' },
    { id: 'shuffle', label: 'Պատահական', title: 'Հաջորդը՝ պատահական բանաստեղծություն' },
];

const ORDER = MODES.map((item) => item.id);

const current = computed(() => MODES.find((item) => item.id === props.mode) ?? MODES[0]);

const BUTTON = 'grid h-11 w-11 place-items-center rounded-full border transition active:scale-95';
const BUTTON_ON = 'border-amber-300/50 bg-amber-300/15 text-amber-200';
const BUTTON_OFF = 'border-white/10 bg-white/[0.04] text-white/50 hover:border-white/20 hover:text-white/80';

/** A tap that only swaps an icon still has to say what it did. */
const notice = ref('');
let noticeTimer = null;

function announce(text) {
    notice.value = text;
    window.clearTimeout(noticeTimer);
    noticeTimer = window.setTimeout(() => {
        notice.value = '';
    }, 1600);
}

function cycleMode() {
    const next = MODES[(ORDER.indexOf(current.value.id) + 1) % MODES.length];

    emit('mode', next.id);
    announce(next.label);
}

function toggleAutoplay() {
    emit('autoplay');
    announce(props.autoplay ? 'Ավտոն անջատված է' : 'Ավտոն միացված է');
}

onBeforeUnmount(() => window.clearTimeout(noticeTimer));
</script>

<template>
    <div class="relative flex items-center justify-center gap-2">
        <!-- playback order: one icon, one press, swapped in place -->
        <button
            type="button"
            :class="[BUTTON, mode === 'sequential' ? BUTTON_OFF : BUTTON_ON]"
            :aria-label="current.title"
            :title="current.title"
            @click="cycleMode"
        >
            <!-- sequential: the poems simply follow each other -->
            <svg v-if="mode === 'sequential'" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                 aria-hidden="true">
                <path d="M4 12h13" />
                <path d="M13 7l5 5-5 5" />
            </svg>

            <!-- repeat -->
            <svg v-else-if="mode === 'loop'" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                 aria-hidden="true">
                <path d="M17 2l4 4-4 4" />
                <path d="M3 11v-1a4 4 0 0 1 4-4h14" />
                <path d="M7 22l-4-4 4-4" />
                <path d="M21 13v1a4 4 0 0 1-4 4H3" />
            </svg>

            <!-- shuffle -->
            <svg v-else class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                 aria-hidden="true">
                <path d="M16 3h5v5" />
                <path d="M4 20L21 3" />
                <path d="M21 16v5h-5" />
                <path d="M15 15l6 6" />
                <path d="M4 4l5 5" />
            </svg>
        </button>

        <!-- autoplay: its own switch -->
        <button
            type="button"
            :class="[BUTTON, autoplay ? BUTTON_ON : BUTTON_OFF]"
            :aria-pressed="autoplay"
            :aria-label="autoplay ? 'Անջատել ավտոմատ սկսելը' : 'Միացնել ավտոմատ սկսելը'"
            :title="autoplay ? 'Ավտոն միացած է' : 'Ավտոն անջատած է'"
            @click="toggleAutoplay"
        >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M18.4 6.6a9 9 0 1 1-12.8 0" />
                <path d="M12 2v9" />
            </svg>
        </button>

        <!-- a short caption instead of a permanent label -->
        <transition
            enter-active-class="transition duration-150"
            enter-from-class="translate-y-1 opacity-0"
            leave-active-class="transition duration-150"
            leave-to-class="translate-y-1 opacity-0"
        >
            <span
                v-if="notice"
                class="pointer-events-none absolute -top-9 left-1/2 z-30 -translate-x-1/2 whitespace-nowrap rounded-full bg-white/90 px-3 py-1 text-[11px] font-medium text-stone-900"
            >
                {{ notice }}
            </span>
        </transition>
    </div>
</template>
