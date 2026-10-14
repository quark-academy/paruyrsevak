import { sameOrigin } from './same-origin';

/**
 * Catalogue behaviour: the hero shortcuts and the "show more" button.
 *
 * Kept in the compiled bundle on purpose — an inline script is what proxies,
 * minifiers and browser extensions like to rewrite, and a rewritten script
 * simply stops working. Both requests below use relative URLs (see
 * same-origin.js), and the button appends the next slice instead of sending the
 * visitor to a second page.
 */
export function initCatalogue() {
    const prefKey = 'sevak:player-prefs';

    /** The player should start as soon as the visitor lands on a poem. */
    function enableAutoplay() {
        try {
            const prefs = JSON.parse(window.localStorage.getItem(prefKey) || '{}') || {};
            prefs.autoplay = true;
            window.localStorage.setItem(prefKey, JSON.stringify(prefs));
        } catch (error) {
            // Preferences are a nice-to-have: never block navigation.
        }
    }

    const start = document.querySelector('[data-start-listening]');

    if (start) {
        start.addEventListener('click', enableAutoplay);
    }

    const random = document.querySelector('[data-random-poem]');

    if (random) {
        random.addEventListener('click', async function () {
            enableAutoplay();
            random.disabled = true;
            random.classList.add('opacity-60');

            try {
                const response = await fetch(sameOrigin(random.dataset.randomPoem), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await response.json();

                if (data.url) {
                    window.location.href = data.url;

                    return;
                }
            } catch (error) {
                // fall through: re-enable the button
            }

            random.disabled = false;
            random.classList.remove('opacity-60');
        });
    }

    const more = document.querySelector('[data-load-more]');

    if (!more) {
        return;
    }

    const label = more.innerHTML;

    more.addEventListener('click', async function (event) {
        event.preventDefault();

        const list = document.querySelector('[data-cards]');
        const href = more.getAttribute('href');

        if (!href || !list || more.dataset.loading) {
            return;
        }

        const url = new URL(href, window.location.href);
        url.searchParams.set('fragment', '1');

        more.dataset.loading = '1';
        more.classList.add('opacity-60');

        try {
            const response = await fetch(url.pathname + url.search, {
                headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!response.ok) {
                throw new Error('load more failed');
            }

            const html = await response.text();

            // Only cards are welcome here: anything else is a full document and
            // would tear the list apart. The marker comes from the partial.
            if (html.indexOf('data-card') === -1) {
                throw new Error('unexpected answer');
            }

            list.insertAdjacentHTML('beforeend', html);

            const next = response.headers.get('X-Next-Page');

            if (next) {
                more.setAttribute('href', next);
            } else {
                more.remove();
            }
        } catch (error) {
            // Stay where the visitor is and say what happened: a jump to a
            // second page is exactly what this button exists to avoid.
            more.innerHTML = 'Չհաջողվեց բեռնել, փորձիր նորից';

            window.setTimeout(function () {
                more.innerHTML = label;
            }, 2600);
        } finally {
            delete more.dataset.loading;
            more.classList.remove('opacity-60');
        }
    });
}
