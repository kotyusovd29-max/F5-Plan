<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { confirmDialog } from '../../utils/confirmDialog';
import ProjectPlanReadiness from './ProjectPlanReadiness.vue';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
    readinessBlocks: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['jump']);

const storageKey = computed(() => `f5plan:lifecycle-expanded:${props.project.id}`);

function readExpandedState() {
    try {
        const stored = localStorage.getItem(storageKey.value);

        if (stored === '1') {
            return true;
        }

        if (stored === '0') {
            return false;
        }
    } catch {
        // ignore
    }

    return false;
}

const expanded = ref(readExpandedState());

watch(expanded, (value) => {
    try {
        localStorage.setItem(storageKey.value, value ? '1' : '0');
    } catch {
        // ignore
    }
});

const canEdit = computed(() => props.project.can_edit !== false);
const canDelete = computed(() => props.project.can_delete === true);

const statusForm = useForm({
    status: props.project.status ?? 'draft',
});

watch(
    () => props.project.status,
    (status) => {
        statusForm.status = status ?? 'draft';
    },
);

function toggleExpanded() {
    expanded.value = !expanded.value;
}

function saveStatus() {
    if (!canEdit.value || statusForm.status === props.project.status) {
        return;
    }

    statusForm.put(props.project.urls.status_update, {
        preserveScroll: true,
    });
}

async function archiveProject() {
    if (!canEdit.value) {
        return;
    }

    const confirmed = await confirmDialog({
        title: 'Отправить в архив?',
        message: `Проект «${props.project.company_name}» будет скрыт из активных списков. Его можно вернуть, сменив статус.`,
        confirmLabel: 'В архив',
        variant: 'primary',
    });

    if (!confirmed) {
        return;
    }

    statusForm.status = 'archived';
    statusForm.put(props.project.urls.status_update, { preserveScroll: true });
}

async function unarchiveProject() {
    if (!canEdit.value) {
        return;
    }

    statusForm.status = 'draft';
    statusForm.put(props.project.urls.status_update, { preserveScroll: true });
}

async function deleteProject() {
    if (!canDelete.value) {
        return;
    }

    const confirmed = await confirmDialog({
        title: 'Удалить проект?',
        message: `Проект «${props.project.company_name}» будет перемещён в «Удалённые». Его можно восстановить.`,
        confirmLabel: 'Удалить',
        variant: 'danger',
    });

    if (!confirmed) {
        return;
    }

    router.delete(props.project.urls.destroy);
}

async function cloneProject() {
    if (!props.project.urls?.clone) {
        return;
    }

    const confirmed = await confirmDialog({
        title: 'Клонировать проект?',
        message: `Будет создана копия структуры «${props.project.company_name}»: воронки, поля СКК, виджеты и этапы работ. Данные клиента, AI и amoCRM не копируются.`,
        confirmLabel: 'Клонировать',
        variant: 'primary',
    });

    if (!confirmed) {
        return;
    }

    router.post(props.project.urls.clone);
}
</script>

<template>
    <section class="overflow-hidden rounded-2xl border border-f5-border bg-f5-surface shadow-sm dark:border-white/10 dark:bg-f5-black">
        <button
            type="button"
            class="flex w-full items-center gap-3 px-5 py-4 text-left transition-colors hover:bg-f5-surface-muted/60 dark:hover:bg-white/5"
            :aria-expanded="expanded"
            @click="toggleExpanded"
        >
            <span
                class="shrink-0 text-f5-text-muted transition-transform dark:text-white/40"
                :class="{ 'rotate-90': expanded }"
                aria-hidden="true"
            >▶</span>

            <span class="min-w-0 flex-1">
                <span class="block text-sm font-semibold text-f5-text dark:text-white">
                    Жизненный цикл
                </span>
                <span
                    v-if="!expanded && readinessBlocks.length"
                    class="mt-2 block"
                    @click.stop
                >
                    <ProjectPlanReadiness
                        :status="project.status"
                        :status-label="project.status_label"
                        :blocks="readinessBlocks"
                        :show-blocks="false"
                        @jump="emit('jump', $event)"
                    />
                </span>
            </span>

            <span class="shrink-0 text-xs text-f5-accent">
                {{ expanded ? 'Свернуть' : 'Развернуть' }}
            </span>
        </button>

        <div v-show="expanded" class="space-y-4 border-t border-f5-border px-5 py-4 dark:border-white/10">
            <ProjectPlanReadiness
                v-if="readinessBlocks.length"
                :status="project.status"
                :status-label="project.status_label"
                :blocks="readinessBlocks"
                @jump="emit('jump', $event)"
            />

            <div v-if="canEdit">
                <label for="project_status" class="f5-label">Изменить статус</label>
                <select
                    id="project_status"
                    v-model="statusForm.status"
                    class="f5-input"
                    :disabled="statusForm.processing"
                    @change="saveStatus"
                >
                    <option
                        v-for="option in project.status_options"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
                <p v-if="statusForm.errors.status" class="f5-error">{{ statusForm.errors.status }}</p>
            </div>

            <div class="flex flex-col gap-2">
                <button
                    v-if="project.urls?.clone"
                    type="button"
                    class="f5-btn f5-btn--primary w-full"
                    @click="cloneProject"
                >
                    Клонировать проект
                </button>
                <button
                    v-if="canEdit && !project.is_archived"
                    type="button"
                    class="f5-btn f5-btn--ghost w-full"
                    @click="archiveProject"
                >
                    В архив
                </button>
                <button
                    v-if="canEdit && project.is_archived"
                    type="button"
                    class="f5-btn f5-btn--primary w-full"
                    @click="unarchiveProject"
                >
                    Вернуть из архива
                </button>
                <button
                    v-if="canDelete"
                    type="button"
                    class="f5-btn f5-btn--danger w-full"
                    @click="deleteProject"
                >
                    Удалить проект
                </button>
            </div>
        </div>
    </section>
</template>
