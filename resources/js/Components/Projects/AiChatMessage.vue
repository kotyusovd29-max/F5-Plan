<script setup>
import { computed, ref } from 'vue';
import AiChangesPreview from './AiChangesPreview.vue';
import GoogleDocsModal from '../GoogleDocsModal.vue';
import { useGoogleDocsExport } from '../../composables/useGoogleDocsExport';
import { hasPendingChanges } from '../../utils/aiChat';
import { looksLikeMarkdown, renderMarkdown } from '../../utils/renderMarkdown';

const props = defineProps({
    message: {
        type: Object,
        required: true,
    },
    applyUrl: {
        type: String,
        default: '',
    },
    applying: {
        type: Boolean,
        default: false,
    },
    rollingBack: {
        type: Boolean,
        default: false,
    },
    compact: {
        type: Boolean,
        default: false,
    },
    showCancel: {
        type: Boolean,
        default: false,
    },
    cancelling: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['apply', 'rollback', 'cancel']);

const exportError = ref('');
const {
    googleDoc,
    googleDocsOpen,
    googleDocsExporting,
    openGoogleDoc,
    closeGoogleDocsModal,
    exportToGoogleDocs,
} = useGoogleDocsExport();

const isUser = computed(() => props.message.role === 'user');
const messageAttachments = computed(() => {
    if (Array.isArray(props.message.brief_documents) && props.message.brief_documents.length > 0) {
        return props.message.brief_documents;
    }

    return props.message.brief_document ? [props.message.brief_document] : [];
});
const showChanges = computed(() => !isUser.value && props.message.changes);
const canApply = computed(() => props.message.has_pending_changes || hasPendingChanges(props.message.changes));
const canRollback = computed(() => !!props.message.can_rollback && !!props.message.rollback_url);
const isApplied = computed(() => !!props.message.applied_at && !canApply.value);
const canExportDocx = computed(() => (
    !isUser.value
    && props.message.status === 'completed'
    && !!props.message.export_docx_url
    && !!props.message.content?.trim()
));
const canExportGoogleDocs = computed(() => (
    !isUser.value
    && props.message.status === 'completed'
    && !!props.message.export_google_docs_url
    && !!props.message.content?.trim()
));
const kpDocument = computed(() => (
    !isUser.value && props.message.status === 'completed'
        ? props.message.kp_document ?? null
        : null
));

const renderedContentHtml = computed(() => {
    if (isUser.value || !props.message.content?.trim()) {
        return '';
    }

    if (props.message.status === 'processing'
        || props.message.status === 'failed'
        || props.message.status === 'cancelled'
    ) {
        return '';
    }

    if (!looksLikeMarkdown(props.message.content)) {
        return '';
    }

    return renderMarkdown(props.message.content);
});

function formatAppliedAt(value) {
    if (!value) {
        return '';
    }

    try {
        return new Date(value).toLocaleString('ru-RU', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return value;
    }
}

async function saveToGoogleDocs() {
    exportError.value = '';

    try {
        await exportToGoogleDocs(props.message.export_google_docs_url);
    } catch (error) {
        exportError.value = error?.message || 'Не удалось сохранить в Google Docs.';
    }
}

function openKpPresentation() {
    if (!kpDocument.value) {
        return;
    }

    openGoogleDoc({
        name: kpDocument.value.name,
        url: kpDocument.value.url,
        download_url: kpDocument.value.download_url ?? null,
    });
}
</script>

<template>
    <article
        class="flex w-full gap-3"
        :class="isUser ? 'justify-end' : 'justify-start'"
    >
        <div
            v-if="!isUser"
            class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-f5-green to-f5-yellow text-[10px] font-bold text-f5-black"
            aria-hidden="true"
        >
            ИИ
        </div>

        <div
            class="min-w-0"
            :class="compact ? 'max-w-[92%]' : 'max-w-[min(100%,90%)] sm:max-w-[min(100%,80%)] lg:max-w-[min(100%,72rem)]'"
        >
            <div
                class="rounded-2xl px-4 py-3 text-sm leading-relaxed"
                :class="isUser
                    ? 'rounded-tr-md bg-f5-green/15 text-f5-text dark:bg-f5-green/20 dark:text-white'
                    : 'rounded-tl-md border border-f5-border bg-f5-surface-2 text-f5-text dark:border-white/10 dark:bg-white/[0.04] dark:text-white/90'"
            >
                <div
                    v-if="isUser && messageAttachments.length > 0"
                    class="mb-2 flex max-w-full flex-wrap items-center gap-1.5"
                >
                    <div
                        v-for="document in messageAttachments"
                        :key="document.id"
                        class="inline-flex max-w-full items-center gap-1.5 rounded-full border border-f5-border/80 bg-f5-surface/70 px-2.5 py-1 text-[11px] dark:border-white/15 dark:bg-black/20"
                    >
                        <span
                            v-if="document.is_image"
                            class="inline-flex h-4 min-w-4 items-center justify-center rounded bg-f5-green/25 px-1 text-[9px] font-bold"
                            aria-hidden="true"
                        >IMG</span>
                        <svg
                            v-else
                            class="h-3 w-3 shrink-0 opacity-70"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            aria-hidden="true"
                        >
                            <path d="M21.44 11.05 12.1 20.39a5.5 5.5 0 0 1-7.78-7.78l9.9-9.9a3.5 3.5 0 0 1 4.95 4.95l-9.2 9.19a1.5 1.5 0 0 1-2.12-2.12l8.49-8.48" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <span class="truncate">{{ document.original_name }}</span>
                        <span
                            v-if="document.size_label"
                            class="shrink-0 opacity-60"
                        >
                            {{ document.size_label }}
                        </span>
                    </div>
                </div>

                <div
                    v-if="message.status === 'processing'"
                    class="flex flex-col gap-2"
                >
                    <div class="flex items-center gap-2 text-f5-text-muted dark:text-white/60">
                        <span class="ai-typing-dots" aria-hidden="true">
                            <span /><span /><span />
                        </span>
                        <span>{{ message.content || 'ИИ обрабатывает запрос…' }}</span>
                    </div>
                    <button
                        v-if="showCancel"
                        type="button"
                        class="self-start text-xs font-medium text-f5-text-muted underline transition hover:text-f5-text dark:text-white/50 dark:hover:text-white"
                        :disabled="cancelling"
                        @click="emit('cancel')"
                    >
                        {{ cancelling ? 'Отмена…' : 'Остановить' }}
                    </button>
                </div>

                <div
                    v-else-if="message.status === 'cancelled'"
                    class="text-f5-text-muted dark:text-white/55"
                >
                    {{ message.error_message || message.content || 'Запрос отменён.' }}
                </div>

                <div
                    v-else-if="message.status === 'failed'"
                    class="text-red-600 dark:text-red-400"
                >
                    {{ message.error_message || message.content || 'ИИ не смог обработать запрос.' }}
                </div>

                <div
                    v-else-if="renderedContentHtml"
                    class="ai-markdown break-words"
                    v-html="renderedContentHtml"
                />

                <div
                    v-else
                    class="whitespace-pre-wrap break-words"
                >
                    {{ message.content }}
                </div>

                <div
                    v-if="showChanges && message.status !== 'processing' && message.status !== 'failed' && message.status !== 'cancelled'"
                    class="mt-3 border-t border-f5-border/70 pt-3 dark:border-white/10"
                >
                    <AiChangesPreview :changes="message.changes" />

                    <div
                        v-if="canApply || canRollback || isApplied"
                        class="mt-3 flex flex-wrap items-center gap-2"
                    >
                        <button
                            v-if="canApply"
                            type="button"
                            class="f5-btn f5-btn--primary f5-btn--sm"
                            :disabled="applying || rollingBack"
                            @click="emit('apply', message)"
                        >
                            {{ applying ? 'Применение…' : 'Применить изменения' }}
                        </button>

                        <button
                            v-if="canRollback"
                            type="button"
                            class="f5-btn f5-btn--ghost f5-btn--sm"
                            :disabled="applying || rollingBack"
                            @click="emit('rollback', message)"
                        >
                            {{ rollingBack ? 'Откат…' : 'Откатить' }}
                        </button>

                        <p v-if="isApplied" class="text-xs text-green-700 dark:text-green-400">
                            Изменения применены {{ formatAppliedAt(message.applied_at) }}
                            <template v-if="canRollback"> · можно откатить</template>
                        </p>
                    </div>
                </div>

                <div
                    v-if="kpDocument"
                    class="mt-3 flex flex-wrap items-center gap-2 border-t border-f5-border/70 pt-3 dark:border-white/10"
                >
                    <button
                        type="button"
                        class="f5-btn f5-btn--primary f5-btn--sm inline-flex items-center gap-1.5"
                        @click="openKpPresentation"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        Открыть в Google Slides
                    </button>
                    <a
                        v-if="kpDocument.download_url"
                        :href="kpDocument.download_url"
                        class="f5-btn f5-btn--ghost f5-btn--sm inline-flex items-center gap-1.5"
                        download
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Скачать PPTX
                    </a>
                </div>

                <div
                    v-if="canExportDocx || canExportGoogleDocs"
                    class="mt-3 flex flex-wrap items-center gap-2 border-t border-f5-border/70 pt-3 dark:border-white/10"
                >
                    <a
                        v-if="canExportDocx"
                        :href="message.export_docx_url"
                        class="f5-btn f5-btn--ghost f5-btn--sm inline-flex items-center gap-1.5"
                        download
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Сохранить в DOCX
                    </a>
                    <button
                        v-if="canExportGoogleDocs"
                        type="button"
                        class="f5-btn f5-btn--primary f5-btn--sm"
                        :disabled="googleDocsExporting"
                        @click="saveToGoogleDocs"
                    >
                        {{ googleDocsExporting ? 'Сохранение…' : 'В Google Docs' }}
                    </button>
                    <p v-if="exportError" class="w-full text-xs text-red-600 dark:text-red-400">
                        {{ exportError }}
                    </p>
                </div>
            </div>

            <time
                v-if="message.created_at"
                class="mt-1 block px-1 text-[11px] text-f5-text-muted dark:text-white/35"
                :class="isUser ? 'text-right' : 'text-left'"
            >
                {{ message.created_at }}
            </time>
        </div>

        <GoogleDocsModal
            :open="googleDocsOpen"
            :document="googleDoc"
            @close="closeGoogleDocsModal"
        />
    </article>
</template>
