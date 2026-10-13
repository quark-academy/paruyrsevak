import { ref, watch } from 'vue';

/**
 * Where the visitor's player choices live.
 *
 * Keeping them in localStorage means "autoplay + shuffle" survives navigation
 * between poems: every newly opened poem keeps playing without another tap.
 * The same key is read by the reading page player (see poems/show.blade.php).
 */
export const PREFS_STORAGE_KEY = 'sevak:player-prefs';

const PREF_KEYS = ['autoplay', 'loop', 'shuffle'];

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
 * @param {{autoplay?: boolean, loop?: boolean, shuffle?: boolean}} defaults
 */
export function usePlayerPrefs(defaults = {}) {
    const stored = readStored();

    const state = {};

    PREF_KEYS.forEach((key) => {
        state[key] = ref(typeof stored[key] === 'boolean' ? stored[key] : Boolean(defaults[key]));
    });

    watch([state.autoplay, state.loop, state.shuffle], () => {
        persist({
            autoplay: state.autoplay.value,
            loop: state.loop.value,
            shuffle: state.shuffle.value,
        });
    });

    function set(key, value) {
        if (state[key]) {
            state[key].value = Boolean(value);
        }
    }

    function toggle(key) {
        if (state[key]) {
            state[key].value = !state[key].value;
        }
    }

    return {
        autoplay: state.autoplay,
        loop: state.loop,
        shuffle: state.shuffle,
        set,
        toggle,
    };
}
