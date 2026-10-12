<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import ProjectStatusBadge from './Projects/ProjectStatusBadge.vue';
import { confirmDialog } from '../utils/confirmDialog';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
    staggerIndex: {
        type: Number,
        default: 0,
    },
});

const stats = computed(() => [
    { key: 'funnels', label: 'Воронки', count: Number(props.project.funnels_count) || 0 },
    { key: 'fields', label: 'Поля', count: Number(props.project.skk_fields_count) || 0 },
    { key: 'widgets', label: 'Виджеты', count: Number(props.project.integrations_count) || 0 },
    { key: 'stages', label: 'Этапы', count: Number(props.project.work_stages_count) || 0 },
]);

const filledStats = computed(() => stats.value.filter((item) => item.count > 0).length);

const progressLabel = computed(() => {
    if (props.project.is_deleted) {
        return 'Удалён';
    }

    if (filledStats.value === 0) {
        return 'Пустой черновик';
    }

    if (filledStats.value >= 3) {
        return 'Хорошо заполнен';
    }

    return 'В работе';
});

async function restoreProject() {
    if (!props.project.restore_url) {
        return;
    }

    const confirmed = await confirmDialog({
        title: 'Восстановить проект?',
        message: `Проект «${props.project.company_name}» снова появится в активных списках.`,
        confirmLabel: 'Восстановить',
        variant: 'primary',
    });

    if (!confirmed) {
        return;
    }

    router.post(props.project.restore_url);
}
</script>

<template>
    <component
        :is="project.url ? 'a' : 'div'"
        :href="project.url || undefined"
        class="f5-stagger-item group relative block overflow-hidden rounded-2xl border border-f5-border bg-f5-surface shadow-sm dark:border-white/10 dark:bg-f5-black"
        :class="[
            project.url ? 'f5-card-lift no-underline hover:border-f5-green/60 dark:hover:border-f5-green/50' : 'opacity-90',
        ]"
        :style="{ '--stagger-index': staggerIndex }"
    >
        <div
            class="f5-card-lift__accent pointer-events-none absolute inset-y-0 left-0 w-1 bg-gradient-to-b from-f5-green to-f5-yellow opacity-80"
            aria-hidden="true"
        />

        <div class="p-5 pl-6">
            <div class="mb-3 flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h3
                        class="truncate text-base font-semibold text-f5-text transition-colors duration-200 dark:text-white"
                        :class="project.url ? 'group-hover:text-f5-black dark:group-hover:text-f5-green' : ''"
                    >
                        {{ project.company_name }}
                    </h3>
                    <p
                        v-if="project.business_sphere"
                        class="mt-1 truncate text-xs font-medium text-f5-text-muted dark:text-white/45"
                    >
                        {{ project.business_sphere }}
                    </p>
                </div>
                <ProjectStatusBadge
                    :status="project.is_deleted ? 'archived' : project.status"
                    :label="project.is_deleted ? 'Удалён' : project.status_label"
                    compact
                />
            </div>

            <p
                v-if="project.client_tasks"
                class="mb-4 line-clamp-2 text-sm leading-relaxed text-f5-text-muted dark:text-white/50"
            >
                {{ project.client_tasks }}
            </p>
            <p
                v-else
                class="mb-4 text-sm text-f5-text-muted/70 dark:text-white/35"
            >
                {{ progressLabel }}
            </p>

            <div class="mb-4 grid grid-cols-4 gap-2">
                <div
                    v-for="stat in stats"
                    :key="stat.key"
                    class="f5-card-lift__stat rounded-lg px-1.5 py-2 text-center"
                    :class="stat.count > 0
                        ? 'bg-f5-green/10 dark:bg-f5-green/15'
                        : 'bg-f5-surface-2 dark:bg-white/5'"
                >
                    <p
                        class="text-sm font-semibold tabular-nums"
                        :class="stat.count > 0
                            ? 'text-f5-text dark:text-white'
                            : 'text-f5-text-muted dark:text-white/35'"
                    >
                        {{ stat.count }}
                    </p>
                    <p class="mt-0.5 truncate text-[10px] uppercase tracking-wide text-f5-text-muted dark:text-white/40">
                        {{ stat.label }}
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 text-xs text-f5-text-muted dark:text-white/40">
                <span
                    v-if="project.manager_name"
                    class="rounded-md bg-f5-green/15 px-2 py-0.5 font-medium text-f5-text dark:bg-f5-green/20 dark:text-white"
                >
                    {{ project.manager_name }}
                </span>
                <span v-if="project.access_label">
                    {{ project.access_label }}
                </span>
                <span v-if="project.employees_count">
                    {{ project.employees_count }} сотр.
                </span>
                <span class="ml-auto tabular-nums">{{ project.created_at }}</span>
            </div>

            <button
                v-if="project.is_deleted && project.restore_url"
                type="button"
                class="f5-btn f5-btn--primary f5-btn--sm mt-4"
                @click.stop.prevent="restoreProject"
            >
                Восстановить
            </button>
        </div>
    </component>
</template>
