<script setup>
import { computed, ref, watch } from 'vue';
import { getJson, postJson } from '../../utils/fetchJson';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    urls: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['close', 'imported']);

const loading = ref(false);
const importing = ref(false);
const error = ref('');
const pipelines = ref([]);
const fieldsSummary = ref(null);
const selectedIds = ref([]);
const includeUnsorted = ref(false);
const includeSystem = ref(false);
const includeFields = ref(true);

const allSelected = computed(() => (
    pipelines.value.length > 0
    && selectedIds.value.length === pipelines.value.length
));

const someSelected = computed(() => (
    selectedIds.value.length > 0 && !allSelected.value
));

const selectedCount = computed(() => selectedIds.value.length);

watch(
    () => props.open,
    async (value) => {
        if (!value) {
            return;
        }

        error.value = '';
        includeUnsorted.value = false;
        includeSystem.value = false;
        includeFields.value = true;
        fieldsSummary.value = null;
        await loadPipelines();
    },
);

async function loadPipelines() {
    if (!props.urls?.pipelines) {
        error.value = 'URL списка воронок не задан.';
        return;
    }

    loading.value = true;
    error.value = '';

    try {
        const { response, data } = await getJson(props.urls.pipelines);

        if (!response.ok) {
            throw new Error(data?.message || 'Не удалось загрузить воронки из amoCRM.');
        }

        pipelines.value = Array.isArray(data.pipelines) ? data.pipelines : [];
        fieldsSummary.value = data.fields ?? null;
        selectedIds.value = pipelines.value.map((item) => item.id);
    } catch (exception) {
        pipelines.value = [];
        fieldsSummary.value = null;
        selectedIds.value = [];
        error.value = exception?.message || 'Не удалось загрузить воронки из amoCRM.';
    } finally {
        loading.value = false;
    }
}

function onSelectAllChange(event) {
    if (event.target.checked) {
        selectedIds.value = pipelines.value.map((item) => item.id);
        return;
    }

    selectedIds.value = [];
}

function togglePipeline(id) {
    if (selectedIds.value.includes(id)) {
        selectedIds.value = selectedIds.value.filter((item) => item !== id);
        return;
    }

    selectedIds.value = [...selectedIds.value, id];
}

function close() {
    if (importing.value) {
        return;
    }

    emit('close');
}

const canImport = computed(() => (
    !loading.value
    && !importing.value
    && (selectedCount.value > 0 || includeFields.value)
));

async function importSelected() {
    if (!canImport.value) {
        return;
    }

    importing.value = true;
    error.value = '';

    try {
        const { response, data } = await postJson(props.urls.import_pipelines, {
            pipeline_ids: selectedIds.value,
            include_unsorted: includeUnsorted.value,
            include_system: includeSystem.value,
            include_fields: includeFields.value,
        });

        if (!response.ok || !data?.success) {
            throw new Error(data?.message || 'Не удалось выполнить импорт.');
        }

        emit('imported', data);
        emit('close');
    } catch (exception) {
        error.value = exception?.message || 'Не удалось выполнить импорт.';
    } finally {
        importing.value = false;
    }
}

function fieldsHint() {
    const summary = fieldsSummary.value;

    if (!summary) {
        return '';
    }

    const byEntity = summary.by_entity || {};
    const parts = [];

    if (byEntity.deals) {
        parts.push(`сделки: ${byEntity.deals}`);
    }
    if (byEntity.contacts) {
        parts.push(`контакты: ${byEntity.contacts}`);
    }
    if (byEntity.companies) {
        parts.push(`компании: ${byEntity.companies}`);
    }

    let text = `${summary.importable || 0} полей`;

    if (parts.length) {
        text += ` (${parts.join(', ')})`;
    }

    if (summary.already_imported > 0) {
        text += ` · уже в проекте: ${summary.already_imported}`;
    }

    return text;
}

