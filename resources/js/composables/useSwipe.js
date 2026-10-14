import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Vertical swipe navigation (Shorts gesture).
 *
 * - swipe up   -> onUp   (next poem)
 * - swipe down -> onDown (previous poem)
 *
 * Two rules keep the gesture out of the way of reading:
 *   - horizontal-dominant gestures are ignored, they belong to scrolling;
 *   - a swipe that starts inside a scrollable area (the lyrics) scrolls that
 *     area first and only switches the poem once it reached its edge, so the
 *     text can be read up and down without jumping to another poem.
 */
export function useSwipe({ onUp, onDown, threshold = 70 } = {}) {
    const startX = ref(0);
    const startY = ref(0);
    let tracking = false;
    let innerScroller = null;

    function point(event) {
        const touch = event.changedTouches?.[0] ?? event.touches?.[0] ?? event;

        return { x: touch.clientX, y: touch.clientY };
    }

    /** Nearest ancestor that really scrolls vertically (the lyrics list). */
    function scrollableAncestor(node) {
        let el = node instanceof Element ? node : null;

        while (el && el !== document.documentElement) {
            const overflow = window.getComputedStyle(el).overflowY;

            if (/(auto|scroll)/.test(overflow) && el.scrollHeight > el.clientHeight + 4) {
                return el;
            }

            el = el.parentElement;
        }

        return null;
    }

    /** Swiping up navigates only at the bottom of the inner list, and vice versa. */
    function atEdge(element, deltaY) {
        if (!element) {
            return true;
        }

        return deltaY < 0
            ? element.scrollTop + element.clientHeight >= element.scrollHeight - 2
            : element.scrollTop <= 2;
    }

    function handleStart(event) {
        const { x, y } = point(event);
        startX.value = x;
        startY.value = y;
        innerScroller = scrollableAncestor(event.target);
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
        const inner = innerScroller;

        innerScroller = null;

        if (Math.abs(deltaY) < threshold || Math.abs(deltaX) > Math.abs(deltaY)) {
            return;
        }

        // The gesture belonged to the lyrics: let them scroll.
        if (!atEdge(inner, deltaY)) {
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
