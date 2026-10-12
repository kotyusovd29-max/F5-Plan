<script setup>
import { onMounted, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
    clearTourState,
    resumeProductTourIfNeeded,
    startProductTour,
    stopProductTour,
} from '../tours/productTour';

const page = usePage();

async function boot() {
    const training = page.props.training;
    const params = new URLSearchParams(window.location.search);

    if (params.get('tour') === '1' && training?.show_url) {
        clearTourState();
        // Skip KB intro when opened via ?tour=1 on the training project
        await startProductTour(training, { fromStep: 2 });
        return;
    }

    await resumeProductTourIfNeeded(training);
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        stopProductTour();
    }
}

onMounted(() => {
    window.setTimeout(() => {
        boot();
    }, 80);
    window.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <span class="hidden" aria-hidden="true" data-tour-host="1" />
</template>
