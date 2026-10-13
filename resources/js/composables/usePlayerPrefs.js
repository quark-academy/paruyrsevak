import { computed, ref, watch } from 'vue';

/**
 * Where the visitor's player choices live.
 *
 * Keeping them in localStorage means "autoplay + shuffle" survives navigation
 * between poems: every newly opened poem keeps playing without another tap.
 */
export const PREFS_STORAGE_KEY = 'sevak:player-prefs';

/**
 * What happens when a recording ends — exactly one mode is active at a time.
 *
 * sequential: continue with the next poem in order
 * loop:       repeat the same recording
 * shuffle:    continue with a random poem
 */
export const MODES = ['sequential', 'loop', 'shuffle'];

/** Preferences stored by older versions (two independent switches). */
function migratedMode(stored) {
    if (stored.loop === true) {
        return 'loop';
    }

    if (stored.shuffle === true) {
        return 'shuffle';
    }

    return 'sequential';
}

function readStored() {
    try {
        const raw = window.localStorage.getItem(PREFS_STORAGE_KEY);
        const parsed = raw ? JSON.parse(raw) : null;

        return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (error) {
        return {};
    }
}

function persist(preferences) {
    try {
        window.localStorage.setItem(PREFS_STORAGE_KEY, JSON.stringify(preferences));
    } catch (error) {
        // Private mode / quota exceeded: the preference simply does not stick.
    }
}

/**
 * @param {{autoplay?: boolean, mode?: string}} defaults
 */
export function usePlayerPrefs(defaults = {}) {
    const stored = readStored();

    const autoplay = ref(typeof stored.autoplay === 'boolean' ? stored.autoplay : Boolean(defaults.autoplay));
    const mode = ref(MODES.includes(stored.mode) ? stored.mode : (MODES.includes(defaults.mode) ? defaults.mode : migratedMode(stored)));
    const seenIntro = ref(typeof stored.seenIntro === 'boolean' ? stored.seenIntro : false);

    watch([autoplay, mode, seenIntro], () => {
        persist({
            autoplay: autoplay.value,
            mode: mode.value,
            seenIntro: seenIntro.value,
        });
    });

    const repeat = computed(() => mode.value === 'loop');
    const shuffle = computed(() => mode.value === 'shuffle');

    function setMode(value) {
        if (MODES.includes(value)) {
            mode.value = value;
        }
    }

    function toggleAutoplay() {
        autoplay.value = !autoplay.value;
    }

    function markIntroSeen() {
        seenIntro.value = true;
    }

    return {
        autoplay,
        mode,
        seenIntro,
        repeat,
        shuffle,
        setMode,
        toggleAutoplay,
        markIntroSeen,
    };
}

