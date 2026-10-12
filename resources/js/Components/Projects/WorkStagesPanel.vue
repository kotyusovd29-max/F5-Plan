<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import SortableList from './SortableList.vue';
import SortableBlock from './SortableBlock.vue';
import { confirmDialog } from '../../utils/confirmDialog';
import { cancelAiProcessingMessage } from '../../utils/cancelAiProcessing';
import { postFormData, postJson } from '../../utils/fetchJson';
import { pollAiProcessingMessage } from '../../utils/pollAiProcessing';
import SectionEmptyState from './SectionEmptyState.vue';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
    workStages: {
        type: Array,
        required: true,
    },
    storeUrl: {
        type: String,
        required: true,
    },
    reorderUrl: {
        type: String,
        required: true,
    },
    canEdit: {
        type: Boolean,
        default: true,
    },
});

const items = ref([...props.workStages]);
const showAddForm = ref(false);
const openId = ref(null);
const edits = reactive({});
const fileInput = ref(null);
const uploadingKp = ref(false);
const analyzingKp = ref(false);
const pollActive = ref(false);
const cancellingKp = ref(false);
const analyzingAssistantId = ref(null);
const kpStatus = ref('');
const kpStatusType = ref('');

defineExpose({
    openAdd: () => {
        if (!props.canEdit) {
            return;
        }

        showAddForm.value = true;
    },
});

watch(() => props.workStages, (value) => {
    items.value = [...value];
    value.forEach((item) => {
        edits[item.id] = {
            name: item.name ?? '',
            composition: item.composition ?? '',
            cost: item.cost ?? '',
            duration_days: item.duration_days ?? '',
        };
    });
}, { immediate: true, deep: true });

const addForm = useForm({
    name: '',
    composition: '',
    cost: '',
    duration_days: '',
});

const totalCost = computed(() => {
    return items.value.reduce((sum, item) => {
        const value = Number(item.cost);

        return Number.isFinite(value) ? sum + value : sum;
    }, 0);
});

const totalCostLabel = computed(() => {
    if (totalCost.value <= 0) {
        return null;
    }

    return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        maximumFractionDigits: totalCost.value % 1 === 0 ? 0 : 2,
    }).format(totalCost.value);
});

function openKpPicker() {
    fileInput.value?.click();
}

function formatElapsed(seconds) {
    const minutes = Math.floor(seconds / 60);
    const rest = seconds % 60;

    if (minutes === 0) {
        return `${rest} сек`;
    }

    return `${minutes} мин ${rest} сек`;
}

async function onKpFileSelected(event) {
    const file = event.target.files?.[0];

    if (!file) {
        return;
    }

    uploadingKp.value = true;
    analyzingKp.value = false;
    pollActive.value = true;
    kpStatus.value = 'Загружаю КП…';
    kpStatusType.value = 'info';

    try {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('kind', 'kp');

        const { response, data } = await postFormData(props.project.urls.brief_documents_store, formData);

        if (!response.ok || !data.success || !data.document?.generate_work_stages_url) {
            throw new Error(data.message || 'Не удалось загрузить КП.');
        }

        uploadingKp.value = false;
        analyzingKp.value = true;
        kpStatus.value = 'Запускаю анализ этапов работ…';

        const analyze = await postJson(data.document.generate_work_stages_url);

        if (!analyze.response.ok || !analyze.data.success) {
            throw new Error(analyze.data.message || 'Не удалось запустить анализ КП.');
        }

        const assistantMessage = (analyze.data.messages ?? []).find((item) => item.role === 'assistant');

        if (!assistantMessage?.id) {
            throw new Error('Не удалось получить идентификатор задачи ИИ.');
        }

        analyzingAssistantId.value = assistantMessage.id;
        const startedAt = Date.now();
        kpStatus.value = 'ИИ извлекает этапы работ из КП…';

        const result = await pollAiProcessingMessage(props.project, assistantMessage.id, {
            shouldContinue: () => pollActive.value,
            onProgress(message) {
                const elapsed = formatElapsed(Math.floor((Date.now() - startedAt) / 1000));
                const step = message?.content?.trim();

                kpStatus.value = step
                    ? `${step} (${elapsed})`
                    : `ИИ обрабатывает КП… (${elapsed})`;
            },
        });

        if (result.status === 'completed') {
            kpStatus.value = 'Этапы из КП готовы. Открываю чат — примените изменения.';
            kpStatusType.value = 'success';
            router.visit(props.project.urls.ai_chat, { preserveScroll: true });
            return;
        }

        if (result.status === 'cancelled') {
            kpStatus.value = result.message || 'Запрос отменён.';
            kpStatusType.value = '';
            return;
        }

        if (result.status === 'failed') {
            throw new Error(result.message || 'ИИ не смог разобрать КП.');
        }

        kpStatus.value = result.message;
        kpStatusType.value = result.status === 'timeout' ? 'info' : 'error';
    } catch (error) {
        kpStatus.value = error.message || 'Не удалось проанализировать КП.';
        kpStatusType.value = 'error';
    } finally {
        uploadingKp.value = false;
        analyzingKp.value = false;
        analyzingAssistantId.value = null;
        pollActive.value = false;
        cancellingKp.value = false;

        if (fileInput.value) {
            fileInput.value.value = '';
        }
    }
}

