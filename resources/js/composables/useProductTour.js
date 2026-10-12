import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    clearTourState,
    startProductTour,
    stopProductTour,
} from '../tours/productTour';

export function useProductTour() {
    const page = usePage();
    const training = computed(() => page.props.training ?? null);

    async function beginTour({ fromStep = 0 } = {}) {
        if (!training.value?.show_url) {
            return;
        }

        await startProductTour(training.value, { fromStep });
    }

    function cancelTour() {
        stopProductTour();
    }

    async function startFromKnowledgeBase() {
        if (!training.value) {
            return;
        }

        if (!window.location.pathname.startsWith('/knowledge')) {
            await new Promise((resolve) => {
                router.visit('/knowledge', {
                    onFinish: () => resolve(),
                });
            });
        }

        clearTourState();
        await beginTour({ fromStep: 0 });
    }

    return {
        training,
        beginTour,
        cancelTour,
        startFromKnowledgeBase,
    };
}
