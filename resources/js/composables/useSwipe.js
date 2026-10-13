import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Vertical swipe navigation (Shorts gesture).
 *
 * - swipe up   -> onUp   (next poem)
 * - swipe down -> onDown (previous poem)
 *
 * Horizontal-dominant gestures are ignored so they never hijack scrolling.
 */
export function useSwipe({ onUp, onDown, threshold = 70 } = {}) {
    const startX = ref(0);
    const startY = ref(0);
    let tracking = false;

    function point(event) {
        const touch = event.changedTouches?.[0] ?? event.touches?.[0] ?? event;

        return { x: touch.clientX, y: touch.clientY };
    }

    function handleStart(event) {
        const { x, y } = point(event);
        startX.value = x;
        startY.value = y;
        tracking = true;
    }

    function handleEnd(event) {
        if (!tracking) {
            return;
        }

        tracking = false;

        const { x, y } = point(event);
        const deltaY = y - startY.value;
        const deltaX = x - startX.value;

        if (Math.abs(deltaY) < threshold || Math.abs(deltaX) > Math.abs(deltaY)) {
            return;
        }

        if (deltaY < 0) {
            onUp?.();
        } else {
            onDown?.();
        }
    }

    onMounted(() => {
        window.addEventListener('touchstart', handleStart, { passive: true });
        window.addEventListener('touchend', handleEnd, { passive: true });
    });

    onBeforeUnmount(() => {
        window.removeEventListener('touchstart', handleStart);
        window.removeEventListener('touchend', handleEnd);
    });
}
