<script setup>
defineProps({
    sections: {
        type: Array,
        required: true,
        /** @type {Array<{ id: string, label: string, count?: number|null, filled?: boolean }>} */
    },
});

function scrollToSection(id) {
    const el = document.getElementById(id);

    if (!el) {
        return;
    }

    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>

<template>
    <div class="sticky top-[4.25rem] z-20 -mx-1 px-1 py-1">
        <div
            class="space-y-2 rounded-2xl border border-f5-border bg-f5-surface/95 p-2 shadow-sm backdrop-blur-md dark:border-white/10 dark:bg-f5-black/95"
        >
            <div v-if="$slots.leading" class="min-w-0">
                <slot name="leading" />
            </div>

            <nav
                class="-mx-0.5 overflow-x-auto px-0.5"
                aria-label="Разделы плана"
            >
                <div class="flex w-max min-w-full gap-1">
                    <button
                        v-for="section in sections"
                        :key="section.id"
                        type="button"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-semibold transition-colors"
                        :class="section.filled
                            ? 'text-f5-text hover:bg-f5-surface-2 dark:text-white dark:hover:bg-white/10'
                            : 'text-f5-text-muted hover:bg-f5-surface-2 hover:text-f5-text dark:text-white/45 dark:hover:bg-white/10 dark:hover:text-white'"
                        @click="scrollToSection(section.id)"
                    >
                        <span
                            class="h-1.5 w-1.5 shrink-0 rounded-full"
                            :class="section.filled ? 'bg-f5-green' : 'bg-f5-border dark:bg-white/20'"
                            aria-hidden="true"
                        />
                        {{ section.label }}
                        <span
                            v-if="section.count !== null && section.count !== undefined"
                            class="tabular-nums opacity-70"
                        >{{ section.count }}</span>
                    </button>
                </div>
            </nav>
        </div>
    </div>
</template>
