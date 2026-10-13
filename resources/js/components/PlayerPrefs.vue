<script setup>
defineProps({
    autoplay: { type: Boolean, default: false },
    mode: { type: String, default: 'sequential' },
});

const emit = defineEmits(['autoplay', 'mode']);

const MODES = [
    { id: 'sequential', label: 'Հերթով', title: 'Հաջորդ բանաստեղծությունը՝ հերթով' },
    { id: 'loop', label: 'Կրկնել', title: 'Կրկնել նույն ձայնագրությունը' },
    { id: 'shuffle', label: 'Պատահական', title: 'Հաջորդը՝ պատահական բանաստեղծություն' },
];

const PILL_BASE = 'inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-[11px] font-medium transition';
const PILL_OFF = 'border-white/10 bg-white/[0.04] text-white/45 hover:border-white/20 hover:text-white/80';
const PILL_ON = 'border-amber-300/50 bg-amber-300/15 text-amber-200';

const SEGMENT_BASE = 'rounded-full px-3 py-1.5 text-[11px] font-medium transition';
const SEGMENT_OFF = 'text-white/45 hover:text-white/80';
const SEGMENT_ON = 'bg-amber-300 text-stone-900 shadow-sm';
</script>

<template>
    <div class="flex flex-col items-center gap-2">
        <!-- autoplay: its own switch -->
        <button
            type="button"
            :class="[PILL_BASE, autoplay ? PILL_ON : PILL_OFF]"
            :aria-pressed="autoplay"
            title="Ավտոմատ սկսել նվագարկումը էջը բացելիս"
            @click="emit('autoplay')"
        >
            <span aria-hidden="true">⏻</span> Ավտո
        </button>

        <!-- playback order: exactly one mode at a time -->
        <div class="inline-flex items-center gap-0.5 rounded-full border border-white/10 bg-white/[0.04] p-0.5">
            <button
                v-for="item in MODES"
                :key="item.id"
                type="button"
                :class="[SEGMENT_BASE, mode === item.id ? SEGMENT_ON : SEGMENT_OFF]"
                :aria-pressed="mode === item.id"
                :title="item.title"
                @click="emit('mode', item.id)"
            >
                {{ item.label }}
            </button>
        </div>
    </div>
</template>
