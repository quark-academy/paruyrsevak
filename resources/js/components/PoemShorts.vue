<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import KaraokeLyrics from './KaraokeLyrics.vue';
import PlayerControls from './PlayerControls.vue';
import PlayerOnboarding from './PlayerOnboarding.vue';
import PlayerPrefs from './PlayerPrefs.vue';
import SearchPanel from './SearchPanel.vue';
import SevakSilhouette from './SevakSilhouette.vue';
import { useAudioPlayer } from '../composables/useAudioPlayer';
import { useKaraoke } from '../composables/useKaraoke';
import { usePlayerPrefs } from '../composables/usePlayerPrefs';
import { useSwipe } from '../composables/useSwipe';

const props = defineProps({
    payload: { type: Object, required: true },
});

const source = props.payload;

/**
 * The poem on screen. It starts as the server rendered payload and is replaced
 * in place when the visitor swipes to another poem (see navigate): keeping one
 * document alive keeps the browser's user gesture, and that is what allows
 * audible autoplay between poems without asking for a tap every time.
 */
const poem = ref({ ...source });

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
    error,
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
    attemptAutoplay,
    playedOnce,
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

/** The header search panel: a match is played right here, not on a new page. */
const searchOpen = ref(false);

/** First visit: a short, premium looking walkthrough (remembered in the browser). */
const showIntro = ref(!seenIntro.value);