function stageHint(pipeline) {
    const parts = [`${pipeline.stages_regular} обычн.`];

    if (pipeline.stages_unsorted > 0) {
        parts.push(`${pipeline.stages_unsorted} неразб.`);
    }

    if (pipeline.stages_system > 0) {
        parts.push(`${pipeline.stages_system} системн.`);
    }

    return parts.join(' · ');
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/55 p-3 backdrop-blur-sm sm:p-5"
            @click.self="close"
        >
            <div
                class="flex max-h-[min(90vh,720px)] w-full max-w-xl flex-col overflow-hidden rounded-2xl border border-f5-border bg-f5-surface shadow-2xl dark:border-white/10 dark:bg-f5-black"
                role="dialog"
                aria-modal="true"
                aria-labelledby="amocrm-import-title"
            >
                <div class="shrink-0 border-b border-f5-border px-5 py-4 dark:border-white/10">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2
                                id="amocrm-import-title"
                                class="text-base font-semibold text-f5-text dark:text-white"
                            >
                                Импорт из amoCRM
                            </h2>
                            <p class="mt-0.5 text-xs text-f5-text-muted dark:text-white/50">
                                Воронки с этапами и поля СКК появятся в проекте с привязкой ID
                            </p>
                        </div>
                        <button
                            type="button"
                            class="f5-btn f5-btn--ghost f5-btn--sm shrink-0"
                            :disabled="importing"
                            @click="close"
                        >
                            Закрыть
                        </button>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                    <p
                        v-if="error"
                        class="mb-4 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300"
                    >
                        {{ error }}
                    </p>

                    <div
                        v-if="loading"
                        class="py-10 text-center text-sm text-f5-text-muted dark:text-white/50"
                    >
                        Загружаю данные из amoCRM…
                    </div>

                    <template v-else>
                        <template v-if="pipelines.length">
                            <div class="mb-3 flex items-center justify-between gap-3 rounded-xl border border-f5-border bg-f5-surface-2/80 px-3 py-2.5 dark:border-white/10 dark:bg-white/5">
                                <label class="flex cursor-pointer items-center gap-2.5 text-sm font-medium text-f5-text dark:text-white">
                                    <input
                                        type="checkbox"
                                        class="h-4 w-4"
                                        :checked="allSelected"
                                        :indeterminate="someSelected"
                                        @change="onSelectAllChange"
                                    >
                                    <span>Выбрать все</span>
                                </label>
                                <span class="text-xs text-f5-text-muted dark:text-white/45">
                                    {{ selectedCount }} из {{ pipelines.length }}
                                </span>
                            </div>

                            <ul class="mb-4 space-y-2">
                                <li
                                    v-for="pipeline in pipelines"
                                    :key="pipeline.id"
                                >
                                    <label
                                        class="flex cursor-pointer items-start gap-3 rounded-xl border px-3 py-3 transition"
                                        :class="selectedIds.includes(pipeline.id)
                                            ? 'border-f5-green/50 bg-f5-green/10 dark:border-f5-green/40 dark:bg-f5-green/15'
                                            : 'border-f5-border bg-f5-surface-2 hover:border-f5-green/35 dark:border-white/10 dark:bg-white/5'"
                                    >
                                        <input
                                            type="checkbox"
                                            class="mt-1 h-4 w-4"
                                            :checked="selectedIds.includes(pipeline.id)"
                                            @change="togglePipeline(pipeline.id)"
                                        >
                                        <span class="min-w-0 flex-1">
                                            <span class="flex flex-wrap items-center gap-2">
                                                <span class="text-sm font-medium text-f5-text dark:text-white">
                                                    {{ pipeline.name }}
                                                </span>
                                                <span
                                                    v-if="pipeline.is_main"
                                                    class="rounded-full bg-f5-surface px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-f5-text-muted dark:bg-white/10 dark:text-white/55"
                                                >
                                                    основная
                                                </span>
                                                <span
                                                    v-if="pipeline.already_imported"
                                                    class="rounded-full bg-f5-yellow/20 px-2 py-0.5 text-[10px] font-semibold text-f5-text dark:bg-f5-yellow/25 dark:text-white"
                                                >
                                                    обновить
                                                </span>
                                            </span>
                                            <span class="mt-0.5 block text-xs text-f5-text-muted dark:text-white/50">
                                                {{ stageHint(pipeline) }}
                                            </span>
                                        </span>
                                    </label>
                                </li>
                            </ul>
                        </template>
                        <p
                            v-else
                            class="mb-4 py-6 text-center text-sm text-f5-text-muted dark:text-white/50"
                        >
                            В аккаунте amoCRM воронок не найдено — можно импортировать только поля СКК.
                        </p>

                        <div class="space-y-2 rounded-xl border border-f5-border bg-f5-surface-2/70 p-3 dark:border-white/10 dark:bg-white/5">
                            <p class="text-xs font-medium text-f5-text dark:text-white">
                                Дополнительно
                            </p>
                            <label class="flex cursor-pointer items-start gap-2.5 text-sm text-f5-text dark:text-white/85">
                                <input v-model="includeFields" type="checkbox" class="mt-0.5 h-4 w-4">
                                <span>
                                    Поля СКК
                                    <span class="block text-xs text-f5-text-muted dark:text-white/45">
                                        {{ fieldsHint() || 'Импортировать кастомные поля сделок, контактов и компаний' }}
                                    </span>
                                </span>
                            </label>
                            <label
                                v-if="pipelines.length"
                                class="flex cursor-pointer items-start gap-2.5 text-sm text-f5-text dark:text-white/85"
                            >
                                <input v-model="includeUnsorted" type="checkbox" class="mt-0.5 h-4 w-4">
                                <span>
                                    Неразобранное
                                    <span class="block text-xs text-f5-text-muted dark:text-white/45">
                                        Импортировать этап «Неразобранное»
                                    </span>
                                </span>
                            </label>
                            <label
                                v-if="pipelines.length"
                                class="flex cursor-pointer items-start gap-2.5 text-sm text-f5-text dark:text-white/85"
                            >
                                <input v-model="includeSystem" type="checkbox" class="mt-0.5 h-4 w-4">
                                <span>
                                    Системные статусы
                                    <span class="block text-xs text-f5-text-muted dark:text-white/45">
                                        «Успешно реализовано» и «Закрыто и не реализовано»
                                    </span>
                                </span>
                            </label>
                        </div>
                    </template>
                </div>

                <div class="shrink-0 flex items-center justify-end gap-2 border-t border-f5-border px-5 py-4 dark:border-white/10">
                    <button
                        type="button"
                        class="f5-btn f5-btn--ghost f5-btn--sm"
                        :disabled="importing"
                        @click="close"
                    >
                        Позже
                    </button>
                    <button
                        type="button"
                        class="f5-btn f5-btn--primary f5-btn--sm"
                        :disabled="!canImport"
                        @click="importSelected"
                    >
                        {{ importing
                            ? 'Импорт…'
                            : (selectedCount > 0
                                ? `Импортировать (${selectedCount}${includeFields ? ' + поля' : ''})`
                                : 'Импортировать поля') }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
