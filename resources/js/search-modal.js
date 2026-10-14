import { sameOrigin } from './same-origin';

/**
 * Header search modal.
 *
 * One icon opens a small dialog with live results (Api\PoemSearchController);
 * a match is a plain link, and "all results" hands the visitor to the catalogue
 * with the same query it already understands. Nothing is fetched until the
 * visitor actually types, and the list is built with DOM nodes so a poem title
 * can never be injected as markup.
 */
export function initSearchModal() {
    const modal = document.getElementById('search-modal');

    if (!modal) {
        return;
    }

    const form = modal.querySelector('[data-search-form]');
    const input = modal.querySelector('[data-search-input]');
    const list = modal.querySelector('[data-search-results]');
    const close = modal.querySelector('[data-search-close]');
    const endpoint = modal.dataset.searchUrl || '';
    const IDLE = 'Գրիր առնվազն երկու տառ։';

    let timer = null;
    let query = null;

    function message(text) {
        list.replaceChildren();

        const line = document.createElement('p');
        line.className = 'px-3 py-6 text-center text-sm text-stone-400';
        line.textContent = text;
        list.append(line);
    }

    function render(data) {
        const results = Array.isArray(data.results) ? data.results : [];

        if (results.length === 0) {
            message(data.message || 'Ոչինչ չգտնվեց։');

            return;
        }

        list.replaceChildren();

        results.forEach((result) => {
            const link = document.createElement('a');
            link.href = result.url;
            link.className = 'flex items-center gap-3 rounded-2xl px-3 py-2.5 transition hover:bg-stone-100';

            const title = document.createElement('span');
            title.className = 'min-w-0 flex-1 truncate text-sm font-medium text-stone-900';
            title.textContent = result.title;
            link.append(title);

            const label = document.createElement('span');
            label.className = 'shrink-0 rounded-full bg-stone-100 px-2 py-0.5 text-[11px] font-medium text-stone-500';
            label.textContent = result.label;
            link.append(label);

            list.append(link);
        });

        if (data.allUrl) {
            const all = document.createElement('a');
            all.href = data.allUrl;
            all.className = 'mt-1 block rounded-2xl px-3 py-2.5 text-center text-sm font-medium text-stone-500 transition hover:bg-stone-100 hover:text-stone-900';
            all.textContent = 'Բոլոր արդյունքները →';
            list.append(all);
        }
    }

    async function search(term) {
        if (term.length < 2) {
            message(IDLE);

            return;
        }

        query?.abort();
        query = new AbortController();

        try {
            const response = await fetch(sameOrigin(`${endpoint}?q=${encodeURIComponent(term)}`), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: query.signal,
            });

            render(await response.json());
        } catch (error) {
            // An aborted request is just the next keystroke arriving first.
            if (error?.name !== 'AbortError') {
                message('Ցանցի սխալ։ Փորձիր նորից։');
            }
        }
    }

    function open() {
        modal.classList.remove('hidden');
        input.value = '';
        message(IDLE);
        document.body.style.overflow = 'hidden';
        window.setTimeout(() => input.focus(), 30);
    }

    function closeModal() {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }

    document.querySelectorAll('[data-search-open]').forEach((button) => {
        button.addEventListener('click', open);
    });

    close?.addEventListener('click', closeModal);

    // A tap on the dimmed background closes; a tap inside the card does not.
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });

    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        search(input.value.trim());
    });

    input?.addEventListener('input', () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(() => search(input.value.trim()), 220);
    });
}