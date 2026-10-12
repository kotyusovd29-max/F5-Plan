<script setup>
defineProps({
    id: {
        type: Number,
        required: true,
    },
    name: {
        type: String,
        required: true,
    },
    comment: {
        type: String,
        default: '',
    },
    summary: {
        type: String,
        default: '',
    },
    destroyUrl: {
        type: String,
        required: true,
    },
    open: {
        type: Boolean,
        default: false,
    },
});
defineEmits(['toggle']);
</script>

<template>
    <article
        class="sortable-item border-t border-f5-border first:border-t-0 dark:border-white/10"
        :class="{ 'bg-f5-surface-2 dark:bg-white/[0.02]': open }"
        :data-sortable-id="id"
        :data-drag-delete-url="destroyUrl"
        :data-drag-delete-label="name"
    >
        <div class="flex items-stretch">
            <span
                class="sortable-handle flex w-9 shrink-0 cursor-grab items-center justify-center border-r border-f5-border text-f5-text-muted select-none active:cursor-grabbing hover:bg-f5-surface-2 hover:text-f5-green dark:border-white/10 dark:hover:bg-white/5"
                title="Перетащить"
                aria-label="Перетащить"
            >⠿</span>

            <button
                type="button"
                class="flex min-w-0 flex-1 items-center gap-3 border-0 bg-transparent px-3 py-3.5 text-left hover:bg-f5-surface-2 dark:hover:bg-white/5"
                @click="$emit('toggle')"
            >
                <span class="max-w-[40%] shrink-0 text-sm font-semibold text-f5-text dark:text-white">{{ name }}</span>
                <span
                    v-if="summary || comment"
                    class="min-w-0 flex-1 truncate text-xs text-f5-text-muted dark:text-white/50"
                >{{ summary || comment }}</span>
                <span
                    class="ml-auto shrink-0 text-[0.65rem] text-f5-text-muted transition-transform dark:text-white/40"
                    :class="{ 'rotate-180': open }"
                    aria-hidden="true"
                >▼</span>
            </button>
        </div>

        <div v-show="open" class="border-t border-f5-border px-5 pb-4 dark:border-white/10">
            <slot />
        </div>
    </article>
</template>
