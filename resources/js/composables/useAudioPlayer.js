import { computed, onBeforeUnmount, ref, shallowRef } from 'vue';

/**
 * Tiny reactive wrapper around a single HTMLAudioElement.
 *
 * Exposes smooth values (updated through requestAnimationFrame) so the
 * karaoke highlight can follow audio.currentTime at screen refresh rate
 * instead of the coarse ~4Hz "timeupdate" event.
 */
export function useAudioPlayer() {
    const audio = shallowRef(null);
    const src = ref(null);
    const playing = ref(false);
    const ready = ref(false);
    const loading = ref(false);
    const error = ref(null);
    const time = ref(0);
    const duration = ref(0);
    const rate = ref(1);
    const muted = ref(false);
    const loop = ref(false);
    const seeking = ref(false);

    let rafId = null;

    const progress = computed(() => (duration.value > 0 ? Math.min(1, time.value / duration.value) : 0));
    const started = computed(() => time.value > 0.05);

    function ensure() {
        if (audio.value) {
            return audio.value;
        }

        const el = new Audio();
        el.preload = 'metadata';
        el.crossOrigin = 'anonymous';
        el.volume = muted.value ? 0 : 1;

        el.addEventListener('loadedmetadata', () => {
            duration.value = isFinite(el.duration) ? el.duration : 0;
        });
        el.addEventListener('canplay', () => {
            ready.value = true;
            loading.value = false;
        });
        el.addEventListener('play', () => { playing.value = true; });
        el.addEventListener('playing', () => { loading.value = false; });
        el.addEventListener('pause', () => { playing.value = false; });
        el.addEventListener('waiting', () => { loading.value = true; });
        el.addEventListener('seeking', () => { seeking.value = true; });
        el.addEventListener('seeked', () => { seeking.value = false; });
        el.addEventListener('ended', () => {
            playing.value = false;
            if (loop.value) {
                el.currentTime = 0;
                el.play().catch(() => {});
            }
        });
        el.addEventListener('error', () => {
            error.value = 'Ձայնի բեռնումը ձախողվեց։';
            loading.value = false;
        });

        audio.value = el;
        rafId = requestAnimationFrame(tick);

        return el;
    }

    function tick() {
        const el = audio.value;

        if (el) {
            if (!seeking.value) {
                time.value = el.currentTime || 0;
            }

            if (isFinite(el.duration) && el.duration > 0 && Math.abs(duration.value - el.duration) > 0.05) {
                duration.value = el.duration;
            }
        }

        rafId = requestAnimationFrame(tick);
    }

    function load(url) {
        src.value = url;
        ready.value = false;
        error.value = null;
        time.value = 0;
        duration.value = 0;

        if (!url) {
            return;
        }

        loading.value = true;
        const el = ensure();
        el.src = url;
        el.load();
    }

    function play() {
        const el = ensure();

        if (!el.src) {
            return Promise.resolve();
        }

        return el.play().catch(() => {
            playing.value = false;
        });
    }

    function pause() {
        audio.value?.pause();
    }

    function toggle() {
        if (playing.value) {
            pause();
        } else {
            play();
        }
    }

    function seek(seconds) {
        const el = audio.value;

        if (!el) {
            return;
        }

        el.currentTime = Math.max(0, Math.min(seconds, duration.value || el.duration || 0));
        time.value = el.currentTime;
        el.play().catch(() => {});
    }

    function seekRatio(ratio) {
        const total = duration.value || audio.value?.duration || 0;
        if (total > 0) {
            seek(ratio * total);
        }
    }

    function setRate(value) {
        rate.value = value;
        if (audio.value) {
            audio.value.playbackRate = value;
        }
    }

    function toggleMute() {
        muted.value = !muted.value;
        if (audio.value) {
            audio.value.volume = muted.value ? 0 : 1;
        }
    }

    function toggleLoop() {
        loop.value = !loop.value;
    }

    onBeforeUnmount(() => {
        if (rafId !== null) {
            cancelAnimationFrame(rafId);
            rafId = null;
        }

        if (audio.value) {
            audio.value.pause();
            audio.value.src = '';
            audio.value = null;
        }
    });

    return {
        audio,
        src,
        playing,
        ready,
        loading,
        error,
        time,
        duration,
        rate,
        muted,
        loop,
        progress,
        started,
        load,
        play,
        pause,
        toggle,
        seek,
        seekRatio,
        setRate,
        toggleMute,
        toggleLoop,
    };
}
