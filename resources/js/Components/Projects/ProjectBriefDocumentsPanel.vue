<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { confirmDialog } from '../../utils/confirmDialog';
import { cancelAiProcessingMessage } from '../../utils/cancelAiProcessing';
import { imageFileFromPasteEvent } from '../../utils/clipboardImage';
import { deleteJson, postFormData, postJson } from '../../utils/fetchJson';
import { pollAiProcessingMessage } from '../../utils/pollAiProcessing';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
    documents: {
        type: Array,
        default: () => [],
    },
    compact: {
        type: Boolean,
        default: false,
    },
    canEdit: {
        type: Boolean,
        default: true,
    },
});

const items = ref([...props.documents]);
const uploading = ref(false);
const generatingId = ref(null);
const generatingAssistantId = ref(null);
const pollActive = ref(false);
const cancellingGeneration = ref(false);
const status = ref('');
const statusType = ref('');
const fileInput = ref(null);

const acceptedTypes = '.pdf,.docx,.xlsx,.xls,.txt,.md,.rtf,.csv,.jpg,.jpeg,.png,.webp,.gif,image/*';

function openFilePicker() {
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

async function uploadFile(file) {
    if (!file || !props.canEdit || uploading.value || generatingId.value !== null) {
        return;
    }

    uploading.value = true;
    status.value = '';
    statusType.value = '';

    try {
        const formData = new FormData();
        formData.append('file', file);

        const { response, data } = await postFormData(props.project.urls.brief_documents_store, formData);

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Не удалось загрузить документ.');
        }

        items.value = [data.document, ...items.value];
        status.value = data.message || 'Документ загружен.';
        statusType.value = 'success';
    } catch (error) {
        status.value = error.message || 'Не удалось загрузить документ.';
        statusType.value = 'error';
    } finally {
        uploading.value = false;
        if (fileInput.value) {
            fileInput.value.value = '';
        }
    }
}

async function onFileSelected(event) {
    const file = event.target.files?.[0];
    event.target.value = '';

    if (!file) {
        return;
    }

    await uploadFile(file);
}

async function onPaste(event) {
    if (!props.canEdit || uploading.value || generatingId.value !== null) {
        return;
    }

    const file = imageFileFromPasteEvent(event);

    if (!file) {
        return;
    }

    event.preventDefault();
    await uploadFile(file);
}

async function removeDocument(document) {
    const confirmed = await confirmDialog({
        title: 'Удалить документ?',
        message: `«${document.original_name}» будет удалён без возможности восстановления.`,
    });

    if (!confirmed) {
        return;
    }

    try {
        const { response, data } = await deleteJson(document.destroy_url);

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Не удалось удалить документ.');
        }

        items.value = items.value.filter((item) => item.id !== document.id);
        status.value = data.message || 'Документ удалён.';
        statusType.value = 'success';
    } catch (error) {
        status.value = error.message || 'Не удалось удалить документ.';
        statusType.value = 'error';
    }
}

async function generatePlan(document) {
    generatingId.value = document.id;
    pollActive.value = true;
    status.value = 'Запускаю анализ ТЗ…';
    statusType.value = 'info';

    try {
        const { response, data } = await postJson(document.generate_plan_url);

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Не удалось сформировать план по ТЗ.');
        }

        const assistantMessage = (data.messages ?? []).find((item) => item.role === 'assistant');

        if (!assistantMessage?.id) {
            throw new Error('Не удалось получить идентификатор задачи ИИ.');
        }

        generatingAssistantId.value = assistantMessage.id;
        const startedAt = Date.now();
        status.value = 'ИИ анализирует ТЗ. Обычно это занимает 3–10 минут…';
        statusType.value = 'info';

        const result = await pollAiProcessingMessage(props.project, assistantMessage.id, {
            shouldContinue: () => pollActive.value,
            onProgress(message) {
                const elapsed = formatElapsed(Math.floor((Date.now() - startedAt) / 1000));
                const step = message?.content?.trim();

                status.value = step
                    ? `${step} (${elapsed})`
                    : `ИИ обрабатывает документ… (${elapsed})`;
            },
        });

        if (result.status === 'completed') {
            status.value = 'План по ТЗ готов. Открываю чат с результатом…';
            statusType.value = 'success';
            router.visit(props.project.urls.ai_chat, { preserveScroll: true });
            return;
        }

        if (result.status === 'cancelled') {
            status.value = result.message || 'Запрос отменён.';
            statusType.value = '';
            return;
        }

        if (result.status === 'failed') {
            throw new Error(result.message || 'ИИ не смог обработать документ ТЗ.');
        }

        status.value = result.message;
        statusType.value = result.status === 'timeout' ? 'info' : 'error';
    } catch (error) {
        status.value = error.message || 'Не удалось сформировать план по ТЗ.';
        statusType.value = 'error';
    } finally {
        generatingId.value = null;
        generatingAssistantId.value = null;
        pollActive.value = false;
        cancellingGeneration.value = false;
    }
}

