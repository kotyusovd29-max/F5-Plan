<script setup>
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import GoogleDocsModal from '../GoogleDocsModal.vue';
import { useGoogleDocsExport } from '../../composables/useGoogleDocsExport';

const props = defineProps({
    projectId: {
        type: Number,
        required: true,
    },
    urls: {
        type: Object,
        required: true,
    },
    current: {
        type: String,
        default: 'project',
    },
    compact: {
        type: Boolean,
        default: false,
    },
    showTabs: {
        type: Boolean,
        default: true,
    },
    showExports: {
        type: Boolean,
        default: true,
    },
    canEdit: {
        type: Boolean,
        default: true,
    },
});

const exportError = ref('');
const {
    googleDoc,
    googleDocsOpen,
    googleDocsExporting,
    closeGoogleDocsModal,
    exportToGoogleDocs,
} = useGoogleDocsExport();

const tabClass = (active, compact) => [
    compact
        ? 'inline-flex shrink-0 items-center rounded-lg px-3 py-1.5 text-sm font-medium transition-colors'
        : 'inline-flex shrink-0 items-center border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
    active
        ? compact
            ? 'bg-f5-green/15 text-f5-text dark:bg-f5-green/20 dark:text-white'
            : 'border-f5-green text-f5-black dark:text-f5-green'
        : compact
            ? 'text-f5-text-muted hover:bg-f5-surface-2 hover:text-f5-text dark:text-white/50 dark:hover:bg-white/5 dark:hover:text-white'
            : 'border-transparent text-f5-text-muted hover:text-f5-text dark:text-white/50 dark:hover:text-white',
];

async function saveToGoogleDocs() {
    exportError.value = '';

    try {
        await exportToGoogleDocs(props.urls.export_google_docs);
    } catch (error) {
        exportError.value = error?.message || 'Не удалось сохранить в Google Docs.';
    }
}

async function saveToGoogleSheets() {
    exportError.value = '';

    try {
        await exportToGoogleDocs(props.urls.export_google_sheets, {
            errorMessage: 'Не удалось сохранить в Google Таблицы.',
            connectMessage: 'Подключите Google в личном кабинете, чтобы сохранять смету в Google Таблицы.',
        });
    } catch (error) {
        exportError.value = error?.message || 'Не удалось сохранить в Google Таблицы.';
    }
}
</script>

<template>
    <div
        class="flex overflow-hidden"
        :class="compact
            ? 'shrink-0 flex-col gap-2 sm:flex-row sm:items-center sm:justify-between'
            : 'mb-5 flex-col gap-3 border-b border-f5-border dark:border-white/10 sm:flex-row sm:items-center sm:justify-between'"
    >
        <nav
            v-if="showTabs"
            class="flex min-w-0 flex-wrap gap-1"
            :class="compact ? '' : 'gap-2'"
            data-tour="project-nav"
            aria-label="Разделы проекта"
        >
            <Link
                :href="`/projects/${projectId}`"
                :class="tabClass(current === 'project', compact)"
            >
                Проект
            </Link>
            <Link
                :href="urls.ai_chat"
                :class="tabClass(current === 'ai', compact)"
            >
                ИИ-чат
            </Link>
            <Link
                v-if="urls.documents"
                :href="urls.documents"
                :class="tabClass(current === 'documents', compact)"
            >
                Документы
            </Link>
            <Link
                :href="urls.canvas"
                :class="tabClass(current === 'canvas', compact)"
            >
                Доска
            </Link>
        </nav>

        <div
            v-if="showExports && (urls.export_docx || urls.export_xlsx || urls.export_google_docs || urls.export_google_sheets)"
            class="flex flex-wrap items-center gap-2 self-start sm:self-auto"
        >
            <a
                v-if="urls.export_docx"
                :href="urls.export_docx"
                class="f5-btn f5-btn--ghost f5-btn--sm shrink-0"
            >
                DOCX
            </a>
            <a
                v-if="urls.export_xlsx"
                :href="urls.export_xlsx"
                class="f5-btn f5-btn--ghost f5-btn--sm shrink-0"
            >
                XLSX
            </a>
            <button
                v-if="canEdit && urls.export_google_docs"
                type="button"
                class="f5-btn f5-btn--ghost f5-btn--sm shrink-0"
                :disabled="googleDocsExporting"
                @click="saveToGoogleDocs"
            >
                {{ googleDocsExporting ? 'Сохранение…' : 'Google Docs' }}
            </button>
            <button
                v-if="canEdit && urls.export_google_sheets"
                type="button"
                class="f5-btn f5-btn--primary f5-btn--sm shrink-0"
                :disabled="googleDocsExporting"
                @click="saveToGoogleSheets"
            >
                {{ googleDocsExporting ? 'Сохранение…' : 'Google Sheets' }}
            </button>
            <p v-if="exportError" class="w-full text-xs text-red-600 dark:text-red-400">
                {{ exportError }}
            </p>
        </div>
    </div>

    <GoogleDocsModal
        :open="googleDocsOpen"
        :document="googleDoc"
        @close="closeGoogleDocsModal"
    />
</template>
