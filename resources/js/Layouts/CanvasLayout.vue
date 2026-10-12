<script setup>
import { provide, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AiChatWidget from '../Components/Projects/AiChatWidget.vue';
import ConfirmDialog from '../Components/ConfirmDialog.vue';
import ProductTourHost from '../Components/ProductTourHost.vue';
import { GLOBAL_AI_CHAT_KEY } from '../composables/useGlobalAiChat';

defineProps({
    title: {
        type: String,
        required: true,
    },
    variant: {
        type: String,
        default: '',
    },
});

const aiChatWidgetRef = ref(null);

provide(GLOBAL_AI_CHAT_KEY, {
    open: (...args) => aiChatWidgetRef.value?.open(...args),
    openWithBootstrap: (...args) => aiChatWidgetRef.value?.openWithBootstrap(...args),
    close: (...args) => aiChatWidgetRef.value?.close(...args),
});
</script>

<template>
    <Head :title="title" />

    <div
        class="page--canvas project-canvas"
        :class="variant"
        data-tour="project-canvas"
    >
        <slot />
        <AiChatWidget ref="aiChatWidgetRef" variant="canvas" />
        <ProductTourHost />
        <ConfirmDialog />
    </div>
</template>