async function cancelKpAnalysis() {
    if (!analyzingAssistantId.value || cancellingKp.value) {
        return;
    }

    cancellingKp.value = true;
    pollActive.value = false;

    try {
        const data = await cancelAiProcessingMessage(props.project, analyzingAssistantId.value);
        kpStatus.value = data.message || 'Запрос отменён.';
        kpStatusType.value = '';
    } catch (error) {
        kpStatus.value = error.message || 'Не удалось отменить запрос.';
        kpStatusType.value = 'error';
    } finally {
        cancellingKp.value = false;
        analyzingKp.value = false;
        analyzingAssistantId.value = null;
    }
}

function submitAdd() {
    addForm.transform((data) => ({
        name: data.name,
        composition: data.composition || null,
        cost: data.cost === '' || data.cost === null ? null : data.cost,
        duration_days: data.duration_days === '' || data.duration_days === null ? null : data.duration_days,
    })).post(props.storeUrl, {
        preserveScroll: true,
        onSuccess: () => {
            addForm.reset();
            showAddForm.value = false;
        },
    });
}

function saveItem(item) {
    const edit = edits[item.id];

    router.put(item.update_url, {
        name: edit.name,
        composition: edit.composition || null,
        cost: edit.cost === '' || edit.cost === null ? null : edit.cost,
        duration_days: edit.duration_days === '' || edit.duration_days === null ? null : edit.duration_days,
    }, { preserveScroll: true });
}

async function deleteItem(item) {
    const confirmed = await confirmDialog({
        title: 'Удалить этап работ?',
        message: `«${item.name}» будет удалён без возможности восстановления.`,
    });

    if (!confirmed) {
        return;
    }

    router.delete(item.destroy_url, { preserveScroll: true });
}

function onDeleted(id) {
    items.value = items.value.filter((item) => item.id !== id);
    delete edits[id];
}
</script>

