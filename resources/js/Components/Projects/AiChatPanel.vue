<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { imageFileFromPasteEvent } from '../../utils/clipboardImage';
import AiChatMessage from './AiChatMessage.vue';
import AiModelSelect from './AiModelSelect.vue';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
    aiPrompts: {
        type: Object,
        default: () => ({ analysis: null, actions: [] }),
    },
    chat: {
        type: Object,
        required: true,
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

const textareaRef = ref(null);
const fileInputRef = ref(null);

const acceptedTypes = '.pdf,.docx,.xlsx,.xls,.txt,.md,.rtf,.csv,.jpg,.jpeg,.png,.webp,.gif,image/*';

const activeModeLabel = computed(() => (
    props.aiPrompts.actions.find((item) => item.mode === props.chat.sendMode.value)?.label
    ?? props.chat.sendMode.value
));

const hasProcessing = computed(() => (
    props.chat.messages.value.some((message) => (
        message.role === 'assistant' && message.status === 'processing'
    ))
));

const processingMessage = computed(() => props.chat.processingMessage?.value ?? null);

const isBusy = computed(() => props.chat.loading.value || hasProcessing.value || !!props.chat.attaching?.value);

const canSend = computed(() => {
    const hasText = props.chat.draft.value.trim().length >= 2;
    const hasFile = (props.chat.pendingAttachments?.value?.length ?? 0) > 0;

    return (hasText || hasFile) && !isBusy.value;
});

const canAttach = computed(() => (
    props.canEdit
    && !isBusy.value
    && !!props.project?.urls?.brief_documents_store
));

const isMeetingThread = computed(() => props.chat.isMeetingThread?.value ?? false);
const isAuditThread = computed(() => props.chat.isAuditThread?.value ?? false);
const isKpThread = computed(() => props.chat.isKpThread?.value ?? false);
const isConsultationThread = computed(() => (
    props.chat.isConsultationThread?.value ?? (isMeetingThread.value || isAuditThread.value || isKpThread.value)
));

const quickActions = computed(() => {
    if (isKpThread.value) {
        return [];
    }

    if (isAuditThread.value) {
        return props.aiPrompts.auditRecommendationActions ?? [];
    }

    if (isMeetingThread.value) {
        return props.aiPrompts.meetingActions ?? [];
    }

    return props.aiPrompts.actions ?? [];
});

const showProjectAnalysis = computed(() => (
    !isConsultationThread.value && props.aiPrompts.analysis
));

const emptyTitle = computed(() => {
    if (isKpThread.value) {
        return 'Генерация коммерческого предложения';
    }

    if (isAuditThread.value) {
        return 'Рекомендации по аудиту';
    }

    if (isMeetingThread.value) {
        return 'Первая встреча с клиентом';
    }

    return 'Чем помочь с проектом?';
});

const emptyDescription = computed(() => {
    if (isKpThread.value) {
        return 'Вставьте резюме встречи или опишите пожелания клиента — ИИ соберёт презентацию КП по шаблону F5 и вернёт ссылку на Google Slides';
    }

    if (isAuditThread.value) {
        return 'Вставьте текст записи встречи — ИИ подготовит документ рекомендаций клиенту по улучшению amoCRM. Ответ можно сохранить в DOCX';
    }

    if (isMeetingThread.value) {
        return 'ИИ поможет подготовить повестку, вопросы и сценарий первичной встречи по внедрению amoCRM';
    }

    return props.compact
        ? 'Опишите задачу — ИИ предложит правки по полям, воронкам и интеграциям'
        : 'Спросите про воронки, поля, интеграции или вставьте бриф клиента';
});

function resizeTextarea() {
    const element = textareaRef.value;

    if (!element) {
        return;
    }

    element.style.height = 'auto';
    const maxHeight = props.compact ? 120 : 200;
    element.style.height = `${Math.min(element.scrollHeight, maxHeight)}px`;
}

watch(() => props.chat.draft.value, () => {
    nextTick(resizeTextarea);
});

function onKeydown(event) {
    if (event.key !== 'Enter' || event.shiftKey) {
        return;
    }

    if (event.isComposing) {
        return;
    }

    event.preventDefault();

    if (canSend.value) {
        props.chat.sendMessage();
    }
}

function selectAction(action) {
    props.chat.useAction(action);
    nextTick(() => {
        resizeTextarea();
        textareaRef.value?.focus();
    });
}

function selectAnalysis() {
    if (!props.aiPrompts.analysis) {
        return;
    }

    selectAction({
        ...props.aiPrompts.analysis,
        id: 'project_analysis',
        label: props.aiPrompts.analysis.title,
    });
}

function openFilePicker() {
    if (!canAttach.value) {
        return;
    }

    fileInputRef.value?.click();
}

async function onFileSelected(event) {
    const files = Array.from(event.target.files ?? []);
    event.target.value = '';

    if (files.length === 0 || typeof props.chat.attachFile !== 'function') {
        return;
    }

    for (const file of files) {
        await props.chat.attachFile(file);
    }

    nextTick(() => textareaRef.value?.focus());
}

async function onPaste(event) {
    if (!canAttach.value || typeof props.chat.attachFile !== 'function') {
        return;
    }

    const file = imageFileFromPasteEvent(event);

    if (!file) {
        return;
    }

    // Не вставлять бинарный мусор в textarea — только прикрепляем скриншот.
    event.preventDefault();
    await props.chat.attachFile(file);
    nextTick(() => textareaRef.value?.focus());
}
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col bg-f5-surface dark:bg-f5-black">
        <div
            :ref="(element) => { chat.messagesEl.value = element; }"
            class="min-h-0 flex-1 overflow-y-auto"
            :class="compact ? 'px-3 py-3' : 'px-4 py-6 sm:px-6 lg:px-8'"
        >
            <div class="mx-auto flex w-full max-w-none flex-col gap-4">
                <div
                    v-if="chat.messages.value.length === 0"
                    class="flex flex-col items-center justify-center text-center"
                    :class="compact ? 'my-8 px-2' : 'my-16 px-4'"
                >
                    <div
                        class="mb-4 flex items-center justify-center rounded-2xl bg-gradient-to-br from-f5-green to-f5-yellow font-bold text-f5-black shadow-sm"
                        :class="compact ? 'h-12 w-12 text-lg' : 'h-16 w-16 text-2xl'"
                    >
                        AI
                    </div>
                    <h3
                        class="font-semibold text-f5-text dark:text-white"
                        :class="compact ? 'text-base' : 'text-xl'"
                    >
                        {{ emptyTitle }}
                    </h3>
                    <p class="mt-2 max-w-md text-sm text-f5-text-muted dark:text-white/55">
                        {{ emptyDescription }}
                    </p>

                    <div
                        v-if="!compact && (quickActions.length || showProjectAnalysis)"
                        class="mt-6 flex w-full max-w-lg flex-wrap justify-center gap-2"
                    >
                        <button
                            v-if="showProjectAnalysis"
                            type="button"
                            class="rounded-full border border-f5-border bg-f5-surface-2 px-3.5 py-2 text-xs font-medium text-f5-text transition hover:border-f5-green/50 dark:border-white/10 dark:bg-white/5 dark:text-white/80"
                            @click="selectAnalysis"
                        >
                            {{ aiPrompts.analysis.title }}
                        </button>
                        <button
                            v-for="action in quickActions"
                            :key="action.id"
                            type="button"
                            class="rounded-full border border-f5-border bg-f5-surface-2 px-3.5 py-2 text-xs font-medium text-f5-text transition hover:border-f5-green/50 dark:border-white/10 dark:bg-white/5 dark:text-white/80"
                            :title="action.description"
                            @click="selectAction(action)"
                        >
                            {{ action.label }}
                        </button>
                    </div>
                </div>

                <AiChatMessage
                    v-for="message in chat.messages.value"
                    :key="message.id"
                    :message="message"
                    :apply-url="chat.applyUrl(message)"
                    :applying="chat.applyingId.value === message.id"
                    :rolling-back="chat.rollingBackId.value === message.id"
                    :compact="compact"
                    :show-cancel="message.id === processingMessage?.id"
                    :cancelling="chat.cancelling.value"
                    @apply="chat.applyChanges"
                    @rollback="chat.rollbackChanges"
                    @cancel="chat.cancelCurrentRequest"
                />

                <div
                    v-if="chat.loading.value && !hasProcessing"
                    class="flex items-start gap-3 self-start"
                >
                    <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-f5-green to-f5-yellow text-[10px] font-bold text-f5-black">
                        ИИ
                    </div>
                    <div class="rounded-2xl rounded-tl-md border border-f5-border bg-f5-surface-2 px-4 py-3 dark:border-white/10 dark:bg-white/5">
                        <div class="flex items-center gap-2 text-sm text-f5-text-muted dark:text-white/60">
                            <span class="ai-typing-dots" aria-hidden="true">
                                <span /><span /><span />
                            </span>
                            {{ chat.loadingText.value }}
                        </div>
                    </div>
                </div>

                <div
                    v-if="hasProcessing"
                    class="flex justify-center"
                >
                    <button
                        type="button"
                        class="f5-btn f5-btn--ghost f5-btn--sm"
                        :disabled="chat.cancelling.value"
                        @click="chat.cancelCurrentRequest"
                    >
                        {{ chat.cancelling.value ? 'Отмена…' : 'Остановить генерацию' }}
                    </button>
                </div>
            </div>
        </div>

        <div
            class="shrink-0 border-t border-f5-border bg-f5-surface/95 backdrop-blur dark:border-white/10 dark:bg-f5-black/95"
        >
            <div
                class="mx-auto w-full"
                :class="compact ? 'px-3 py-3' : 'px-4 py-4 sm:px-6 lg:px-8'"
            >
                <p
                    v-if="chat.status.value"
                    class="mb-2 text-xs"
                    :class="chat.statusType.value === 'error'
                        ? 'text-red-600 dark:text-red-400'
                        : 'text-f5-text-muted dark:text-white/55'"
                    role="status"
                >
                    {{ chat.status.value }}
                </p>

                <div
                    v-if="canEdit"
                    class="mb-2 flex items-center gap-2 rounded-xl border border-f5-border bg-f5-surface-2/80 px-3 py-2 dark:border-white/10 dark:bg-white/[0.04]"
                >
                    <span class="shrink-0 text-[11px] font-medium text-f5-text-muted dark:text-white/45">
                        Модель
                    </span>
                    <div class="min-w-0 flex-1">
                        <AiModelSelect :compact="compact" :disabled="isBusy" />
                    </div>
                </div>

                <div
                    v-if="canEdit && !compact && chat.messages.value.length > 0 && (quickActions.length || showProjectAnalysis)"
                    class="mb-2 flex gap-1.5 overflow-x-auto pb-0.5 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                >
                    <button
                        v-if="showProjectAnalysis"
                        type="button"
                        class="shrink-0 rounded-full border border-f5-border bg-f5-surface-2 px-2.5 py-1 text-[11px] font-medium text-f5-text transition hover:border-f5-green/50 dark:border-white/10 dark:bg-white/5 dark:text-white/75"
                        @click="selectAnalysis"
                    >
                        {{ aiPrompts.analysis.title }}
                    </button>
                    <button
                        v-for="action in quickActions"
                        :key="action.id"
                        type="button"
                        class="shrink-0 rounded-full border px-2.5 py-1 text-[11px] font-medium transition"
                        :class="!isConsultationThread && chat.sendMode.value === action.mode
                            ? 'border-f5-green bg-f5-green/15 text-f5-black dark:text-white'
                            : 'border-f5-border bg-f5-surface-2 text-f5-text hover:border-f5-green/50 dark:border-white/10 dark:bg-white/5 dark:text-white/75'"
                        @click="selectAction(action)"
                    >
                        {{ action.label }}
                    </button>
                </div>

                <p
                    v-if="!isConsultationThread && chat.sendMode.value"
                    class="mb-2 text-[11px] font-medium text-f5-green"
                >
                    Режим: {{ activeModeLabel }}
                    <button type="button" class="ml-1 underline" @click="chat.sendMode.value = null">
                        сбросить
                    </button>
                </p>

                <form
                    v-if="canEdit"
                    class="rounded-3xl border border-f5-border bg-f5-surface-2 shadow-sm transition focus-within:border-f5-green/50 focus-within:shadow-md dark:border-white/10 dark:bg-white/[0.04] dark:focus-within:border-f5-green/40"
                    @submit.prevent="canSend && chat.sendMessage()"
                >
                    <input
                        ref="fileInputRef"
                        type="file"
                        class="hidden"
                        multiple
                        :accept="acceptedTypes"
                        @change="onFileSelected"
                    >

                    <div
                        v-if="(chat.pendingAttachments?.value?.length ?? 0) > 0 || chat.attaching?.value"
                        class="flex flex-wrap items-center gap-2 px-3.5 pt-3"
                    >
                        <div
                            v-for="(attachment, index) in chat.pendingAttachments.value"
                            :key="attachment.id"
                            class="inline-flex min-w-0 max-w-full items-center gap-2 rounded-2xl border border-f5-border bg-f5-surface px-3 py-1.5 text-xs text-f5-text dark:border-white/10 dark:bg-white/5 dark:text-white/85"
                        >
                            <span
                                v-if="attachment.is_image"
                                class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-f5-green/20 text-[11px] font-bold text-f5-black dark:text-white"
                                aria-hidden="true"
                            >
                                IMG
                            </span>
                            <svg
                                v-else
                                class="h-3.5 w-3.5 shrink-0 text-f5-text-muted dark:text-white/50"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                aria-hidden="true"
                            >
                                <path d="M21.44 11.05 12.1 20.39a5.5 5.5 0 0 1-7.78-7.78l9.9-9.9a3.5 3.5 0 0 1 4.95 4.95l-9.2 9.19a1.5 1.5 0 0 1-2.12-2.12l8.49-8.48" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <span class="truncate">{{ attachment.original_name }}</span>
                            <span
                                v-if="attachment.size_label"
                                class="shrink-0 text-f5-text-muted dark:text-white/45"
                            >
                                {{ attachment.size_label }}
                            </span>
                            <button
                                type="button"
                                class="shrink-0 text-f5-text-muted transition hover:text-f5-text dark:text-white/45 dark:hover:text-white"
                                title="Убрать файл"
                                aria-label="Убрать файл"
                                @click="chat.removeAttachment(index)"
                            >
                                ×
                            </button>
                        </div>
                        <div
                            v-if="chat.attaching?.value"
                            class="inline-flex items-center gap-2 rounded-2xl border border-f5-border bg-f5-surface px-3 py-1.5 text-xs text-f5-text-muted dark:border-white/10 dark:bg-white/5 dark:text-white/60"
                        >
                            Загрузка файла…
                        </div>
                    </div>

                    <textarea
                        ref="textareaRef"
                        v-model="chat.draft.value"
                        rows="1"
                        class="block w-full resize-none border-0 bg-transparent px-4 pt-3.5 pb-2 text-sm leading-relaxed text-f5-text outline-none placeholder:text-f5-text-muted dark:text-white dark:placeholder:text-white/40"
                        :class="compact ? 'max-h-[120px]' : 'max-h-[200px]'"
                        :placeholder="isBusy
                            ? 'ИИ отвечает… можно набрать следующий вопрос'
                            : (isKpThread
                                ? 'Вставьте резюме встречи или пожелания к КП…'
                                : (isAuditThread
                                    ? 'Вставьте текст записи встречи или приложите файл…'
                                    : (isMeetingThread
                                        ? 'Опишите контекст встречи или вставьте скриншот (Ctrl+V)…'
                                        : (compact ? 'Сообщение или Ctrl+V — скриншот…' : 'Напишите сообщение или вставьте скриншот (Ctrl+V)…'))))"
                        :disabled="false"
                        @keydown="onKeydown"
                        @paste="onPaste"
                        @input="resizeTextarea"
                    />

                    <div class="flex items-center justify-between gap-2 px-2.5 pb-2.5">
                        <div class="flex min-w-0 items-center gap-1">
                            <button
                                type="button"
                                class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-f5-text-muted transition hover:bg-f5-surface hover:text-f5-text disabled:cursor-not-allowed disabled:opacity-40 dark:text-white/50 dark:hover:bg-white/10 dark:hover:text-white"
                                :disabled="!canAttach"
                                title="Прикрепить файл или фото · Ctrl+V — вставить скриншот"
                                aria-label="Прикрепить файл или фото"
                                @click="openFilePicker"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M21.44 11.05 12.1 20.39a5.5 5.5 0 0 1-7.78-7.78l9.9-9.9a3.5 3.5 0 0 1 4.95 4.95l-9.2 9.19a1.5 1.5 0 0 1-2.12-2.12l8.49-8.48" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                            <p class="hidden truncate px-1 text-[11px] text-f5-text-muted dark:text-white/40 sm:block">
                                Enter — отправить · Ctrl+V — скриншот
                            </p>
                        </div>
                        <button
                            type="submit"
                            class="ml-auto inline-flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-f5-green to-f5-yellow text-f5-black transition enabled:hover:scale-105 enabled:active:scale-95 disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="!canSend"
                            :title="isBusy ? 'Дождитесь ответа ИИ' : 'Отправить'"
                            aria-label="Отправить"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M3.4 20.6 21 12 3.4 3.4l.1 6.8L15 12 3.5 13.8l-.1 6.8Z" />
                            </svg>
                        </button>
                    </div>
                </form>
                <p
                    v-else
                    class="rounded-xl border border-f5-border bg-f5-surface-2 px-4 py-3 text-sm text-f5-text-muted dark:border-white/10 dark:bg-white/5 dark:text-white/60"
                >
                    Режим просмотра: отправка сообщений недоступна.
                </p>
            </div>
        </div>
    </div>
</template>
