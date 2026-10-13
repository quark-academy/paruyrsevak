import { computed } from 'vue';

/**
 * Word-by-word karaoke state machine.
 *
 * The word list is treated as a sorted array of intervals [{s, e}]. On every
 * frame we binary search the last word whose start time is <= current time
 * (O(log n)) and derive three visual states:
 *
 *   - pending : not reached yet (muted)
 *   - lead    : last word that has started (may already be finished)
 *   - active  : the word currently being pronounced (gold highlight)
 *
 * @param {import('vue').Ref<Array<{w: string, s: number, e: number}>>} words
 * @param {import('vue').Ref<number>} time
 */
export function useKaraoke(words, time) {
    const lastStartedIndex = computed(() => {
        const list = words.value;

        if (!list || list.length === 0) {
            return -1;
        }

        const current = time.value;
        let low = 0;
        let high = list.length - 1;
        let found = -1;

        while (low <= high) {
            const mid = (low + high) >> 1;

            if (Number(list[mid].s) <= current) {
                found = mid;
                low = mid + 1;
            } else {
                high = mid - 1;
            }
        }

        return found;
    });

    const activeIndex = computed(() => {
        const index = lastStartedIndex.value;

        if (index < 0) {
            return -1;
        }

        const word = words.value[index];

        // Small tolerance keeps the highlight on screen through micro gaps.
        return time.value <= Number(word.e) + 0.05 ? index : -1;
    });

    const sungCount = computed(() => Math.max(0, lastStartedIndex.value + 1));

    /**
     * Visual state of a single word used by the template.
     */
    function stateFor(index) {
        if (index === activeIndex.value) {
            return 'active';
        }

        if (index <= lastStartedIndex.value) {
            return 'sung';
        }

        return 'pending';
    }

    return { lastStartedIndex, activeIndex, sungCount, stateFor };
}
