<script setup>
const props = defineProps({
    status: {
        type: String,
        default: 'draft',
    },
    label: {
        type: String,
        default: '',
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

/** Черновик → согласование → внедрение: muted / yellow / lime */
const toneClass = {
    draft: 'bg-f5-surface-2 text-f5-text-muted border-f5-border dark:bg-white/10 dark:text-white/55 dark:border-white/10',
    pending_approval: 'bg-f5-yellow/20 text-f5-text border-f5-yellow/50 dark:bg-f5-yellow/25 dark:text-white dark:border-f5-yellow/40',
    approved: 'bg-f5-green/20 text-f5-text border-f5-green/50 dark:bg-f5-green/25 dark:text-white dark:border-f5-green/40',
    in_implementation: 'bg-f5-green text-f5-dark border-f5-green dark:bg-f5-green dark:text-f5-dark dark:border-f5-green',
    archived: 'bg-f5-surface-2 text-f5-text-muted border-f5-border/80 dark:bg-white/5 dark:text-white/40 dark:border-white/10',
};
</script>

<template>
    <span
        class="inline-flex items-center rounded-md border font-medium"
        :class="[
            toneClass[status] || toneClass.draft,
            compact ? 'px-1.5 py-0.5 text-[10px] uppercase tracking-wide' : 'px-2 py-0.5 text-xs',
        ]"
    >
        {{ label || status }}
    </span>
</template>
