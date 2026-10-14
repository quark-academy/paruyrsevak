/**
 * Player state guard — runs in plain Node, no browser and no test framework.
 *
 * Swapping the audio src (a swipe to the next poem) stops playback WITHOUT
 * firing "pause". The player used to keep believing it was still playing, so
 * autoplay bailed out (nothing played) and the ▶ button showed ❚❚ while its
 * taps paused silence. A second regression made the ❚❚ button stay ❚❚ after a
 * pause, because the "pause" handler trusted the wrong side of the flag.
 *
 * The fake element fires its events on a queued task, exactly like a real media
 * element — that ordering is what exposes both bugs.
 *
 * Run: npm run check:player
 */

/** Media events never fire synchronously: queue them like a browser does. */
function announce(element, type) {
    setTimeout(() => element.dispatchEvent(new Event(type)), 0);
}

/** Let every queued event run. */
const settle = () => new Promise((resolve) => setTimeout(resolve, 2));

class FakeAudio extends EventTarget {
    constructor() {
        super();
        this.paused = true;
        this.currentTime = 0;
        this.duration = 0;
        this._src = '';
        this.playCalls = 0;
        this.pauseCalls = 0;
        this.loadCalls = 0;
    }

    get src() {
        return this._src;
    }

    set src(value) {
        this._src = value;
    }

    play() {
        this.playCalls += 1;
        this.paused = false;
        announce(this, 'play');

        return Promise.resolve();
    }

    pause() {
        this.pauseCalls += 1;

        if (!this.paused) {
            this.paused = true;
            announce(this, 'pause');
        }
    }

    load() {
        this.loadCalls += 1;
    }
}

globalThis.Audio = FakeAudio;
globalThis.requestAnimationFrame = (fn) => setTimeout(() => fn(Date.now()), 16);
globalThis.cancelAnimationFrame = (id) => clearTimeout(id);
globalThis.window = globalThis.window ?? {};

const { useAudioPlayer } = await import('../../resources/js/composables/useAudioPlayer.js');

let failures = 0;

function check(label, actual, expected) {
    const ok = actual === expected;

    if (!ok) {
        failures += 1;
    }

    console.log(`${ok ? '  ok  ' : ' FAIL '} ${label}${ok ? '' : ` (expected ${expected}, got ${actual})`}`);
}

const player = useAudioPlayer();

/** Pretend the recording reported its metadata (this is what "ready" means). */
function metadata(element, seconds) {
    element.duration = seconds;
    announce(element, 'loadedmetadata');
}

player.load('first.mp3');

const audio = player.audio.value;

metadata(audio, 12);
await settle();
check('metadata makes the player ready', player.ready.value, true);

await player.play();
await settle();
check('▶ starts the recording', player.playing.value, true);

// The swipe: the next poem arrives in the same document.
player.load('second.mp3');
check('a new src pauses the element', audio.pauseCalls >= 1, true);
check('a new src clears the playing flag', player.playing.value, false);
await settle();

metadata(audio, 20);
await settle();
await player.attemptAutoplay();
await settle();
check('autoplay plays the next poem', audio.playCalls, 2);
check('the next poem is playing', player.playing.value, true);

// Even if the flag and the element ever disagree, ▶ must start the audio.
player.playing.value = true;
audio.paused = true;
player.toggle();
await settle();
check('a stale flag cannot turn ▶ into a pause', audio.paused, false);

// The pause button (also the lock screen, headphones or a call) has to turn
// ❚❚ back into ▶ — the event lands after the click, like in a browser.
player.pause();
check('pause stops the element', audio.paused, true);
await settle();
check('pause flips the icon back to ▶', player.playing.value, false);

// A late "pause" event must not flip the icon while the next src plays.
player.playing.value = true;
audio.paused = false;
audio.dispatchEvent(new Event('pause'));
check('a late pause event is ignored while playing', player.playing.value, true);

console.log(failures === 0 ? '\nPlayer state: OK' : `\nPlayer state: ${failures} failure(s)`);

process.exit(failures === 0 ? 0 : 1);

