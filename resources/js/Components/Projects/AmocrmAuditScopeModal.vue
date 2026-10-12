<script setup>
import { computed, ref, watch } from 'vue';
import { getJson } from '../../utils/fetchJson';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    urls: {
        type: Object,
        required: true,
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'confirm']);

const loadingList = ref(false);
const error = ref('');
const pipelines = ref([]);
const fieldsSummary = ref(null);
const selectedIds = ref([]);
const includeFields = ref(true);

const allSelected = computed(() => (
    pipelines.value.length > 0
    && selectedIds.value.length === pipelines.value.length
));

const someSelected = computed(() => (
    selectedIds.value.length > 0 && !allSelected.value
));

const selectedCount = computed(() => selectedIds.value.length);

const canConfirm = computed(() => (
    !loadingList.value
    && !props.loading
    && selectedCount.value > 0
));

watch(
    () => props.open,
    async (value) => {
        if (!value) {
            return;
        }

        error.value = '';
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

    loadingList.value = true;
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
        loadingList.value = false;
    }
}

function onSelectAllChange(event) {
    selectedIds.value = event.target.checked
        ? pipelines.value.map((item) => item.id)
        : [];
}

function togglePipeline(id) {
    if (selectedIds.value.includes(id)) {
        selectedIds.value = selectedIds.value.filter((item) => item !== id);
        return;
    }

    selectedIds.value = [...selectedIds.value, id];
}

function close() {
    if (props.loading) {
        return;
    }

    emit('close');
}

function confirm() {
    if (!canConfirm.value) {
        return;
    }

    emit('confirm', {
        pipeline_ids: selectedIds.value,
        include_fields: includeFields.value,
    });
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

function fieldsHint() {
    const summary = fieldsSummary.value;

    if (!summary) {
        return 'Кастомные поля сделок, контактов и компаний';
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

    return parts.length
        ? `${summary.importable || 0} полей (${parts.join(', ')})`
        : `${summary.importable || 0} полей`;
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
                aria-labelledby="amocrm-audit-scope-title"
            >
                <div class="shrink-0 border-b border-f5-border px-5 py-4 dark:border-white/10">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2
                                id="amocrm-audit-scope-title"
                                class="text-base font-semibold text-f5-text dark:text-white"
                            >
                                AI-аудит amoCRM
                            </h2>
                            <p class="mt-0.5 text-xs text-f5-text-muted dark:text-white/50">
                                Выберите воронки и поля, которые отправить в ИИ для анализа
                            </p>
                        </div>
                        <button
                            type="button"
                            class="f5-btn f5-btn--ghost f5-btn--sm shrink-0"
                            :disabled="loading"
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
                        v-if="loadingList"
                        class="py-10 text-center text-sm text-f5-text-muted dark:text-white/50"
                    >
                        Загружаю воронки из amoCRM…
                    </div>

                    <template v-else-if="pipelines.length">
                        <div class="mb-3 flex items-center justify-between gap-3 rounded-xl border border-f5-border bg-f5-surface-2/80 px-3 py-2.5 dark:border-white/10 dark:bg-white/5">
                            <label class="flex cursor-pointer items-center gap-2.5 text-sm font-medium text-f5-text dark:text-white">
                                <input
                                    type="checkbox"
                                    class="h-4 w-4"
                                    :checked="allSelected"
                                    :indeterminate="someSelected"
                                    :disabled="loading"
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
                                        :disabled="loading"
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
                                        </span>
                                        <span class="mt-0.5 block text-xs text-f5-text-muted dark:text-white/50">
                                            {{ stageHint(pipeline) }}
                                        </span>
                                    </span>
                                </label>
                            </li>
                        </ul>

                        <div class="space-y-2 rounded-xl border border-f5-border bg-f5-surface-2/70 p-3 dark:border-white/10 dark:bg-white/5">
                            <label class="flex cursor-pointer items-start gap-2.5 text-sm text-f5-text dark:text-white/85">
                                <input
                                    v-model="includeFields"
                                    type="checkbox"
                                    class="mt-0.5 h-4 w-4"
                                    :disabled="loading"
                                >
                                <span>
                                    Поля СКК
                                    <span class="block text-xs text-f5-text-muted dark:text-white/45">
                                        {{ fieldsHint() }}
                                    </span>
                                </span>
                            </label>
                        </div>
                    </template>

                    <p
                        v-else
                        class="py-10 text-center text-sm text-f5-text-muted dark:text-white/50"
                    >
                        В аккаунте amoCRM воронок не найдено.
                    </p>
                </div>

                <div class="shrink-0 flex items-center justify-end gap-2 border-t border-f5-border px-5 py-4 dark:border-white/10">
                    <button
                        type="button"
                        class="f5-btn f5-btn--ghost f5-btn--sm"
                        :disabled="loading"
                        @click="close"
                    >
                        Отмена
                    </button>
                    <button
                        type="button"
                        class="f5-btn f5-btn--primary f5-btn--sm"
                        :disabled="!canConfirm"
                        @click="confirm"
                    >
                        {{ loading
                            ? 'Анализ…'
                            : `Запустить аудит (${selectedCount}${includeFields ? ' + поля' : ''})` }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
