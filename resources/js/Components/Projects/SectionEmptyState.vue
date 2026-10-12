<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
    actionLabel: {
        type: String,
        default: '',
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['action']);
</script>

<template>
    <div
        class="f5-enter flex flex-col items-center text-center"
        :class="compact ? 'px-3 py-6' : 'px-4 py-10 sm:px-8'"
    >
        <div
            class="flex items-center justify-center rounded-2xl border border-dashed border-f5-border bg-f5-surface-2 text-f5-text-muted dark:border-white/15 dark:bg-white/5 dark:text-white/40"
            :class="compact ? 'mb-3 h-9 w-9' : 'mb-4 h-12 w-12'"
            aria-hidden="true"
        >
            <slot name="icon">
                <svg
                    :class="compact ? 'h-4 w-4' : 'h-5 w-5'"
                    viewBox="0 0 20 20"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.6"
                >
                    <path d="M4 6.5h12M4 10h8M4 13.5h10" stroke-linecap="round" />
                </svg>
            </slot>
        </div>

        <p
            class="font-semibold text-f5-text dark:text-white"
            :class="compact ? 'text-xs' : 'text-sm'"
        >
            {{ title }}
        </p>
        <p
            v-if="description"
            class="mt-1 max-w-sm leading-relaxed text-f5-text-muted dark:text-white/50"
            :class="compact ? 'text-xs' : 'text-sm'"
        >
            {{ description }}
        </p>

        <button
            v-if="actionLabel"
            type="button"
            class="f5-btn f5-btn--primary f5-btn--sm"
            :class="compact ? 'mt-3' : 'mt-5'"
            @click="$emit('action')"
        >
            {{ actionLabel }}
        </button>

        <div
            v-if="$slots.actions"
            class="flex flex-wrap items-center justify-center gap-2"
            :class="compact ? 'mt-3' : 'mt-5'"
        >
            <slot name="actions" />
        </div>
    </div>
</template>