function finishIntro() {
    showIntro.value = false;
    markIntroSeen();

    // The press on «Սկսենք» is a real user gesture, and that is all a browser
    // asks for: the recording may start with sound right away.
    if (state.value === 'ready') {
        attemptAutoplay();
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

/** Autoplay was refused by the browser — the play button pulses until pressed. */
const autoplayBlocked = computed(
    () => blocked.value && autoplay.value && state.value === 'ready' && !playing.value,
);

/**
 * Nothing has started in this document yet and the browser waits for a
 * deliberate gesture: show the "press ▶" hint. That single press is enough —
 * every following poem starts by itself again, because the document keeps the
 * activation it was given.
 */
const needsStart = computed(() => state.value === 'ready' && !playedOnce.value && !playing.value);

/**
 * A recording that fails to load must not leave "loading" on screen forever:
 * the transport would stay disabled and the visitor could not even press ▶.
 */
watch(error, (value) => {
    if (value && state.value === 'loading') {
        state.value = 'error';
        message.value = value;
    }
});

/** Watchdog for a stalled request that never reports an error. */
let loadWatchdog = null;

watch(state, (value) => {
    window.clearTimeout(loadWatchdog);

    if (value !== 'loading') {
        return;
    }

    loadWatchdog = window.setTimeout(() => {
        if (state.value === 'loading' && !playing.value && duration.value === 0) {
            state.value = 'error';
            message.value = 'Ձայնը չբեռնվեց։ Փորձիր նորից։';
        }
    }, 12000);
});

const silhouetteState = computed(() => {
    if (state.value === 'ready') {
        return playing.value ? 'ready' : 'idle';
    }

    return state.value;
});

const silhouetteProgress = computed(() => (state.value === 'ready' ? progress.value : 0));

const sourceLabel = computed(() => (isOriginal.value ? 'Օրիգինալ' : 'AI'));

const statusText = computed(() => {
    switch (state.value) {
        case 'generating':
            return 'AI ձայնը ստեղծվում է…';
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

const background = computed(() => poem.value.background || '#0b0a09');
const gold = computed(() => poem.value.gold || '#e8c46a');

function applyPayload(data) {
    poem.value = { ...poem.value, ...data };

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

    // Another recording means another listen (the server de-duplicates
    // visitors) and a fresh autoplay intent.
    playTracked = false;
    userPaused.value = false;

    if (typeof data.title === 'string' && data.title) {
        document.title = data.title;
    }

    // Keep the address-bar/canonical pair in sync while swapping in place.
    if (typeof data.canonical === 'string' && data.canonical) {
        document.querySelector('link[rel="canonical"]')?.setAttribute('href', data.canonical);
    }
}

/**
 * Tell the backend that the recording is playing. Failures are ignored: the
 * counter must never break playback.
 */
async function trackPlay() {
    if (playTracked || !poem.value.playsUrl) {
        return;
    }

    playTracked = true;

    try {
        const response = await fetch(poem.value.playsUrl, {
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
    if (!poem.value.generateUrl) {
        state.value = 'missing';

        return;
    }

    state.value = 'generating';
    message.value = '';

    try {
        const response = await fetch(poem.value.generateUrl, {
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

let navigating = false;

/**
 * Swap to another poem inside the same document.
 *
 * A full page load throws the visitor's gesture away, and the browser then
 * refuses audible autoplay — which is exactly why the player used to ask for a
 * tap on every poem. An in-place swap keeps the document (and its activation),
 * so the next recording simply starts with sound.
 */
async function navigate(target, { push = true } = {}) {
    if (!target || navigating) {
        return;
    }

    if (!target.playerUrl) {
        // No payload URL (older link, or an unexpected shape): let the browser
        // navigate normally.
        go(target.url);

        return;
    }

    navigating = true;
    state.value = 'loading';

    try {
        const response = await fetch(target.playerUrl, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('payload unavailable');
        }

        const data = await response.json();

        applyPayload(data);

        if (push && data.canonical) {
            window.history.pushState(
                { slug: data.slug, playerUrl: target.playerUrl, url: target.url },
                '',
                data.canonical,
            );
        }

        if (audioUrl.value) {
            state.value = 'loading';
            load(audioUrl.value);
        } else if (poem.value.generateUrl) {
            generate();
        } else {
            state.value = 'missing';
        }
    } catch (error) {
        // Anything unexpected: hand the navigation back to the browser.
        go(target.url);
    } finally {
        navigating = false;
    }
}

/**
 * A string is a plain URL, an object carries its own payload URL.
 */
function go(target) {
    if (!target) {
        return;
    }

    if (typeof target === 'string') {
        window.location.href = target;

        return;
    }

    navigate(target);
}

/**
 * Back/forward buttons (and the iOS back swipe): render the entry the address
 * bar points at, without pushing another one.
 */
function onPopState(event) {
    const stored = event.state;

    if (!stored?.playerUrl) {
        // An entry from outside the player (e.g. the catalogue): reload it.
        window.location.reload();

        return;
    }

    navigate({ playerUrl: stored.playerUrl, url: stored.url }, { push: false });
}

/**
 * Next poem: the sequential neighbour, or a fresh random one in shuffle mode.
 * Falls back to the neighbour when the random endpoint has nothing to offer.
 */
async function goNext() {
    if (navigating) {
        return;
    }

    if (!shuffle.value || !poem.value.randomUrl) {
        go(poem.value.next);

        return;
    }

    navigating = true;

    try {
        const url = new URL(poem.value.randomUrl, window.location.origin);

        if (poem.value.slug) {
            url.searchParams.set('exclude', poem.value.slug);
        }

        const response = await fetch(url.toString(), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const data = await response.json().catch(() => ({}));

        navigating = false;

        if (data.playerUrl) {
            navigate(data);

            return;
        }

        if (data.url) {
            window.location.href = data.url;

            return;
        }
    } catch (error) {
        // Network hiccup: fall back to the sequential neighbour below.
    }

    navigating = false;
    go(poem.value.next);
}

async function share() {
    const url = poem.value.canonical || window.location.href;

    if (navigator.share) {
        try {
            await navigator.share({ title: poem.value.title, url });

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

/**
 * A search result replaces the poem inside this document, exactly like a swipe:
 * the player keeps the browser's activation, so the recording keeps playing.
 */
function onSearchSelect(result) {
    searchOpen.value = false;
    navigate(result);
}

function onKeydown(event) {
    if (event.code === 'ArrowDown') {
        goNext();
    } else if (event.code === 'ArrowUp') {
        go(poem.value.prev);
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
    onDown: () => go(poem.value.prev),
});

// Repeat: keep the audio element looping whenever the visitor asks for it.
watch(prefLoop, (value) => setLoop(value), { immediate: true });

/**
 * The visitor pressed pause themselves: autoplay must not fight that.
 * A newly opened recording resets it.
 */
const userPaused = ref(false);

watch(ready, (isReady) => {
    if (!isReady) {
        return;
    }

    state.value = 'ready';

    // Autoplay: audible when the browser allows playback after an interaction,
    // otherwise muted until the visitor touches the page (attemptAutoplay).
    if (autoplay.value && !userPaused.value && !playing.value) {
        attemptAutoplay();
    }
});

// Switching autoplay on while reading should start the recording right away.
watch(autoplay, (isOn) => {
    if (isOn && !playing.value && !userPaused.value) {
        attemptAutoplay();
    }
});

// Another recording means a fresh autoplay intent.
watch(audioUrl, () => {
    userPaused.value = false;
});

/**
 * The play button: the visitor's own press always wins over autoplay, so a
 * pause they asked for is never fought by the next autoplay attempt.
 */
function onToggle() {
    userPaused.value = playing.value;
    toggle();
}

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
    window.addEventListener('popstate', onPopState);

    if (audioUrl.value) {
        state.value = 'loading';
        load(audioUrl.value);

        return;
    }

    if (poem.value.generateUrl) {
        generate();

        return;
    }

    state.value = 'missing';
});

onBeforeUnmount(() => {
    window.clearTimeout(loadWatchdog);
    window.removeEventListener('keydown', onKeydown);
    window.removeEventListener('popstate', onPopState);
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

        <!-- top bar -->
        <header
            class="relative z-20 flex items-center justify-between gap-2 px-4 pt-[max(0.9rem,env(safe-area-inset-top))] pb-1"
        >
            <!-- the author's mark, straight from the server payload -->
            <a
                :href="poem.homeUrl || '/'"
                class="h-9 w-9 shrink-0 overflow-hidden rounded-full bg-stone-100 ring-1 ring-white/15"
                aria-label="Գլխավոր"
            >
                <img v-if="poem.logoUrl" :src="poem.logoUrl" alt="" class="h-full w-full object-cover">
                <span v-else class="grid h-full w-full place-items-center text-xs font-bold">ՊՍ</span>
            </a>

            <div class="min-w-0 text-center">
                <h1 class="truncate text-sm font-semibold">{{ poem.title }}</h1>
                <p class="text-[10px] uppercase tracking-widest text-white/35">
                    {{ poem.author }}<template v-if="playsLabel"> · ▶ {{ playsLabel }}</template>
                </p>
            </div>

            <div class="flex shrink-0 items-center gap-1.5">
                <button
                    type="button"
                    class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-white/5"
                    aria-label="Փնտրել"
                    title="Փնտրել"
                    @click="searchOpen = true"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="7" />
                        <path d="M20 20l-3.6-3.6" />
                    </svg>
                </button>

                <button
                    type="button"
                    class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-white/5 text-sm"
                    aria-label="Կիսվել"
                    @click="share"
                >⤴</button>
            </div>
        </header>

        <!-- portrait -->
        <div class="relative z-10 flex min-h-0 flex-1 items-center justify-center px-10">
            <div class="aspect-square h-[24dvh] max-h-56">
                <SevakSilhouette
                    :progress="silhouetteProgress"
                    :state="silhouetteState"
                    :gold="gold"
                    :logo="poem.portraitUrl || poem.logoUrl"
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
                :key="poem.slug"
                :lines="lines"
                :active-index="activeIndex"
                :state-for="stateFor"
            />

            <div v-else-if="showRawText" class="karaoke-mask max-h-[38dvh] overflow-y-auto overscroll-contain px-6 py-4">
                <p class="whitespace-pre-line text-center text-base leading-relaxed text-white/45">
                    {{ poem.content }}
                </p>

                <div v-if="state === 'missing' || state === 'error'" class="mt-4 text-center">
                    <p class="text-xs text-white/40">{{ statusText }}</p>
                    <button
                        v-if="poem.generateUrl"
                        type="button"
                        class="mt-2 rounded-full border border-amber-300/40 px-4 py-1.5 text-xs font-medium text-amber-200"
                        @click="reload"
                    >
                        Ստեղծել AI ձայնը
                    </button>
                </div>
            </div>
        </section>

        <!-- controls -->
        <section class="relative z-10 px-4 pb-[max(1.1rem,env(safe-area-inset-bottom))] pt-1 sm:px-5">
            <!-- Browsers play sound only after a deliberate gesture: ask for one. -->
            <p v-if="needsStart" class="mb-2 text-center text-[11px] text-amber-200/85">
                Սեղմիր ▶՝ լսելու համար
            </p>

            <PlayerControls
                :playing="playing"
                :current-time="time"
                :duration="duration"
                :rate="rate"
                :muted="muted"
                :disabled="state !== 'ready'"
                :attention="autoplayBlocked"
                :has-prev="Boolean(poem.prev)"
                :has-next="Boolean(poem.next) || shuffle"
                @toggle="onToggle"
                @seek="seekRatio"
                @rate="setRate"
                @mute="toggleMute"
                @prev="go(poem.prev)"
                @next="goNext"
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
                <a :href="poem.readingUrl" class="shrink-0 underline-offset-2 hover:text-white/60 hover:underline">
                    Ամբողջ տեքստը
                </a>
                <span class="truncate">{{ navHint }}</span>
            </div>
        </section>

        <!-- vertical navigation rail (desktop: on mobile the swipe does this job) -->
        <nav class="absolute right-2 top-1/2 z-20 hidden -translate-y-1/2 flex-col gap-2 sm:flex">
            <button
                type="button"
                class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-black/30 text-sm backdrop-blur transition disabled:opacity-25"
                :disabled="!poem.prev"
                aria-label="Նախորդը"
                @click="go(poem.prev)"
            >
                ⌃
            </button>
            <button
                type="button"
                class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-black/30 text-sm backdrop-blur transition disabled:opacity-25"
                :disabled="!poem.next && !shuffle"
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

        <!-- Search: the same modal as on the catalogue, inside the player. -->
        <SearchPanel
            v-if="searchOpen"
            :url="poem.searchUrl"
            :accent="gold"
            @close="searchOpen = false"
            @select="onSearchSelect"
        />
    </div>
</template>



