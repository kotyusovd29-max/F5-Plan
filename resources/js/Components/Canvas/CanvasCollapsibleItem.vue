<script setup>
import { ref } from 'vue';

defineProps({
    label: {
        type: String,
        required: true,
    },
    meta: {
        type: String,
        default: '',
    },
});

const open = ref(false);
</script>

<template>
    <div class="canvas-item" :class="{ 'canvas-item--open': open }">
        <button type="button" class="canvas-item__toggle" @click="open = !open">
            <span class="min-w-0 flex-1 truncate text-left">{{ label }}</span>
            <span v-if="meta" class="mx-2 min-w-0 truncate text-xs text-f5-text-muted dark:text-white/50">{{ meta }}</span>
            <span class="canvas-item__icon" aria-hidden="true">▼</span>
        </button>
        <div v-show="open" class="canvas-item__panel">
            <slot />
        </div>
    </div>
</template>
