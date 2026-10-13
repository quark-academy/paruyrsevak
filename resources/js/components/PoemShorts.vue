<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import KaraokeLyrics from './KaraokeLyrics.vue';
import PlayerControls from './PlayerControls.vue';
import PlayerOnboarding from './PlayerOnboarding.vue';
import PlayerPrefs from './PlayerPrefs.vue';
import SevakSilhouette from './SevakSilhouette.vue';
import { useAudioPlayer } from '../composables/useAudioPlayer';
import { useKaraoke } from '../composables/useKaraoke';
import { usePlayerPrefs } from '../composables/usePlayerPrefs';
import { useSwipe } from '../composables/useSwipe';

const props = defineProps({
    payload: { type: Object, required: true },
});

const source = props.payload;

/** UI state machine: idle | loading | generating | ready | missing | error */
const state = ref('idle');
const message = ref('');
const shareNotice = ref('');

const audioUrl = ref(source.audioUrl ?? null);
const linesData = ref(source.lines ?? []);
const isOriginal = ref(Boolean(source.isOriginal));
const playsLabel = ref(source.playsLabel ?? '');

/** A listen is registered once per page load (the server de-duplicates visitors). */
let playTracked = false;

const {
    playing,
    ready,
    loading,
    time,
    duration,
    rate,
    muted,
    loop,
    blocked,
    endedAt,
    progress,
    load,
    play,
    toggle,
    seek,
    seekRatio,
    setRate,
    toggleMute,
    setLoop,
} = useAudioPlayer();

/**
 * Player preferences: autoplay plus one playback mode (sequential / repeat /
 * random). They survive navigation between poems through localStorage, so
 * "autoplay + shuffle" behaves like a stream.
 */
const {
    autoplay,
    mode,
    seenIntro,
    repeat: prefLoop,
    shuffle,
    setMode,
    toggleAutoplay,
    markIntroSeen,
} = usePlayerPrefs(source.prefs ?? {});

/** First visit: a short, premium looking walkthrough (remembered in the browser). */
const showIntro = ref(!seenIntro.value);

function finishIntro() {
    showIntro.value = false;
    markIntroSeen();

    // The tap that closes the walkthrough is a user gesture: start playing.
    if (state.value === 'ready') {
        autoplayTried = true;
        play();
    }
}

const lines = computed(() => linesData.value);
const words = computed(() => lines.value.flat());

const { activeIndex, stateFor } = useKaraoke(words, time);

const hasWords = computed(() => words.value.length > 0);
const showLyrics = computed(() => hasWords.value && state.value === 'ready');
const showRawText = computed(() => !showLyrics.value && state.value !== 'generating' && !loading.value);

/** Hint under the controls, depending on the navigation mode. */
const navHint = computed(() => (shuffle.value
    ? 'Սահեցրու՝ պատահական բանաստեղծություն'
    : 'Սահեցրու վերև/ներքև՝ հաջորդ բանաստեղծությունը'));

/** Autoplay was refused by the browser — offer a "tap to listen" button. */
const autoplayBlocked = computed(
    () => blocked.value && autoplay.value && state.value === 'ready' && !playing.value,
);

const silhouetteState = computed(() => {
    if (state.value === 'ready') {
        return playing.value ? 'ready' : 'idle';
    }

    return state.value;
});

const silhouetteProgress = computed(() => (state.value === 'ready' ? progress.value : 0));

const sourceLabel = computed(() => (isOriginal.value ? 'Արխիվային ձայն' : 'ԱԻ ձայն'));

const statusText = computed(() => {
    switch (state.value) {
        case 'generating':
            return 'ԱԻ ձայնը ստեղծվում է…';
        case 'loading':
            return 'Ձայնը բեռնվում է…';
        case 'ready':
            return sourceLabel.value;
        case 'missing':
            return 'Աուդիոն դեռ պատրաստ չէ';
        case 'error':
            return message.value || 'Սխալ';
        default:
            return 'Պատրաստվում է…';
    }
});

const background = computed(() => source.background || '#0b0a09');
const gold = computed(() => source.gold || '#e8c46a');

function applyPayload(data) {
    if (Array.isArray(data.lines)) {
        linesData.value = data.lines;
    }

    if (typeof data.audioUrl === 'string' && data.audioUrl) {
        audioUrl.value = data.audioUrl;
    }

    if (typeof data.isOriginal === 'boolean') {
        isOriginal.value = data.isOriginal;
    }

    if (typeof data.playsLabel === 'string') {
        playsLabel.value = data.playsLabel;
    }
}

/**
 * Tell the backend that the recording is playing. Failures are ignored: the
 * counter must never break playback.
 */
