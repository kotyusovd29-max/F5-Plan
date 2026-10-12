import { computed, ref, unref, watch } from 'vue';

function readStored(key) {
    try {
        return window.localStorage.getItem(key) === '1';
    } catch {
        return false;
    }
}

/**
 * Toggle for arrows into lose / «отказ» stages on the funnel board.
 * Off (default): show only from the selected stage.
 * On: always show all lose transitions.
 */
export function useShowLoseArrows(funnelIdSource) {
    const storageKey = computed(() => `funnel-board-show-lose-arrows:${unref(funnelIdSource)}`);
    const showLoseArrows = ref(readStored(storageKey.value));

    watch(storageKey, (key) => {
        showLoseArrows.value = readStored(key);
    });

    watch(showLoseArrows, (value) => {
        try {
            window.localStorage.setItem(storageKey.value, value ? '1' : '0');
        } catch {
            // ignore storage errors
        }
    });

    function toggleShowLoseArrows() {
        showLoseArrows.value = !showLoseArrows.value;
    }

    return {
        showLoseArrows,
        toggleShowLoseArrows,
    };
}
