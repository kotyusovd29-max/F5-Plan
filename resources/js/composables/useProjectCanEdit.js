import { computed, inject } from 'vue';

/**
 * @param {boolean} fallback
 */
export function useProjectCanEdit(fallback = true) {
    const injected = inject('projectCanEdit', null);

    if (injected) {
        return injected;
    }

    return computed(() => fallback);
}
