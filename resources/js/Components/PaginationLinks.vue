<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <nav v-if="links.length > 3" class="mt-6 flex flex-wrap items-center gap-1">
        <template v-for="(link, index) in links" :key="`${link.label}-${index}`">
            <Link
                v-if="link.url"
                :href="link.url"
                class="rounded-lg px-3 py-1.5 text-sm transition-colors"
                :class="link.active
                    ? 'bg-f5-green font-semibold text-f5-black'
                    : 'text-f5-text-muted hover:bg-f5-surface-2 hover:text-f5-text dark:text-white/50 dark:hover:bg-white/5 dark:hover:text-white'"
                v-html="link.label"
            />
            <span
                v-else
                class="px-3 py-1.5 text-sm text-f5-text-muted dark:text-white/30"
                v-html="link.label"
            />
        </template>
    </nav>
</template>
