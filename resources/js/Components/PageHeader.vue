<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-xl font-bold text-f5-text dark:text-white">
                {{ title }}
            </h1>
            <p v-if="subtitle || $slots.subtitle" class="mt-1 text-sm text-f5-text-muted dark:text-white/50">
                <slot name="subtitle">
                    {{ subtitle }}
                </slot>
            </p>
        </div>

        <div v-if="$slots.actions" class="shrink-0">
            <slot name="actions" />
        </div>
    </div>
</template>
