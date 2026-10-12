<script setup>
import { computed } from 'vue';
import ProjectStatusBadge from './ProjectStatusBadge.vue';

const props = defineProps({
    status: {
        type: String,
        default: 'draft',
    },
    statusLabel: {
        type: String,
        default: '',
    },
    blocks: {
        type: Array,
        required: true,
        /** @type {Array<{ id: string, label: string, filled: boolean, count?: number|null }>} */
    },
    showBlocks: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['jump']);

const total = computed(() => props.blocks.length);
const filledCount = computed(() => props.blocks.filter((block) => block.filled).length);
const allFilled = computed(() => total.value > 0 && filledCount.value === total.value);
const missingLabels = computed(() => (
    props.blocks.filter((block) => !block.filled).map((block) => block.label)
));

const hint = computed(() => {
    if (props.status === 'archived') {
        return 'Проект в архиве';
    }

    if (props.status === 'in_implementation') {
        return allFilled.value ? 'План заполнен, идёт внедрение' : 'Внедрение без полного плана';
    }

    if (props.status === 'approved') {
        return allFilled.value ? 'Согласован и готов к внедрению' : 'Согласован, но план ещё неполный';
    }

    if (props.status === 'pending_approval') {
        return allFilled.value ? 'На согласовании' : 'На согласовании — план неполный';
    }

    if (allFilled.value) {
        return 'План заполнен — можно отправлять на согласование';
    }

    if (missingLabels.value.length === 0) {
        return 'Заполните блоки плана';
    }

    return `Не хватает: ${missingLabels.value.join(', ')}`;
});

const progressPercent = computed(() => (
    total.value === 0 ? 0 : Math.round((filledCount.value / total.value) * 100)
));
</script>

<template>
    <div class="min-w-0 space-y-2">
        <div class="flex flex-wrap items-center gap-2">
            <ProjectStatusBadge :status="status" :label="statusLabel" />
            <span class="text-xs font-semibold tabular-nums text-f5-text dark:text-white">
                {{ filledCount }}/{{ total }}
            </span>
            <span class="text-xs text-f5-text-muted dark:text-white/50">
                {{ hint }}
            </span>
        </div>

        <div
            class="flex h-1.5 overflow-hidden rounded-full bg-f5-surface-2 dark:bg-white/10"
            role="progressbar"
            :aria-valuenow="filledCount"
            :aria-valuemin="0"
            :aria-valuemax="total"
            :aria-label="`Готовность плана ${filledCount} из ${total}`"
        >
            <div
                class="h-full rounded-full transition-all duration-300"
                :class="allFilled ? 'bg-f5-green' : 'bg-f5-yellow'"
                :style="{ width: `${progressPercent}%` }"
            />
        </div>

        <div v-if="showBlocks" class="flex flex-wrap gap-1">
            <button
                v-for="block in blocks"
                :key="block.id"
                type="button"
                class="inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide transition-colors"
                :class="block.filled
                    ? 'border-f5-green/35 bg-f5-green/10 text-f5-text dark:text-white'
                    : 'border-f5-border bg-transparent text-f5-text-muted hover:border-f5-text-muted/40 dark:border-white/10 dark:text-white/45 dark:hover:border-white/25'"
                :title="block.filled ? `${block.label}: заполнено` : `${block.label}: пусто`"
                @click="emit('jump', block.id)"
            >
                <span
                    class="h-1.5 w-1.5 rounded-full"
                    :class="block.filled ? 'bg-f5-green' : 'bg-f5-border dark:bg-white/25'"
                    aria-hidden="true"
                />
                {{ block.label }}
                <span
                    v-if="block.count !== null && block.count !== undefined"
                    class="tabular-nums opacity-70"
                >{{ block.count }}</span>
            </button>
        </div>
    </div>
</template>
