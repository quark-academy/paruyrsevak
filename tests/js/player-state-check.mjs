/**
 * Player state guard — runs in plain Node, no browser and no test framework.
 *
 * Swapping the audio src (a swipe to the next poem) stops playback WITHOUT
 * firing "pause". The player used to keep believing it was still playing, so
 * autoplay bailed out (nothing played) and the ▶ button showed ❚❚ while its
 * taps paused silence. These checks keep that from coming back.
 *
 * Run: npm run check:player
 */

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
        this.dispatchEvent(new Event('play'));

        return Promise.resolve();
    }

    pause() {
        this.pauseCalls += 1;

        if (!this.paused) {
            this.paused = true;
            this.dispatchEvent(new Event('pause'));
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
    element.dispatchEvent(new Event('loadedmetadata'));
}

player.load('first.mp3');

const audio = player.audio.value;

metadata(audio, 12);
check('metadata makes the player ready', player.ready.value, true);

await player.play();
check('▶ starts the recording', player.playing.value, true);

// The swipe: the next poem arrives in the same document.
player.load('second.mp3');
check('a new src pauses the element', audio.pauseCalls >= 1, true);
check('a new src clears the playing flag', player.playing.value, false);

metadata(audio, 20);
await player.attemptAutoplay();
check('autoplay plays the next poem', audio.playCalls, 2);
check('the next poem is playing', player.playing.value, true);

// Even if the flag and the element ever disagree, ▶ must start the audio.
player.playing.value = true;
audio.paused = true;
player.toggle();
check('a stale flag cannot turn ▶ into a pause', audio.paused, false);

console.log(failures === 0 ? '\nPlayer state: OK' : `\nPlayer state: ${failures} failure(s)`);

process.exit(failures === 0 ? 0 : 1);
