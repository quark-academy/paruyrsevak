/**
 * Google Analytics 4 for navigations that never reload the document.
 *
 * The player swaps poems in place with history.pushState (the gesture has to
 * survive so the next recording may autoplay), and gtag only reports a page
 * view when the tag boots. Those swaps are reported here with an explicit
 * page_view event. On ordinary Blade pages nothing calls this: the snippet in
 * partials/analytics.blade.php already counted the load.
 */
export function trackPageView(url) {
    // An ad blocker may remove the snippet entirely — never break the player.
    if (typeof window.gtag !== 'function' || !url) {
        return;
    }

    let target;

    try {
        target = new URL(url, window.location.origin);
    } catch (error) {
        return;
    }

    window.gtag('event', 'page_view', {
        page_location: target.href,
        page_path: `${target.pathname}${target.search}`,
        page_title: document.title,
    });
}
