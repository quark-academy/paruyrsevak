<script setup>
defineProps({
    autoplay: { type: Boolean, default: false },
    loop: { type: Boolean, default: false },
    shuffle: { type: Boolean, default: false },
});

const emit = defineEmits(['toggle']);

const PILL_BASE = 'inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-[11px] font-medium transition';
const PILL_OFF = 'border-white/10 bg-white/[0.04] text-white/45 hover:border-white/20 hover:text-white/80';
const PILL_ON = 'border-amber-300/50 bg-amber-300/15 text-amber-200';

function pill(active) {
    return `${PILL_BASE} ${active ? PILL_ON : PILL_OFF}`;
}
</script>

<template>
    <div class="flex flex-wrap items-center justify-center gap-2">
        <button
            type="button"
            :class="pill(autoplay)"
            :aria-pressed="autoplay"
            title="Ավտոմատ սկսել նվագարկումը էջը բացելիս"
            @click="emit('toggle', 'autoplay')"
        >
            <span aria-hidden="true">⏻</span> Ավտո
        </button>

        <button
            type="button"
            :class="pill(loop)"
            :aria-pressed="loop"
            title="Կրկնել նույն ձայնագրությունը"
            @click="emit('toggle', 'loop')"
        >
            <span aria-hidden="true">↻</span> Կրկնել
        </button>

        <button
            type="button"
            :class="pill(shuffle)"
            :aria-pressed="shuffle"
            title="Հաջորդ բանաստեղծությունը՝ պատահական կարգով"
            @click="emit('toggle', 'shuffle')"
        >
            <span aria-hidden="true">⇄</span> Խառնել
        </button>
    </div>
</template>
