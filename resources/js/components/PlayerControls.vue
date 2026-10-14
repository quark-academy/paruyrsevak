<script setup>
import { computed } from 'vue';

const props = defineProps({
    playing: { type: Boolean, default: false },
    currentTime: { type: Number, default: 0 },
    duration: { type: Number, default: 0 },
    rate: { type: Number, default: 0.8 },
    muted: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    /** Autoplay was refused by the browser: ask for a tap by pulsing the play button. */
    attention: { type: Boolean, default: false },
    /** Poem neighbours: the transport carries prev/next on every screen size. */
    hasPrev: { type: Boolean, default: false },
    hasNext: { type: Boolean, default: false },
});

const emit = defineEmits(['toggle', 'seek', 'rate', 'mute', 'prev', 'next']);

/** 0.8× is the opening speed; a tap walks the list and wraps around. */
const RATES = [0.8, 1, 1.2, 1.5, 0.75];

const ratio = computed(() => (props.duration > 0 ? Math.min(1, props.currentTime / props.duration) : 0));

function format(seconds) {
    if (!isFinite(seconds) || seconds < 0) {
        return '0:00';
    }

    const minutes = Math.floor(seconds / 60);
    const rest = Math.floor(seconds % 60);

    return `${minutes}:${String(rest).padStart(2, '0')}`;
}

function onSeek(event) {
    emit('seek', Number(event.target.value) / 1000);
}

function cycleRate() {
    const index = RATES.indexOf(props.rate);
    emit('rate', RATES[(index + 1) % RATES.length]);
}
</script>

<template>
    <div class="w-full">
        <!-- progress -->
        <input
            type="range"
            min="0"
            max="1000"
            step="1"
            :value="Math.round(ratio * 1000)"
            :disabled="disabled || duration <= 0"
            class="h-1 w-full cursor-pointer appearance-none rounded-full bg-white/15 accent-amber-300 disabled:opacity-40"
            aria-label="Ժամանակացույց"
            @input="onSeek"
        />

        <div class="mt-1 flex items-center justify-between text-[11px] tabular-nums text-white/45">
            <span>{{ format(currentTime) }}</span>
            <span>{{ format(duration) }}</span>
        </div>

        <!-- controls: one row, 44px targets on purpose (a thumb, not a cursor) -->
        <div class="mt-3 flex items-center gap-1 sm:gap-2">
            <button
                type="button"
                class="grid h-11 w-11 shrink-0 place-items-center rounded-full text-[13px] font-semibold tabular-nums text-white/65 transition hover:bg-white/10 hover:text-white"
                :title="'Արագություն՝ ' + rate + '×'"
                aria-label="Արագություն"
                @click="cycleRate"
            >
                {{ rate }}×
            </button>

            <div class="flex flex-1 items-center justify-center gap-0.5 sm:gap-1.5">
                <!-- previous poem -->
                <button
                    type="button"
                    class="grid h-11 w-11 place-items-center rounded-full text-white/75 transition hover:bg-white/10 hover:text-white disabled:opacity-25"
                    :disabled="!hasPrev"
                    title="Նախորդը"
                    aria-label="Նախորդ բանաստեղծությունը"
                    @click="emit('prev')"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M19 20 9 12l10-8z" />
                        <path d="M5 4h2v16H5z" />
                    </svg>
                </button>

                <!-- 10 seconds back (the narrowest phones drop this pair) -->
                <button
                    type="button"
                    class="hidden h-11 w-11 place-items-center rounded-full text-xl text-white/65 transition hover:bg-white/10 hover:text-white disabled:opacity-30 min-[360px]:grid"
                    :disabled="disabled || duration <= 0"
                    title="10 վրկ հետ"
                    aria-label="10 վրկ հետ"
                    @click="emit('seek', Math.max(0, ratio - 10 / (duration || 1)))"
                >
                    ⟲
                </button>

                <button
                    type="button"
                    :disabled="disabled"
                    class="grid h-16 w-16 place-items-center rounded-full bg-amber-300 text-2xl font-bold text-stone-900 shadow-lg shadow-amber-300/20 transition active:scale-95 disabled:opacity-40"
                    :class="{ 'ring-4 ring-amber-300/40 animate-pulse': attention && !playing }"
                    :aria-label="playing ? 'Կանգնեցնել' : 'Նվագարկել'"
                    @click="emit('toggle')"
                >
                    <span v-if="playing">❚❚</span>
                    <span v-else>▶</span>
                </button>

                <!-- 10 seconds forward -->
                <button
                    type="button"
                    class="hidden h-11 w-11 place-items-center rounded-full text-xl text-white/65 transition hover:bg-white/10 hover:text-white disabled:opacity-30 min-[360px]:grid"
                    :disabled="disabled || duration <= 0"
                    title="10 վրկ առաջ"
                    aria-label="10 վրկ առաջ"
                    @click="emit('seek', Math.min(1, ratio + 10 / (duration || 1)))"
                >
                    ⟳
                </button>

                <!-- next poem -->
                <button
                    type="button"
                    class="grid h-11 w-11 place-items-center rounded-full text-white/75 transition hover:bg-white/10 hover:text-white disabled:opacity-25"
                    :disabled="!hasNext"
                    title="Հաջորդը"
                    aria-label="Հաջորդ բանաստեղծությունը"
                    @click="emit('next')"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M5 4l10 8-10 8z" />
                        <path d="M17 4h2v16h-2z" />
                    </svg>
                </button>
            </div>

            <!-- Sound toggle: mirrors the speed button so the transport stays centred. -->
            <button
                type="button"
                class="grid h-11 w-11 shrink-0 place-items-center rounded-full text-white/65 transition hover:bg-white/10 hover:text-white"
                :aria-pressed="muted"
                :aria-label="muted ? 'Միացնել ձայնը' : 'Անջատել ձայնը'"
                :title="muted ? 'Միացնել ձայնը' : 'Անջատել ձայնը'"
                @click="emit('mute')"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 9.5h3L11 6v12l-4-3.5H4z" />
                    <template v-if="muted">
                        <path d="M15.5 9.5l4.5 5M20 9.5l-4.5 5" />
                    </template>
                    <template v-else>
                        <path d="M15.5 9a4 4 0 0 1 0 6" />
                        <path d="M18 6.5a7.5 7.5 0 0 1 0 11" />
                    </template>
                </svg>
            </button>
        </div>
    </div>
</template>
