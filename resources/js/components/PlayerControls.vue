<script setup>
import { computed } from 'vue';

const props = defineProps({
    playing: { type: Boolean, default: false },
    currentTime: { type: Number, default: 0 },
    duration: { type: Number, default: 0 },
    rate: { type: Number, default: 1 },
    muted: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['toggle', 'seek', 'rate', 'mute']);

const RATES = [0.75, 1, 1.25, 1.5];

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

        <!-- controls -->
        <div class="mt-3 flex items-center gap-2">
            <button
                type="button"
                class="grid h-9 min-w-12 shrink-0 place-items-center rounded-full px-2 text-xs font-semibold text-white/60 transition hover:bg-white/10 hover:text-white"
                :title="'Արագություն՝ ' + rate + '×'"
                aria-label="Արագություն"
                @click="cycleRate"
            >
                {{ rate }}×
            </button>

            <div class="flex flex-1 items-center justify-center gap-7">
                <button
                    type="button"
                    class="text-2xl text-white/70 transition hover:text-white disabled:opacity-30"
                    :disabled="disabled || duration <= 0"
                    aria-label="10 վրկ հետ"
                    @click="emit('seek', Math.max(0, ratio - 10 / (duration || 1)))"
                >
                    ⟲
                </button>

                <button
                    type="button"
                    :disabled="disabled"
                    class="grid h-14 w-14 place-items-center rounded-full bg-amber-300 text-xl font-bold text-stone-900 shadow-lg shadow-amber-300/20 transition active:scale-95 disabled:opacity-40"
                    :aria-label="playing ? 'Կանգնեցնել' : 'Նվագարկել'"
                    @click="emit('toggle')"
                >
                    <span v-if="playing">❚❚</span>
                    <span v-else>▶</span>
                </button>

                <button
                    type="button"
                    class="text-2xl text-white/70 transition hover:text-white disabled:opacity-30"
                    :disabled="disabled || duration <= 0"
                    aria-label="10 վրկ առաջ"
                    @click="emit('seek', Math.min(1, ratio + 10 / (duration || 1)))"
                >
                    ⟳
                </button>
            </div>

            <!-- Sound toggle: mirrors the speed button so the transport stays centred. -->
            <button
                type="button"
                class="grid h-9 min-w-12 shrink-0 place-items-center rounded-full text-white/60 transition hover:bg-white/10 hover:text-white"
                :aria-pressed="muted"
                :aria-label="muted ? 'Միացնել ձայնը' : 'Անջատել ձայնը'"
                :title="muted ? 'Միացնել ձայնը' : 'Անջատել ձայնը'"
                @click="emit('mute')"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
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
