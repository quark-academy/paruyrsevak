import { createApp } from 'vue';
import PoemShorts from './components/PoemShorts.vue';
import './bootstrap';

/**
 * Mounts the Shorts player on the canonical poem page (/{slug}).
 * The server renders a JSON payload next to the mount node so the first
 * paint is instant and crawlers still get the SEO markup.
 */
function readPayload() {
    const node = document.getElementById('poem-shorts-payload');

    if (!node) {
        return {};
    }

    try {
        return JSON.parse(node.textContent || '{}');
    } catch (error) {
        console.error('[poem-shorts] invalid payload', error);

        return {};
    }
}

const root = document.getElementById('poem-shorts');

if (root) {
    createApp(PoemShorts, { payload: readPayload() }).mount(root);
}
