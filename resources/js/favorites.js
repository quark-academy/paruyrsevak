/**
 * "Իմ ընտրանին" — the visitor's own list of poems.
 *
 * The list lives in localStorage only: the site has no accounts and sends
 * nothing to the server. Every heart on the page is a mirror of that list, so
 * the same poem always looks the same on the catalogue, the reading page and
 * the favourites page.
 */
export const FAVORITES_STORAGE_KEY = 'sevak:favorites';

function normalize(item) {
    if (!item || typeof item !== 'object') {
        return null;
    }

    const slug = typeof item.slug === 'string' ? item.slug.trim() : '';

    if (slug === '' || typeof item.title !== 'string' || item.title.trim() === '') {
        return null;
    }

    return {
        slug,
        title: item.title.trim(),
        url: typeof item.url === 'string' ? item.url : '',
        excerpt: typeof item.excerpt === 'string' ? item.excerpt : '',
        addedAt: Number.isFinite(item.addedAt) ? item.addedAt : 0,
    };
}

export function readFavorites() {
    try {
        const parsed = JSON.parse(window.localStorage.getItem(FAVORITES_STORAGE_KEY) || '[]');

        return Array.isArray(parsed) ? parsed.map(normalize).filter(Boolean) : [];
    } catch (error) {
        return [];
    }
}

function writeFavorites(items) {
    try {
        window.localStorage.setItem(FAVORITES_STORAGE_KEY, JSON.stringify(items));
    } catch (error) {
        // Private mode or a full storage: the list simply does not persist.
    }
}

export function isFavorite(slug) {
    return readFavorites().some((item) => item.slug === slug);
}

function favoriteItem(button) {
    return {
        slug: button.dataset.slug || '',
        title: button.dataset.title || '',
        url: button.dataset.url || '',
        excerpt: button.dataset.excerpt || '',
        addedAt: Date.now(),
    };
}

/** Add or remove a poem by its data and return whether it is now stored. */
export function toggleFavoriteItem(item) {
    const candidate = normalize({ ...item, addedAt: Number.isFinite(item?.addedAt) ? item.addedAt : Date.now() });

    if (!candidate) {
        return false;
    }

    let items = readFavorites();
    const exists = items.some((entry) => entry.slug === candidate.slug);

    if (exists) {
        items = items.filter((entry) => entry.slug !== candidate.slug);
    } else {
        items = [candidate, ...items];
    }

    writeFavorites(items);
    syncFavoriteButtons();
    updateFavoriteCounts();

    document.dispatchEvent(new CustomEvent('sevak:favorites-changed'));

    return !exists;
}

/** Add or remove the poem of this button and return the new state. */
export function toggleFavorite(button) {
    return toggleFavoriteItem(favoriteItem(button));
}

function setButtonState(button, active) {
    const title = button.dataset.title || '';
    const icon = button.querySelector('[data-favorite-icon]');

    button.setAttribute('aria-pressed', active ? 'true' : 'false');
    button.classList.remove('text-rose-500', 'text-stone-400', 'text-stone-500');
    button.classList.add(active ? 'text-rose-500' : 'text-stone-400');

    if (icon) {
        icon.setAttribute('fill', active ? 'currentColor' : 'none');
    }

    button.setAttribute(
        'aria-label',
        active
            ? `Հեռացնել «${title}»-ը իմ ընտրանուց`
            : `Ավելացնել «${title}» իմ ընտրանուն`,
    );
}

export function syncFavoriteButtons(root = document) {
    root.querySelectorAll('[data-favorite]').forEach((button) => {
        setButtonState(button, isFavorite(button.dataset.slug || ''));
    });
}

export function updateFavoriteCounts() {
    const total = readFavorites().length;

    document.querySelectorAll('[data-favorites-count]').forEach((badge) => {
        badge.textContent = String(total);
        badge.classList.toggle('hidden', total === 0);
    });
}

/** Favourites page: paint the list from localStorage, removals included. */
function initFavoritesPage() {
    const page = document.querySelector('[data-favorites-page]');

    if (!page) {
        return;
    }

    const list = page.querySelector('[data-favorites-list]');
    const empty = page.querySelector('[data-favorites-empty]');
    const template = document.getElementById('favorite-card-template');

    if (!list || !empty || !template) {
        return;
    }

    function render() {
        const items = readFavorites().sort((a, b) => b.addedAt - a.addedAt);

        list.replaceChildren();

        items.forEach((item) => {
            const node = template.content.firstElementChild?.cloneNode(true);

            if (!node) {
                return;
            }

            const link = node.querySelector('[data-favorite-link]');
            const title = node.querySelector('[data-favorite-title]');
            const excerpt = node.querySelector('[data-favorite-excerpt]');
            const button = node.querySelector('[data-favorite]');

            if (link) {
                link.href = item.url || '#';
            }

            if (title) {
                title.textContent = item.title;
            }

            if (excerpt) {
                excerpt.textContent = item.excerpt;
            }

            if (button) {
                button.dataset.slug = item.slug;
                button.dataset.title = item.title;
                button.dataset.url = item.url;
                button.dataset.excerpt = item.excerpt;
                setButtonState(button, true);
            }

            list.append(node);
        });

        empty.classList.toggle('hidden', items.length > 0);
        updateFavoriteCounts();
    }

    render();
    document.addEventListener('sevak:favorites-changed', render);
}

export function initFavorites() {
    initFavoritesPage();
    syncFavoriteButtons();
    updateFavoriteCounts();

    // Hearts arrive later too: the catalogue appends cards with "show more",
    // and the reading page can swap poems without a reload.
    new MutationObserver(() => syncFavoriteButtons()).observe(document.body, {
        childList: true,
        subtree: true,
    });

    window.addEventListener('storage', (event) => {
        if (event.key !== FAVORITES_STORAGE_KEY) {
            return;
        }

        syncFavoriteButtons();
        updateFavoriteCounts();
        document.dispatchEvent(new CustomEvent('sevak:favorites-changed'));
    });
}

/**
 * One delegated listener: every heart, present or future, toggles the same
 * stored list. The buttons are real buttons, so no navigation is involved.
 */
export function initFavoriteButtons() {
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-favorite]');

        if (!button) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        toggleFavorite(button);

        button.classList.remove('favorite-pop');
        // Restart the little pop even on a quick double tap.
        void button.offsetWidth;
        button.classList.add('favorite-pop');
    });
}
