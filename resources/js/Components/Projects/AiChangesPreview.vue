<script setup>
import { computed } from 'vue';
import { buildChangesSections } from '../../utils/aiChat';

const props = defineProps({
    changes: {
        type: Object,
        default: null,
    },
});

const sections = computed(() => buildChangesSections(props.changes));
</script>

<template>
    <p v-if="sections.length === 0" class="text-xs text-f5-text-muted dark:text-white/50">
        Нет структурированных изменений.
    </p>
    <div v-else class="space-y-2.5">
        <div
            v-for="section in sections"
            :key="section.title"
            class="rounded-lg border border-f5-border bg-f5-surface-2/80 p-3 dark:border-white/10 dark:bg-white/5"
        >
            <h4 class="mb-1.5 text-xs font-semibold text-f5-text dark:text-white">
                {{ section.title }}
            </h4>
            <ul class="list-disc space-y-1 pl-4 text-xs text-f5-text-muted dark:text-white/60">
                <li v-for="(item, index) in section.items" :key="`${section.title}-${index}`">
                    {{ item }}
                </li>
            </ul>
        </div>
    </div>
</template>
