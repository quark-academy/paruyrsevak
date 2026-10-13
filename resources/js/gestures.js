/**
 * Phone gesture guard: pinch-zoom must not rescale the page.
 *
 * `touch-action: pan-x pan-y` (app.css) already blocks pinch-zoom, but older
 * iOS Safari does not support it and has ignored `user-scalable=no` since
 * iOS 10. There pinch-zoom shows up as the proprietary `gesture*` events, so
 * stopping those covers the remaining devices.
 *
 * Single taps are left untouched: buttons, words and links stay clickable.
 */
export function lockPageZoom() {
    ['gesturestart', 'gesturechange', 'gestureend'].forEach((name) => {
        document.addEventListener(name, (event) => event.preventDefault(), { passive: false });
    });
}
