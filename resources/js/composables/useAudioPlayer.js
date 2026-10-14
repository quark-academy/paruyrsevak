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
    /** Poetry reads better a touch slower: the player always opens at 0.8×. */
    const rate = ref(0.8);
    const muted = ref(false);
    const loop = ref(false);
    const seeking = ref(false);
    /**
     * Set when the browser refused audible playback: the recording stays
     * silent until the visitor presses ▶ (the player never runs a muted
     * stream, which would swallow the beginning of the poem unheard).
     */
    const blocked = ref(false);
    /** True once a recording really started with sound in this document. */
    const playedOnce = ref(false);
    /** Bumped every time the recording reaches its end (drives auto-advance). */
    const endedAt = ref(0);

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
        el.muted = muted.value;
        el.volume = muted.value ? 0 : 1;
        el.loop = loop.value;
        el.playbackRate = rate.value;

        el.addEventListener('loadedmetadata', () => {
            duration.value = isFinite(el.duration) ? el.duration : 0;
            // Do not wait for "canplay": with preload="metadata" some browsers
            // (iOS Safari) only fire it once playback was requested, so the
            // autoplay attempt never happened at all.
            ready.value = true;
        });
        el.addEventListener('canplay', () => {
            ready.value = true;
            loading.value = false;
            retryWhenPlayable();
        });
        // The element's own state decides, and only when it agrees: a late
        // "pause" event can arrive after the next src already started playing.
        el.addEventListener('play', () => {
            if (!el.paused) {
                playing.value = true;
            }
        });
        el.addEventListener('playing', () => { loading.value = false; });
        el.addEventListener('pause', () => {
            if (el.paused) {
                playing.value = false;
            }
        });
        el.addEventListener('waiting', () => { loading.value = true; });
        el.addEventListener('seeking', () => { seeking.value = true; });
        el.addEventListener('seeked', () => { seeking.value = false; });
        el.addEventListener('ended', () => {
            playing.value = false;
            endedAt.value += 1;

            if (loop.value) {
                el.currentTime = 0;
                el.play().catch(() => {});
            }
        });
        el.addEventListener('error', () => {
            error.value = 'Ձայնի բեռնումը ձախողվեց։';
            loading.value = false;
            playing.value = false;
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
        // Swapping src stops playback without firing "pause", so the flags are
        // reset by hand: otherwise the button stays on ❚❚ and autoplay never
        // runs again for the next poem (the visitor sees a dead player).
        if (audio.value) {
            audio.value.pause();
        }

        playing.value = false;

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

        return el
            .play()
            .then(() => {
                blocked.value = false;
                playedOnce.value = true;
                resumeAfterAbort = false;
            })
            .catch((error) => {
                // Browsers only allow audible playback after a user gesture:
                // remember it so the player knows it must ask for one press
                // on ▶ (see attemptAutoplay).
                playing.value = false;
                blocked.value = error?.name === 'NotAllowedError';

                // Not a policy decision: the call was interrupted because the
                // media was not buffered yet. Play again once it can.
                resumeAfterAbort = !blocked.value;
            });
    }

    function pause() {
        audio.value?.pause();
        // The element can already be paused (a swapped src): the flag still has
        // to follow, or ▶ and ❚❚ stop matching reality.
        playing.value = false;
    }

    function toggle() {
        // Trust the element, not the flag: if the two ever disagree, a tap must
        // start the recording instead of "pausing" silence.
        const el = audio.value;

        if (el ? !el.paused : playing.value) {
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
        setMuted(!muted.value);
    }

    function setMuted(value) {
        muted.value = Boolean(value);

        if (audio.value) {
            audio.value.muted = muted.value;
            audio.value.volume = muted.value ? 0 : 1;
        }
    }

    /**
     * play() was interrupted because the media was not buffered yet (the
     * browser aborts the call instead of waiting): play again at "canplay".
     */
    let resumeAfterAbort = false;

    function retryWhenPlayable() {
        if (!resumeAfterAbort) {
            return;
        }

        resumeAfterAbort = false;
        play();
    }

    /**
     * Start a freshly opened recording without the visitor pressing anything.
     *
     * Audible playback is attempted once: the browser allows it on desktop and
     * for anyone who already interacted with the document, so moving between
     * poems keeps the stream going. When it is refused the player does NOT
     * fall back to a muted stream — that would swallow the beginning of the
     * poem while the visitor hears nothing. It waits for one explicit press on
     * ▶ instead (the pulsing button and the hint ask for it); from that press
     * on, every following poem starts by itself again.
     */
    async function attemptAutoplay() {
        if (playing.value) {
            return;
        }

        await play();
    }

    function setLoop(value) {
        loop.value = Boolean(value);

        if (audio.value) {
            audio.value.loop = loop.value;
        }
    }

    function toggleLoop() {
        setLoop(!loop.value);
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
        blocked,
        playedOnce,
        endedAt,
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
        setLoop,
        toggleLoop,
        attemptAutoplay,
    };
}
