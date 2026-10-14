<script setup>
import { computed } from 'vue';

const props = defineProps({
    /** 0..1 — how much of the portrait is already "sung" (gold). */
    progress: { type: Number, default: 0 },
    /** idle | loading | generating | ready | error */
    state: { type: String, default: 'idle' },
    gold: { type: String, default: '#e8c46a' },
    /** The author's mark: when the server sends it, it takes the icon's place. */
    logo: { type: String, default: '' },
});

const viewBox = '0 0 200 260';

/** Stylised, symmetric silhouette of the poet (head + bust). */
const PATH = [
    'M100 18',
    'C132 18 154 42 154 74',
    'C154 92 149 106 142 116',
    'C152 120 160 130 160 142',
    'C160 150 156 157 150 161',
    'L150 172',
    'C178 180 196 200 198 228',
    'L198 250',
    'L2 250',
    'L2 228',
    'C4 200 22 180 50 172',
    'L50 161',
    'C44 157 40 150 40 142',
    'C40 130 48 120 58 116',
    'C51 106 46 92 46 74',
    'C46 42 68 18 100 18',
    'Z',
].join(' ');

const uid = Math.random().toString(36).slice(2, 9);
const gradientId = `sevak-gold-${uid}`;
const haloId = `sevak-halo-${uid}`;
const shapeClipId = `sevak-shape-${uid}`;

const pulsing = computed(() => ['idle', 'loading', 'generating'].includes(props.state));
/** The player's "ready" means this recording is running right now. */
const playing = computed(() => props.state === 'ready');
const clamped = computed(() => Math.max(0, Math.min(1, props.progress || 0)));
const fillY = computed(() => Number((260 * (1 - clamped.value)).toFixed(2)));
const haloOpacity = computed(() => (pulsing.value ? 1 : 0.7));

/** The sung part of the portrait is revealed bottom-up, like the gold fill. */
const reveal = computed(() => `inset(${(100 - clamped.value * 100).toFixed(2)}% 0 0 0)`);
const portraitOpacity = computed(() => {
    if (props.state === 'error') {
        return 'opacity-25';
    }

    return playing.value ? 'opacity-60' : 'opacity-40';
});
</script>

<template>
    <div class="relative flex h-full w-full items-center justify-center">
        <!-- The author's own mark: his portrait fills with gold as the poem plays. -->
        <template v-if="logo">
            <span
                class="pointer-events-none absolute inset-[-14%] rounded-full transition-opacity duration-700"
                :style="{
                    background: `radial-gradient(circle at 50% 45%, ${gold}3d 0%, ${gold}00 72%)`,
                    opacity: playing ? 1 : 0.55,
                }"
            ></span>

            <span
                class="relative block h-full w-full overflow-hidden rounded-full"
                :class="{ 'sevak-breathe': pulsing }"
                :style="{ boxShadow: `inset 0 0 0 1px ${gold}59` }"
            >
                <img
                    :src="logo"
                    alt=""
                    aria-hidden="true"
                    class="absolute inset-0 h-full w-full object-cover saturate-0 transition-opacity duration-700"
                    :class="portraitOpacity"
                >
                <img
                    :src="logo"
                    alt=""
                    aria-hidden="true"
                    class="absolute inset-0 h-full w-full object-cover"
                    :style="{ clipPath: reveal, filter: 'sepia(1) saturate(2.6) hue-rotate(-6deg) brightness(1.08)' }"
                >
            </span>
        </template>

        <!-- Without the file (a fresh checkout, a broken download) the drawing stays. -->
        <div v-else class="h-full w-full" :class="{ 'sevak-breathe': pulsing }">
            <svg
                :viewBox="viewBox"
                class="h-full w-full overflow-visible"
                role="img"
                aria-label="Պարույր Սևակի ուրվանկարը"
            >
                <defs>
                    <linearGradient :id="gradientId" x1="0" y1="1" x2="0" y2="0">
                        <stop offset="0%" :stop-color="gold" stop-opacity="1" />
                        <stop offset="55%" :stop-color="gold" stop-opacity="0.85" />
                        <stop offset="100%" :stop-color="gold" stop-opacity="0.4" />
                    </linearGradient>

                    <radialGradient :id="haloId" cx="50%" cy="45%" r="55%">
                        <stop offset="0%" :stop-color="gold" stop-opacity="0.25" />
                        <stop offset="70%" :stop-color="gold" stop-opacity="0.06" />
                        <stop offset="100%" :stop-color="gold" stop-opacity="0" />
                    </radialGradient>

                    <clipPath :id="shapeClipId">
                        <path :d="PATH" />
                    </clipPath>
                </defs>

                <!-- golden halo -->
                <circle
                    cx="100"
                    cy="125"
                    r="104"
                    :fill="`url(#${haloId})`"
                    :opacity="haloOpacity"
                    class="transition-opacity duration-700"
                />

                <!-- dark base silhouette -->
                <path :d="PATH" class="fill-stone-800/90" />

                <!-- rising gold fill, masked by the silhouette -->
                <g :clip-path="`url(#${shapeClipId})`">
                    <rect x="-10" :y="fillY" width="220" height="300" :fill="`url(#${gradientId})`" />
                    <rect
                        x="-10"
                        :y="fillY - 1"
                        width="220"
                        height="2"
                        :fill="gold"
                        opacity="0.95"
                    />
                </g>

                <!-- outline -->
                <path
                    :d="PATH"
                    fill="none"
                    :stroke="gold"
                    stroke-width="1.25"
                    stroke-opacity="0.35"
                />
            </svg>
        </div>
    </div>
</template>
