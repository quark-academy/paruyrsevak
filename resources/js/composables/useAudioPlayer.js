import { computed, onBeforeUnmount, ref, shallowRef, watch } from 'vue';

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
    /** Set when the browser refuses audible autoplay (needs a user gesture). */
    const blocked = ref(false);
    /**
     * Set when audible autoplay was refused and the player fell back to a
     * muted stream: it keeps playing silently until the visitor interacts.
     */
    const mutedAutoplay = ref(false);
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
        el.addEventListener('play', () => { playing.value = true; });
        el.addEventListener('playing', () => { loading.value = false; });
        el.addEventListener('pause', () => { playing.value = false; });
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

        return el
            .play()
            .then(() => {
                blocked.value = false;
                resumeAfterAbort = false;
            })
            .catch((error) => {
                // Browsers only allow audible playback after a user gesture:
                // remember it so the player knows it must ask for a tap.
                playing.value = false;
                blocked.value = error?.name === 'NotAllowedError';

                // Not a policy decision: the call was interrupted because the
                // media was not buffered yet. Play again once it can.
                resumeAfterAbort = !blocked.value;
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
     * 1. Audible playback first — allowed as soon as the visitor interacted
     *    with the document (a swipe or a tap on the previous poem).
     * 2. If the browser refuses that, play muted: muted autoplay IS allowed.
     *    The stream then runs silently and the first interaction turns the
     *    sound on, which is the closest the web gets to autoplay on a phone.
     */
    async function attemptAutoplay() {
        if (playing.value) {
            return;
        }

        await play();

        if (playing.value || !blocked.value) {
            return;
        }

        mutedAutoplay.value = true;
        setMuted(true);
        await play();

        if (!playing.value) {
            // Even muted playback was refused: hand the player to the visitor.
            mutedAutoplay.value = false;
            setMuted(false);
        }

        playOnFirstGesture();
    }

    /** The visitor asked for sound (the hint pill or the play button). */
    function unmuteNow() {
        mutedAutoplay.value = false;
        setMuted(false);

        if (!playing.value) {
            play();
        }
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

    /**
     * Autoplay retry for browsers that refuse audible playback until the
     * visitor interacts with the page.
     *
     * The recording then starts on the very first touch, scroll or key press —
     * usually the moment the visitor reaches for the screen — so "autoplay"
     * still feels automatic on phones. Taps that land on a control are ignored:
     * those controls start playback themselves.
     */
    let disarmGestureResume = null;

    function playOnFirstGesture() {
        if (disarmGestureResume) {
            return;
        }

        const events = ['pointerdown', 'touchstart', 'keydown', 'wheel'];

        function stop() {
            disarmGestureResume = null;
            events.forEach((name) => window.removeEventListener(name, start, true));
        }

        function start(event) {
            const target = event.target;
            const onControl = target instanceof Element
                && Boolean(target.closest('button, a, input, select, textarea, [role="button"]'));

            // Space is the player's own play/pause shortcut, and every control
            // looks after its own taps.
            if (onControl || event.code === 'Space') {
                return;
            }

            stop();

            // The muted autoplay stream gets its sound back on any interaction.
            if (mutedAutoplay.value) {
                mutedAutoplay.value = false;
                setMuted(false);
            }

            if (!playing.value) {
                play();
            }
        }

        disarmGestureResume = stop;

        events.forEach((name) => window.addEventListener(name, start, { capture: true, passive: true }));
    }

    // Once something really plays, a pending retry is pointless.
    watch(playing, (isPlaying) => {
        if (isPlaying) {
            disarmGestureResume?.();
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
        playOnFirstGesture,
        attemptAutoplay,
        unmuteNow,
        setMuted,
        mutedAutoplay,
    };
}