async function trackPlay() {
    if (playTracked || !source.playsUrl) {
        return;
    }

    playTracked = true;

    try {
        const response = await fetch(source.playsUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const data = await response.json().catch(() => ({}));

        if (typeof data.label === 'string' && data.label) {
            playsLabel.value = data.label;
        }
    } catch (error) {
        // ignore — playback must not depend on the counter
    }
}

async function generate() {
    if (!source.generateUrl) {
        state.value = 'missing';

        return;
    }

    state.value = 'generating';
    message.value = '';

    try {
        const response = await fetch(source.generateUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            state.value = data.status === 'disabled' ? 'missing' : 'error';
            message.value = data.message || 'Աուդիոն չհաջողվեց ստեղծել։';

            return;
        }

        applyPayload(data);

        if (audioUrl.value) {
            load(audioUrl.value);
        } else {
            state.value = 'missing';
        }
    } catch (error) {
        state.value = 'error';
        message.value = 'Ցանցի սխալ։ Փորձիր նորից։';
    }
}

function reload() {
    message.value = '';

    if (audioUrl.value) {
        state.value = 'loading';
        load(audioUrl.value);

        return;
    }

    generate();
}

function go(url) {
    if (url) {
        window.location.href = url;
    }
}

let navigating = false;

/**
 * Next poem: the sequential neighbour, or a fresh random one in shuffle mode.
 * Falls back to the neighbour when the random endpoint has nothing to offer.
 */
async function goNext() {
    if (navigating) {
        return;
    }

    if (!shuffle.value || !source.randomUrl) {
        go(source.next?.url);

        return;
    }

    navigating = true;

    try {
        const url = new URL(source.randomUrl, window.location.origin);

        if (source.slug) {
            url.searchParams.set('exclude', source.slug);
        }

        const response = await fetch(url.toString(), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const data = await response.json().catch(() => ({}));

        if (data.url) {
            window.location.href = data.url;

            return;
        }
    } catch (error) {
        // Network hiccup: fall back to the sequential neighbour below.
    }

    navigating = false;
    go(source.next?.url);
}

async function share() {
    const url = source.canonical || window.location.href;

    if (navigator.share) {
        try {
            await navigator.share({ title: source.title, url });

            return;
        } catch (error) {
            // user cancelled — fall through to clipboard
        }
    }

    try {
        await navigator.clipboard.writeText(url);
        shareNotice.value = 'Հղումը պատճենվեց';
    } catch (error) {
        shareNotice.value = url;
    }

    window.setTimeout(() => {
        shareNotice.value = '';
    }, 2000);
}

function onKeydown(event) {
    if (event.code === 'ArrowDown') {
        goNext();
    } else if (event.code === 'ArrowUp') {
        go(source.prev?.url);
    } else if (event.code === 'KeyL') {
        setMode(prefLoop.value ? 'sequential' : 'loop');
    } else if (event.code === 'KeyS') {
        setMode(shuffle.value ? 'sequential' : 'shuffle');
    } else if (event.code === 'Space' && state.value === 'ready') {
        event.preventDefault();
        toggle();
    }
}

useSwipe({
    onUp: () => goNext(),
    onDown: () => go(source.prev?.url),
});

// Repeat: keep the audio element looping whenever the visitor asks for it.
watch(prefLoop, (value) => setLoop(value), { immediate: true });

let autoplayTried = false;

watch(ready, (isReady) => {
    if (!isReady) {
        return;
    }

    state.value = 'ready';

    // Browsers only allow audible autoplay after a user gesture. When the
    // attempt is refused, "blocked" turns on and the player offers a tap.
    if (!autoplayTried && autoplay.value) {
        autoplayTried = true;
        play();
    }
});

// The recording ended: repeat it (loop mode) or keep the stream going.
watch(endedAt, () => {
    if (prefLoop.value || !autoplay.value) {
        return;
    }

    goNext();
});

// Count a listen as soon as the recording really starts playing.
watch(playing, (isPlaying) => {
    if (isPlaying) {
        trackPlay();
    }
});

onMounted(() => {
    window.addEventListener('keydown', onKeydown);

    if (audioUrl.value) {
        state.value = 'loading';
        load(audioUrl.value);

        return;
    }

    if (source.generateUrl) {
        generate();

        return;
    }

    state.value = 'missing';
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div
        class="relative flex h-[100dvh] w-full flex-col overflow-hidden text-stone-100"
        :style="{ backgroundColor: background }"
    >
        <!-- ambient golden glow -->
        <div
            class="pointer-events-none absolute inset-0"
            style="background: radial-gradient(120% 80% at 50% 15%, rgba(232,196,106,0.10) 0%, rgba(11,10,9,0) 60%);"
        ></div>

        <!-- playback progress (top hairline) -->
        <div class="absolute inset-x-0 top-0 z-30 h-0.5 bg-white/10">
            <div
                class="h-full transition-[width] duration-200 ease-linear"
                :style="{ width: silhouetteProgress * 100 + '%', backgroundColor: gold }"
            ></div>
        </div>

        <!-- top bar -->
        <header
            class="relative z-20 flex items-center justify-between gap-2 px-4 pt-[max(0.9rem,env(safe-area-inset-top))] pb-1"
        >
            <a
                :href="source.homeUrl || '/'"
                class="grid h-9 w-9 shrink-0 place-items-center rounded-full border border-white/10 bg-white/5 text-xs font-bold"
                aria-label="Գլխավոր"
            >ՊՍ</a>

            <div class="min-w-0 text-center">
                <h1 class="truncate text-sm font-semibold">{{ source.title }}</h1>
                <p class="text-[10px] uppercase tracking-widest text-white/35">
                    {{ source.author }}<template v-if="playsLabel"> · ▶ {{ playsLabel }}</template>
                </p>
            </div>

            <button
                type="button"
                class="grid h-9 w-9 shrink-0 place-items-center rounded-full border border-white/10 bg-white/5 text-sm"
                aria-label="Կիսվել"
                @click="share"
            >⤴</button>
        </header>

        <!-- portrait -->
        <div class="relative z-10 flex min-h-0 flex-1 items-center justify-center px-10">
            <div class="aspect-[200/260] h-[26dvh] max-h-60">
                <SevakSilhouette
                    :progress="silhouetteProgress"
                    :state="silhouetteState"
                    :gold="gold"
                    class="h-full w-full"
                />
            </div>

            <div class="absolute bottom-1 left-1/2 -translate-x-1/2">
                <span
                    class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-black/40 px-3 py-1 text-[11px] backdrop-blur"
                    :class="state === 'error' ? 'text-rose-300' : 'text-white/60'"
                >
                    <span
                        v-if="['loading', 'generating', 'idle'].includes(state)"
                        class="h-1.5 w-1.5 animate-ping rounded-full bg-amber-300"
                    ></span>
                    <span v-else class="h-1.5 w-1.5 rounded-full" :style="{ backgroundColor: gold }"></span>
                    {{ statusText }}
                </span>
            </div>
        </div>

        <!-- lyrics -->
        <section class="relative z-10">
            <KaraokeLyrics
                v-if="showLyrics"
                :lines="lines"
                :active-index="activeIndex"
                :state-for="stateFor"
            />

            <div v-else-if="showRawText" class="karaoke-mask max-h-[38dvh] overflow-y-auto px-6 py-4">
                <p class="whitespace-pre-line text-center text-base leading-relaxed text-white/45">
                    {{ source.content }}
                </p>

                <div v-if="state === 'missing' || state === 'error'" class="mt-4 text-center">
                    <p class="text-xs text-white/40">{{ statusText }}</p>
                    <button
                        v-if="source.generateUrl"
                        type="button"
                        class="mt-2 rounded-full border border-amber-300/40 px-4 py-1.5 text-xs font-medium text-amber-200"
                        @click="reload"
                    >
                        Ստեղծել ԱԻ ձայնը
                    </button>
                </div>
            </div>
        </section>

        <!-- controls -->
        <section class="relative z-10 px-5 pb-[max(1.1rem,env(safe-area-inset-bottom))] pt-1">
            <PlayerControls
                :playing="playing"
                :current-time="time"
                :duration="duration"
                :rate="rate"
                :muted="muted"
                :disabled="state !== 'ready'"
                :attention="autoplayBlocked"
                @toggle="toggle"
                @seek="seekRatio"
                @rate="setRate"
                @mute="toggleMute"
            />

            <!-- autoplay + playback mode: remembered between poems -->
            <div class="mt-2.5">
                <PlayerPrefs
                    :autoplay="autoplay"
                    :mode="mode"
                    @autoplay="toggleAutoplay"
                    @mode="setMode"
                />
            </div>

            <!-- Desktop only: the mobile player stays clean (see the walkthrough). -->
            <div class="mt-2 hidden items-center justify-between gap-2 text-[10px] text-white/30 sm:flex">
                <a :href="source.readingUrl" class="shrink-0 underline-offset-2 hover:text-white/60 hover:underline">
                    Ամբողջ տեքստը
                </a>
                <span class="truncate">{{ navHint }}</span>
            </div>
        </section>

        <!-- vertical navigation rail -->
        <nav class="absolute right-2 top-1/2 z-20 flex -translate-y-1/2 flex-col gap-2">
            <button
                type="button"
                class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-black/30 text-sm backdrop-blur transition disabled:opacity-25"
                :disabled="!source.prev"
                aria-label="Նախորդը"
                @click="go(source.prev?.url)"
            >
                ⌃
            </button>
            <button
                type="button"
                class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-black/30 text-sm backdrop-blur transition disabled:opacity-25"
                :disabled="!source.next && !shuffle"
                :aria-label="shuffle ? 'Պատահական բանաստեղծություն' : 'Հաջորդը'"
                @click="goNext"
            >
                ⌄
            </button>
        </nav>

        <!-- share toast -->
        <transition
            enter-active-class="transition duration-200"
            enter-from-class="translate-y-2 opacity-0"
            leave-active-class="transition duration-200"
            leave-to-class="translate-y-2 opacity-0"
        >
            <div
                v-if="shareNotice"
                class="absolute bottom-24 left-1/2 z-40 -translate-x-1/2 rounded-full bg-white/90 px-4 py-1.5 text-xs font-medium text-stone-900"
            >
                {{ shareNotice }}
            </div>
        </transition>

        <!-- First visit: a short walkthrough (shown once per browser). -->
        <PlayerOnboarding v-if="showIntro" :gold="gold" @done="finishIntro" />
    </div>
</template>