<template>
    <div>
        <div
            v-if="canEdit"
            class="border-b border-f5-border px-6 py-4 dark:border-white/10"
        >
            <div class="flex flex-wrap items-center gap-2">
                <input
                    ref="fileInput"
                    type="file"
                    class="hidden"
                    accept=".pdf,.docx,.xlsx,.xls,.txt,.md,.rtf,.csv,application/pdf"
                    @change="onKpFileSelected"
                >
                <button
                    type="button"
                    class="f5-btn f5-btn--primary f5-btn--sm"
                    :disabled="uploadingKp || analyzingKp"
                    @click="openKpPicker"
                >
                    {{ uploadingKp ? 'Загрузка…' : analyzingKp ? 'Анализ КП…' : 'Заполнить из КП (PDF)' }}
                </button>
                <button
                    v-if="analyzingKp"
                    type="button"
                    class="f5-btn f5-btn--ghost f5-btn--sm"
                    :disabled="cancellingKp"
                    @click="cancelKpAnalysis"
                >
                    {{ cancellingKp ? 'Отмена…' : 'Остановить' }}
                </button>
            </div>
            <p class="mt-2 text-xs text-f5-text-muted dark:text-white/45">
                Загрузите коммерческое предложение — ИИ найдёт этапы работ и подготовит их к применению в чате.
            </p>
            <p
                v-if="kpStatus"
                class="mt-2 text-sm"
                :class="{
                    'text-f5-text-muted dark:text-white/55': kpStatusType === 'info' || !kpStatusType,
                    'text-green-700 dark:text-green-400': kpStatusType === 'success',
                    'text-red-600 dark:text-red-400': kpStatusType === 'error',
                }"
            >
                {{ kpStatus }}
            </p>
        </div>

        <div
            v-if="totalCostLabel"
            class="border-b border-f5-border px-6 py-3 text-sm dark:border-white/10"
        >
            <span class="text-f5-text-muted dark:text-white/50">Итого по этапам:</span>
            <span class="ml-2 font-semibold text-f5-text dark:text-white">{{ totalCostLabel }}</span>
        </div>

        <div v-if="canEdit && showAddForm" class="border-b border-f5-border p-5 dark:border-white/10">
            <form class="mx-auto max-w-3xl space-y-4" @submit.prevent="submitAdd">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-f5-text dark:text-white">Название этапа</label>
                    <input
                        v-model="addForm.name"
                        type="text"
                        class="f5-input"
                        placeholder="Например: Аналитика и проектирование"
                        required
                    >
                    <p v-if="addForm.errors.name" class="mt-1 text-sm text-red-600">{{ addForm.errors.name }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-f5-text dark:text-white">Состав работ</label>
                    <textarea
                        v-model="addForm.composition"
                        class="f5-input min-h-24"
                        rows="4"
                        placeholder="Что входит в этап"
                    />
                    <p v-if="addForm.errors.composition" class="mt-1 text-sm text-red-600">{{ addForm.errors.composition }}</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-f5-text dark:text-white">Срок реализации, раб. дн.</label>
                        <input
                            v-model="addForm.duration_days"
                            type="number"
                            min="1"
                            step="1"
                            class="f5-input"
                            placeholder="Например: 10"
                        >
                        <p v-if="addForm.errors.duration_days" class="mt-1 text-sm text-red-600">{{ addForm.errors.duration_days }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-f5-text dark:text-white">Стоимость этапа, ₽</label>
                        <input
                            v-model="addForm.cost"
                            type="number"
                            min="0"
                            step="0.01"
                            class="f5-input"
                            placeholder="0"
                        >
                        <p v-if="addForm.errors.cost" class="mt-1 text-sm text-red-600">{{ addForm.errors.cost }}</p>
                    </div>
                </div>
                <button type="submit" class="f5-btn f5-btn--primary" :disabled="addForm.processing">
                    Добавить
                </button>
            </form>
        </div>

        <SectionEmptyState
            v-if="items.length === 0 && !showAddForm"
            title="Этапы работ пока не добавлены"
            description="Разбейте внедрение на этапы с составом, стоимостью и сроком."
            :action-label="canEdit ? 'Добавить этап' : ''"
            @action="showAddForm = true"
        >
            <template #icon>
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6">
                    <path d="M4 5.5h12M4 10h9M4 14.5h7" stroke-linecap="round" />
                    <circle cx="15.5" cy="10" r="1.2" fill="currentColor" stroke="none" />
                </svg>
            </template>
        </SectionEmptyState>

        <SortableList
            v-else-if="canEdit && items.length > 0"
            :reorder-url="reorderUrl"
            drag-delete
            class="border-t border-f5-border dark:border-white/10"
            @deleted="onDeleted"
        >
            <SortableBlock
                v-for="item in items"
                :key="item.id"
                :id="item.id"
                :name="item.name"
                :summary="item.summary"
                :destroy-url="item.destroy_url"
                :open="openId === item.id"
                @toggle="openId = openId === item.id ? null : item.id"
            >
                <form class="space-y-4 pt-4" @submit.prevent="saveItem(item)">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-f5-text dark:text-white">Название этапа</label>
                        <input
                            v-model="edits[item.id].name"
                            type="text"
                            class="f5-input"
                            required
                        >
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-f5-text dark:text-white">Состав работ</label>
                        <textarea
                            v-model="edits[item.id].composition"
                            class="f5-input min-h-24"
                            rows="4"
                        />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-f5-text dark:text-white">Срок реализации, раб. дн.</label>
                            <input
                                v-model="edits[item.id].duration_days"
                                type="number"
                                min="1"
                                step="1"
                                class="f5-input"
                            >
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-f5-text dark:text-white">Стоимость этапа, ₽</label>
                            <input
                                v-model="edits[item.id].cost"
                                type="number"
                                min="0"
                                step="0.01"
                                class="f5-input"
                            >
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="f5-btn f5-btn--primary f5-btn--sm">
                            Сохранить
                        </button>
                        <button type="button" class="f5-btn f5-btn--danger f5-btn--sm" @click="deleteItem(item)">
                            Удалить
                        </button>
                    </div>
                </form>
            </SortableBlock>
        </SortableList>

        <ul
            v-else-if="items.length > 0"
            class="divide-y divide-f5-border border-t border-f5-border dark:divide-white/10 dark:border-white/10"
        >
            <li
                v-for="item in items"
                :key="item.id"
                class="px-6 py-3.5 text-sm"
            >
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <span class="font-semibold text-f5-text dark:text-white">{{ item.name }}</span>
                    <div class="flex shrink-0 flex-wrap items-center gap-x-3 gap-y-1 text-sm font-medium text-f5-text dark:text-white">
                        <span v-if="item.duration_label">{{ item.duration_label }}</span>
                        <span v-if="item.cost_label">{{ item.cost_label }}</span>
                    </div>
                </div>
                <ol
                    v-if="(item.composition_items?.length || 0) > 0"
                    class="mt-2 list-decimal space-y-1 pl-5 text-xs leading-relaxed text-f5-text-muted dark:text-white/55"
                >
                    <li
                        v-for="(point, pointIndex) in item.composition_items"
                        :key="`${item.id}-${pointIndex}`"
                    >
                        {{ point }}
                    </li>
                </ol>
                <p
                    v-else-if="item.composition"
                    class="mt-1 whitespace-pre-line text-xs text-f5-text-muted dark:text-white/50"
                >{{ item.composition }}</p>
            </li>
        </ul>
    </div>
</template>