async function cancelGeneration() {
    if (!generatingAssistantId.value || cancellingGeneration.value) {
        return;
    }

    cancellingGeneration.value = true;
    pollActive.value = false;

    try {
        const data = await cancelAiProcessingMessage(props.project, generatingAssistantId.value);
        status.value = data.message || 'Запрос отменён.';
        statusType.value = '';
    } catch (error) {
        status.value = error.message || 'Не удалось отменить запрос.';
        statusType.value = 'error';
    } finally {
        generatingId.value = null;
        generatingAssistantId.value = null;
        cancellingGeneration.value = false;
    }
}
</script>

<template>
    <div
        class="rounded-xl border border-f5-border bg-f5-surface-2 outline-none dark:border-white/10 dark:bg-white/5"
        :class="compact ? 'mt-4' : 'mt-4'"
        tabindex="0"
        @paste="onPaste"
    >
        <div class="flex items-start justify-between gap-3 p-3">
            <div class="min-w-0">
                <h3 class="text-sm font-semibold text-f5-text dark:text-white">
                    Документы ТЗ
                </h3>
                <p class="mt-0.5 text-xs text-f5-text-muted dark:text-white/50">
                    Загрузите ТЗ или вставьте скриншот (Ctrl+V) — ИИ проанализирует и подготовит план
                </p>
            </div>

            <button
                v-if="canEdit"
                type="button"
                class="f5-btn f5-btn--primary f5-btn--sm shrink-0"
                :disabled="uploading || generatingId !== null"
                title="Загрузить файл или вставить скриншот (Ctrl+V)"
                @click="openFilePicker"
            >
                {{ uploading ? 'Загрузка…' : 'Загрузить' }}
            </button>

            <input
                v-if="canEdit"
                ref="fileInput"
                type="file"
                class="hidden"
                :accept="acceptedTypes"
                @change="onFileSelected"
            >
        </div>

        <p
            v-if="status"
            class="border-t border-f5-border px-3 py-2 text-xs dark:border-white/10"
            :class="statusType === 'error'
                ? 'text-red-600 dark:text-red-400'
                : statusType === 'success'
                    ? 'text-emerald-600 dark:text-emerald-400'
                    : 'text-f5-text-muted dark:text-white/60'"
        >
            {{ status }}
        </p>

        <div
            v-if="generatingId !== null"
            class="border-t border-f5-border px-3 py-2 dark:border-white/10"
        >
            <button
                type="button"
                class="f5-btn f5-btn--ghost f5-btn--sm"
                :disabled="cancellingGeneration"
                @click="cancelGeneration"
            >
                {{ cancellingGeneration ? 'Отмена…' : 'Остановить анализ ТЗ' }}
            </button>
        </div>

        <p
            v-if="items.length === 0"
            class="border-t border-f5-border px-3 py-3 text-xs text-f5-text-muted dark:border-white/10 dark:text-white/50"
        >
            PDF, DOCX, XLSX, TXT, MD, RTF, CSV и изображения · Ctrl+V — скриншот · до 15 МБ.
        </p>

        <ul
            v-else
            class="divide-y divide-f5-border border-t border-f5-border dark:divide-white/10 dark:border-white/10"
        >
            <li
                v-for="document in items"
                :key="document.id"
                class="flex flex-col gap-2 px-3 py-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-f5-text dark:text-white">
                        {{ document.original_name }}
                    </p>
                    <p class="mt-0.5 text-xs text-f5-text-muted dark:text-white/50">
                        {{ document.size_label }}
                        <template v-if="document.char_count">
                            · {{ document.char_count.toLocaleString('ru-RU') }} симв.
                        </template>
                        <template v-if="document.created_at">
                            · {{ document.created_at }}
                        </template>
                    </p>
                </div>

                <div v-if="canEdit" class="flex shrink-0 flex-wrap gap-2">
                    <button
                        type="button"
                        class="f5-btn f5-btn--primary f5-btn--sm"
                        :disabled="generatingId !== null"
                        @click="generatePlan(document)"
                    >
                        {{ generatingId === document.id ? 'Анализ ТЗ…' : 'План по ТЗ' }}
                    </button>
                    <button
                        type="button"
                        class="f5-btn f5-btn--danger f5-btn--sm"
                        :disabled="generatingId !== null"
                        @click="removeDocument(document)"
                    >
                        Удалить
                    </button>
                </div>
            </li>
        </ul>
    </div>
</template>
