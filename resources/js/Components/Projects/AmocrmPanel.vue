<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { confirmDialog } from '../../utils/confirmDialog';
import { getCsrfToken, getJson, postJson } from '../../utils/fetchJson';
import GoogleDocsModal from '../GoogleDocsModal.vue';
import { useGoogleDocsExport } from '../../composables/useGoogleDocsExport';
import AmocrmAiSeedModal from './AmocrmAiSeedModal.vue';
import AmocrmAuditScopeModal from './AmocrmAuditScopeModal.vue';
import AmocrmPipelineImportModal from './AmocrmPipelineImportModal.vue';
import WorkspaceCard from './WorkspaceCard.vue';

const props = defineProps({
    amocrm: {
        type: Object,
        required: true,
    },
    canEdit: {
        type: Boolean,
        default: true,
    },
    collapsible: {
        type: Boolean,
        default: false,
    },
    defaultOpen: {
        type: Boolean,
        default: true,
    },
    storageKey: {
        type: String,
        default: '',
    },
});

const page = usePage();
const aiChatUrl = computed(() => page.props.project?.urls?.ai_chat ?? '');
const showForm = ref(!props.amocrm.connected);
const exporting = ref(false);
const previewLoading = ref(false);
const previewOpen = ref(false);
const previewError = ref('');
const preview = ref(null);
const selectedFunnelIds = ref([]);
const selectedFieldIds = ref([]);
const includeFields = ref(true);
const importOpen = ref(false);
const aiSeedOpen = ref(false);
const auditScopeOpen = ref(false);
const auditLoading = ref(false);
const auditError = ref('');
const auditResult = ref(props.amocrm.last_ai_audit_result ?? '');
const auditAt = ref(props.amocrm.last_ai_audit_at ?? '');
const auditModalOpen = ref(false);
const auditStreaming = ref(false);
const auditStatus = ref('');
let auditAbort = null;
const auditExportError = ref('');
const snapshotLoading = ref(false);
const snapshotError = ref('');
const snapshotAt = ref(props.amocrm.snapshot_at ?? '');
const snapshotSummary = ref(props.amocrm.snapshot_summary ?? null);
const auditScrollEl = ref(null);

watch([auditResult, auditStatus], async () => {
    if (!auditStreaming.value || !auditScrollEl.value) {
        return;
    }

    await nextTick();
    auditScrollEl.value.scrollTop = auditScrollEl.value.scrollHeight;
});

onMounted(() => {
    if (props.canEdit && props.amocrm.connected && page.props.flash?.amocrm_import_open) {
        importOpen.value = true;
    }
});

const form = useForm({
    subdomain: props.amocrm.subdomain ?? '',
    access_token: '',
});

const {
    googleDoc,
    googleDocsOpen,
    googleDocsExporting,
    closeGoogleDocsModal,
    exportToGoogleDocs,
} = useGoogleDocsExport();

const lastResult = computed(() => props.amocrm.last_export_result);

const selectedFunnels = computed(() => {
    const funnels = Array.isArray(preview.value?.funnels) ? preview.value.funnels : [];
    const ids = new Set(selectedFunnelIds.value);

    return funnels.filter((funnel) => ids.has(funnel.id));
});

const selectedFields = computed(() => {
    if (!includeFields.value) {
        return [];
    }

    const fields = Array.isArray(preview.value?.fields) ? preview.value.fields : [];
    const ids = new Set(selectedFieldIds.value);

    return fields.filter((field) => ids.has(field.id));
});

const allFunnelsSelected = computed(() => {
    const funnels = preview.value?.funnels ?? [];

    return funnels.length > 0 && selectedFunnelIds.value.length === funnels.length;
});

const someFunnelsSelected = computed(() => (
    selectedFunnelIds.value.length > 0 && !allFunnelsSelected.value
));

const allFieldsSelected = computed(() => {
    const fields = preview.value?.fields ?? [];

    return fields.length > 0 && selectedFieldIds.value.length === fields.length;
});

const someFieldsSelected = computed(() => (
    selectedFieldIds.value.length > 0 && !allFieldsSelected.value
));

const conflictCount = computed(() => countByStatus('conflict'));
const createCount = computed(() => countByStatus('create'));
const existingCount = computed(() => countByStatus('existing'));

const canExport = computed(() => (
    selectedFunnelIds.value.length > 0
    || (includeFields.value && selectedFieldIds.value.length > 0)
));

function countByStatus(status) {
    let count = 0;

    for (const funnel of selectedFunnels.value) {
        if (funnel.status === status) {
            count += 1;
        }

        for (const stage of funnel.stages ?? []) {
            if (stage.status === status) {
                count += 1;
            }
        }
    }

    for (const field of selectedFields.value) {
        if (field.status === status) {
            count += 1;
        }
    }

    return count;
}

watch(includeFields, (enabled) => {
    if (!enabled) {
        return;
    }

    if (selectedFieldIds.value.length === 0 && preview.value?.fields?.length) {
        selectedFieldIds.value = preview.value.fields.map((field) => field.id);
    }
});

function connect() {
    form.post(props.amocrm.urls.connect, {
        preserveScroll: true,
        onSuccess: (pageAfter) => {
            if (pageAfter.props.flash?.error) {
                return;
            }

            form.reset('access_token');
            showForm.value = false;

            if (pageAfter.props.flash?.amocrm_import_open) {
                importOpen.value = true;
            }
        },
    });
}

function openImportModal() {
    importOpen.value = true;
}

async function refreshSnapshot() {
    if (!props.canEdit || !props.amocrm.urls?.snapshot) {
        return;
    }

    snapshotLoading.value = true;
    snapshotError.value = '';

    try {
        const { response, data } = await postJson(props.amocrm.urls.snapshot);

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Не удалось обновить снимок.');
        }

        snapshotSummary.value = data.summary ?? null;
        snapshotAt.value = data.snapshot_at ?? '';
    } catch (error) {
        snapshotError.value = error instanceof Error ? error.message : 'Не удалось обновить снимок.';
    } finally {
        snapshotLoading.value = false;
    }
}

function onPipelinesImported() {
    router.reload({ preserveScroll: true });
}

async function disconnect() {
    const confirmed = await confirmDialog({
        title: 'Отключить amoCRM?',
        message: 'Токен будет удалён из проекта. Уже созданные в amo воронки и поля останутся; связь по ID сохранится в плане.',
        confirmLabel: 'Отключить',
        variant: 'danger',
    });

    if (!confirmed) {
        return;
    }

    router.delete(props.amocrm.urls.disconnect, { preserveScroll: true });
}

async function loadPreview() {
    previewLoading.value = true;
    previewError.value = '';

    try {
        const { response, data } = await getJson(props.amocrm.urls.preview);

        if (!response.ok) {
            throw new Error(data?.message || 'Не удалось получить предпросмотр выгрузки.');
        }

        preview.value = data;
        selectedFunnelIds.value = (data.funnels ?? []).map((funnel) => funnel.id);
        selectedFieldIds.value = (data.fields ?? []).map((field) => field.id);
        includeFields.value = (data.fields ?? []).length > 0;
        previewOpen.value = true;
    } catch (error) {
        previewError.value = error?.message || 'Не удалось получить предпросмотр выгрузки.';
    } finally {
        previewLoading.value = false;
    }
}

function onSelectAllFunnelsChange(event) {
    selectedFunnelIds.value = event.target.checked
        ? (preview.value?.funnels ?? []).map((funnel) => funnel.id)
        : [];
}

function onFunnelCheck(id, event) {
    if (event.target.checked) {
        if (!selectedFunnelIds.value.includes(id)) {
            selectedFunnelIds.value = [...selectedFunnelIds.value, id];
        }

        return;
    }

    selectedFunnelIds.value = selectedFunnelIds.value.filter((item) => item !== id);
}

function onSelectAllFieldsChange(event) {
    selectedFieldIds.value = event.target.checked
        ? (preview.value?.fields ?? []).map((field) => field.id)
        : [];
}

function onFieldCheck(id, event) {
    if (event.target.checked) {
        if (!selectedFieldIds.value.includes(id)) {
            selectedFieldIds.value = [...selectedFieldIds.value, id];
        }

        return;
    }

    selectedFieldIds.value = selectedFieldIds.value.filter((item) => item !== id);
}

function exportToAmo() {
    if (!canExport.value) {
        previewError.value = 'Выберите хотя бы одну воронку или поле для выгрузки.';
        return;
    }

    exporting.value = true;
    previewError.value = '';

    router.post(props.amocrm.urls.export, {
        funnel_ids: selectedFunnelIds.value,
        include_fields: includeFields.value,
        field_ids: includeFields.value ? selectedFieldIds.value : [],
    }, {
        preserveScroll: true,
        onFinish: () => {
            exporting.value = false;
            previewOpen.value = false;
        },
    });
}

function openAuditScope() {
    auditError.value = '';
    auditScopeOpen.value = true;
}

async function runAiAudit(scope = null) {
    const pipelineIds = Array.isArray(scope?.pipeline_ids) ? scope.pipeline_ids : [];

    // Клик по кнопке / пустой scope — сначала модалка выбора, не запрос в API.
    if (pipelineIds.length === 0) {
        openAuditScope();
        return;
    }

    auditLoading.value = true;
    auditStreaming.value = true;
    auditError.value = '';
    auditExportError.value = '';
    auditResult.value = '';
    auditStatus.value = 'Подключаюсь к amoCRM…';
    auditScopeOpen.value = false;
    auditModalOpen.value = true;

    auditAbort = new AbortController();

    try {
        // SSE-поток: ответ ИИ подгружается в реальном времени, без таймаута.
        const response = await fetch(props.amocrm.urls.audit_stream, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'text/event-stream',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                pipeline_ids: pipelineIds,
                include_fields: scope?.include_fields ?? true,
            }),
            signal: auditAbort.signal,
        });

        if (!response.ok) {
            const data = await response.json().catch(() => null);

            throw new Error(data?.message || `Сервер вернул ошибку (${response.status}).`);
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        let eventName = 'message';
        let finished = false;

        while (true) {
            const { done, value } = await reader.read();

            if (done) {
                break;
            }

            buffer += decoder.decode(value, { stream: true });

            let newlineIndex;
            while ((newlineIndex = buffer.indexOf('\n')) !== -1) {
                const line = buffer.slice(0, newlineIndex).replace(/\r$/, '');
                buffer = buffer.slice(newlineIndex + 1);

                if (line === '') {
                    continue;
                }

                if (line.startsWith('event:')) {
                    eventName = line.slice(6).trim();
                    continue;
                }

                if (!line.startsWith('data:')) {
                    continue;
                }

                let payload = null;
                try {
                    payload = JSON.parse(line.slice(5).trim());
                } catch {
                    continue;
                }

                if (eventName === 'status') {
                    auditStatus.value = payload?.text ?? '';
                } else if (eventName === 'chunk') {
                    auditResult.value += payload?.text ?? '';
                } else if (eventName === 'done') {
                    auditAt.value = payload?.audited_at ?? '';
                    finished = true;
                } else if (eventName === 'error') {
                    throw new Error(payload?.message || 'Не удалось выполнить AI-аудит amoCRM.');
                }

                eventName = 'message';
            }
        }

        if (!finished && !auditResult.value) {
            throw new Error('Поток ответа ИИ оборвался. Попробуйте ещё раз.');
        }
    } catch (error) {
        if (error?.name !== 'AbortError') {
            auditError.value = error?.message || 'Не удалось выполнить AI-аудит amoCRM.';
        }

        if (!auditResult.value) {
            auditModalOpen.value = false;
        }
    } finally {
        auditLoading.value = false;
        auditStreaming.value = false;
        auditStatus.value = '';
        auditAbort = null;
    }
}

function closeAuditModal() {
    // Закрытие во время стрима останавливает генерацию.
    if (auditStreaming.value && auditAbort) {
        auditAbort.abort();
    }

    auditModalOpen.value = false;
}

async function saveAuditToGoogleDocs() {
    auditExportError.value = '';

    try {
        await exportToGoogleDocs(props.amocrm.urls.audit_google_docs, {
            errorMessage: 'Не удалось сохранить AI-аудит в Google Docs.',
            connectMessage: 'Подключите Google в личном кабинете, чтобы сохранять AI-аудит в Google Docs.',
        });
    } catch (error) {
        auditExportError.value = error?.message || 'Не удалось сохранить AI-аудит в Google Docs.';
    }
}

function itemTone(status) {
    if (status === 'existing') {
        return 'border-f5-green/35 bg-f5-green/10 text-f5-text dark:text-white';
    }

    if (status === 'conflict') {
        return 'border-f5-yellow/45 bg-f5-yellow/10 text-f5-text dark:text-white';
    }

    return 'border-f5-border bg-f5-surface-2 text-f5-text-muted dark:border-white/10 dark:bg-white/5 dark:text-white/55';
}

function itemLabel(status) {
    if (status === 'existing') {
        return 'Уже есть';
    }

    if (status === 'conflict') {
        return 'Конфликт';
    }

    return 'Создать';
}
</script>

<template>
    <WorkspaceCard
        :collapsible="collapsible"
        :default-open="defaultOpen"
        :storage-key="storageKey"
    >
        <template #title>amoCRM</template>
        <template #subtitle>
            <span v-if="amocrm.connected">
                {{ amocrm.account_name || amocrm.subdomain }}.amocrm.ru
            </span>
            <span v-else>
                Выгрузка воронок и полей в аккаунт клиента
            </span>
        </template>
        <template #actions>
            <button
                v-if="canEdit && amocrm.connected && !showForm"
                type="button"
                class="f5-btn f5-btn--ghost f5-btn--sm"
                @click="showForm = true"
            >
                Сменить
            </button>
            <button
                v-if="canEdit && amocrm.connected"
                type="button"
                class="f5-btn f5-btn--danger f5-btn--sm"
                @click="disconnect"
            >
                Отключить
            </button>
        </template>

        <div v-if="amocrm.connected && !showForm" class="space-y-4">
            <dl class="space-y-2 text-sm">
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-f5-text-muted dark:text-white/50">Аккаунт</dt>
                    <dd class="text-right font-medium text-f5-text dark:text-white">
                        {{ amocrm.account_name || amocrm.subdomain }}
                    </dd>
                </div>
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-f5-text-muted dark:text-white/50">Subdomain</dt>
                    <dd class="text-right text-f5-text dark:text-white">
                        {{ amocrm.subdomain }}.amocrm.ru
                    </dd>
                </div>
                <div v-if="amocrm.last_exported_at" class="flex items-start justify-between gap-3">
                    <dt class="text-f5-text-muted dark:text-white/50">Последняя выгрузка</dt>
                    <dd class="text-right text-f5-text dark:text-white">
                        {{ amocrm.last_exported_at }}
                    </dd>
                </div>
                <div v-if="snapshotAt" class="flex items-start justify-between gap-3">
                    <dt class="text-f5-text-muted dark:text-white/50">Снимок аккаунта</dt>
                    <dd class="text-right text-f5-text dark:text-white">
                        {{ snapshotAt }}
                    </dd>
                </div>
            </dl>

            <div
                v-if="snapshotSummary"
                class="rounded-xl border border-f5-border bg-f5-surface-2 p-3 text-xs text-f5-text-muted dark:border-white/10 dark:bg-white/5 dark:text-white/60"
            >
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <p class="font-semibold text-f5-text dark:text-white">
                        Данные для аналитики
                    </p>
                    <button
                        v-if="canEdit"
                        type="button"
                        class="f5-btn f5-btn--ghost f5-btn--sm"
                        :disabled="snapshotLoading"
                        @click="refreshSnapshot"
                    >
                        {{ snapshotLoading ? 'Обновление…' : 'Обновить снимок' }}
                    </button>
                </div>
                <p>
                    Воронок: {{ snapshotSummary.pipelines ?? 0 }},
                    этапов: {{ snapshotSummary.statuses ?? 0 }}
                    <span v-if="snapshotSummary.statuses_with_tips">
                        (с подсказками: {{ snapshotSummary.statuses_with_tips }})
                    </span>,
                    пользователей: {{ snapshotSummary.users ?? 0 }},
                    виджетов: {{ snapshotSummary.widgets ?? 0 }},
                    вебхуков: {{ snapshotSummary.webhooks ?? 0 }},
                    сделок выгружено: {{ snapshotSummary.leads_total ?? snapshotSummary.leads_in_sample ?? 0 }}
                    <span v-if="snapshotSummary.leads_truncated" class="opacity-70">(достигнут лимит выгрузки)</span>,
                    открытых задач: {{ snapshotSummary.tasks_open_total ?? snapshotSummary.tasks_open_sample ?? 0 }}
                    <span v-if="snapshotSummary.tasks_overdue" class="opacity-70">(просрочено: {{ snapshotSummary.tasks_overdue }})</span>.
                </p>
                <p class="mt-1 opacity-70">
                    Полная выгрузка хранится в базе 1 день и затем удаляется автоматически.
                </p>
                <p class="mt-1">
                    Digital Pipeline:
                    <span v-if="snapshotSummary.digital_pipeline_available">
                        найдено триггеров {{ snapshotSummary.digital_pipeline_triggers ?? 0 }}
                    </span>
                    <span v-else>
                        настройки API не отдаёт — используем подсказки этапов, виджеты, вебхуки и события
                    </span>
                </p>
                <p
                    v-if="snapshotSummary.is_partial && snapshotSummary.reused_sections?.length"
                    class="mt-1 text-amber-700 dark:text-amber-300"
                >
                    Часть секций взята из прошлого снимка (amoCRM не ответил):
                    {{ snapshotSummary.reused_sections.join(', ') }}.
                </p>
                <p
                    v-if="snapshotSummary.errors?.length"
                    class="mt-1 text-amber-700 dark:text-amber-300"
                >
                    {{ snapshotSummary.errors[0] }}
                    <span v-if="snapshotSummary.errors_count > 1">
                        (+ ещё {{ snapshotSummary.errors_count - 1 }})
                    </span>
                </p>
                <p v-if="snapshotError" class="mt-1 text-red-600 dark:text-red-400">
                    {{ snapshotError }}
                </p>
            </div>

            <div
                v-else-if="canEdit"
                class="rounded-xl border border-dashed border-f5-border p-3 text-xs text-f5-text-muted dark:border-white/15 dark:text-white/50"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p>Снимок аккаунта ещё не собран (сделки, пользователи, автоматизации).</p>
                    <button
                        type="button"
                        class="f5-btn f5-btn--ghost f5-btn--sm"
                        :disabled="snapshotLoading"
                        @click="refreshSnapshot"
                    >
                        {{ snapshotLoading ? 'Загрузка…' : 'Собрать снимок' }}
                    </button>
                </div>
                <p v-if="snapshotError" class="mt-1 text-red-600 dark:text-red-400">
                    {{ snapshotError }}
                </p>
            </div>

            <div
                v-if="lastResult"
                class="rounded-xl border border-f5-border bg-f5-surface-2 p-3 text-xs text-f5-text-muted dark:border-white/10 dark:bg-white/5 dark:text-white/50"
            >
                Создано: воронок {{ lastResult.pipelines_created ?? 0 }},
                этапов {{ lastResult.stages_created ?? 0 }},
                обновлено этапов {{ lastResult.tips_synced ?? 0 }},
                полей {{ lastResult.fields_created ?? 0 }}.
                Пропущено: воронок {{ lastResult.pipelines_skipped ?? 0 }},
                полей {{ lastResult.fields_skipped ?? 0 }}.
                <p v-if="lastResult.errors?.length" class="mt-1 text-red-600 dark:text-red-400">
                    Ошибки: {{ lastResult.errors.slice(0, 2).join(' ') }}
                </p>
            </div>

            <div v-if="canEdit" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                <button
                    type="button"
                    class="f5-btn f5-btn--ghost w-full"
                    @click="openImportModal"
                >
                    Импорт воронок
                </button>
                <button
                    type="button"
                    class="f5-btn f5-btn--primary w-full"
                    :disabled="exporting || previewLoading"
                    @click="loadPreview"
                >
                    {{ previewLoading ? 'Сравнение…' : exporting ? 'Выгрузка…' : 'Выгрузка в amo' }}
                </button>
                <button
                    type="button"
                    class="f5-btn f5-btn--ghost w-full sm:col-span-2 lg:col-span-1"
                    @click="aiSeedOpen = true"
                >
                    Заполнить через ИИ
                </button>
            </div>
            <p v-if="previewError" class="text-xs text-red-600 dark:text-red-400">
                {{ previewError }}
            </p>

            <div class="rounded-xl border border-f5-border bg-f5-surface-2 p-3 dark:border-white/10 dark:bg-white/5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h4 class="text-sm font-semibold text-f5-text dark:text-white">
                            AI-аудит аккаунта
                        </h4>
                        <p class="mt-0.5 text-xs text-f5-text-muted dark:text-white/50">
                            ИИ проанализирует структуру amoCRM и предложит улучшения/автоматизации.
                        </p>
                    </div>
                    <button
                        v-if="canEdit"
                        type="button"
                        class="f5-btn f5-btn--ghost f5-btn--sm"
                        :disabled="auditLoading"
                        @click.prevent="openAuditScope"
                    >
                        {{ auditLoading ? 'Анализ…' : 'Запустить' }}
                    </button>
                </div>

                <p v-if="auditError" class="mt-2 text-xs text-red-600 dark:text-red-400">
                    {{ auditError }}
                </p>

                <div
                    v-if="auditResult"
                    class="mt-3 rounded-lg border border-f5-border bg-f5-surface p-3 dark:border-white/10 dark:bg-f5-black"
                >
                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <p v-if="auditAt" class="text-[11px] text-f5-text-muted dark:text-white/40">
                            Последний аудит: {{ auditAt }}
                        </p>
                        <button
                            type="button"
                            class="text-xs font-semibold text-f5-accent transition-colors hover:text-f5-accent-hover"
                            @click="auditModalOpen = true"
                        >
                            Открыть на весь экран
                        </button>
                    </div>
                    <div class="max-h-72 overflow-y-auto whitespace-pre-wrap text-sm leading-relaxed text-f5-text dark:text-white/85">
                        {{ auditResult }}
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a
                            :href="amocrm.urls.audit_docx"
                            class="f5-btn f5-btn--ghost f5-btn--sm"
                        >
                            Скачать DOCX
                        </a>
                        <button
                            type="button"
                            class="f5-btn f5-btn--ghost f5-btn--sm"
                            :disabled="googleDocsExporting"
                            @click="saveAuditToGoogleDocs"
                        >
                            {{ googleDocsExporting ? 'Сохранение…' : 'Google Docs' }}
                        </button>
                        <Link
                            v-if="aiChatUrl"
                            :href="aiChatUrl"
                            class="f5-btn f5-btn--primary f5-btn--sm"
                            preserve-scroll
                        >
                            Продолжить в ИИ-чате
                        </Link>
                    </div>
                    <p v-if="auditExportError" class="mt-2 text-xs text-red-600 dark:text-red-400">
                        {{ auditExportError }}
                    </p>
                </div>
            </div>
        </div>

        <form v-else-if="canEdit" class="space-y-4" @submit.prevent="connect">
            <p class="text-sm text-f5-text-muted dark:text-white/50">
                Укажите subdomain клиентского аккаунта и long-lived access token из настроек интеграции amoCRM.
            </p>
            <div>
                <label for="amo_subdomain" class="f5-label">Subdomain</label>
                <div class="flex items-center gap-2">
                    <input
                        id="amo_subdomain"
                        v-model="form.subdomain"
                        type="text"
                        class="f5-input"
                        :class="{ 'f5-input--error': form.errors.subdomain }"
                        placeholder="company"
                        required
                        autocomplete="off"
                    >
                    <span class="shrink-0 text-sm text-f5-text-muted dark:text-white/40">.amocrm.ru</span>
                </div>
                <p v-if="form.errors.subdomain" class="f5-error">{{ form.errors.subdomain }}</p>
            </div>
            <div>
                <label for="amo_token" class="f5-label">Access token</label>
                <input
                    id="amo_token"
                    v-model="form.access_token"
                    type="password"
                    class="f5-input"
                    :class="{ 'f5-input--error': form.errors.access_token }"
                    placeholder="Долгосрочный токен"
                    required
                    autocomplete="off"
                >
                <p v-if="form.errors.access_token" class="f5-error">{{ form.errors.access_token }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="f5-btn f5-btn--primary" :disabled="form.processing">
                    {{ form.processing ? 'Проверка…' : 'Подключить' }}
                </button>
                <button
                    v-if="amocrm.connected"
                    type="button"
                    class="f5-btn f5-btn--ghost"
                    @click="showForm = false"
                >
                    Отмена
                </button>
            </div>
        </form>

        <p
            v-else-if="!amocrm.connected"
            class="text-sm text-f5-text-muted dark:text-white/50"
        >
            amoCRM не подключён. Подключение доступно тем, у кого есть право редактирования.
        </p>
    </WorkspaceCard>

    <Teleport to="body">
        <div
            v-if="previewOpen && preview"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 p-4 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
        >
            <div class="flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-f5-border bg-f5-surface shadow-2xl dark:border-white/10 dark:bg-f5-black">
                <div class="border-b border-f5-border px-5 py-4 dark:border-white/10">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-base font-semibold text-f5-text dark:text-white">
                                Выгрузка в amoCRM
                            </h3>
                            <p class="mt-1 text-sm text-f5-text-muted dark:text-white/50">
                                {{ preview.account?.name || preview.account?.subdomain }}.amocrm.ru
                                · отметьте, что выгружать
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg p-2 text-f5-text-muted transition-colors hover:bg-f5-surface-2 hover:text-f5-text dark:hover:bg-white/10 dark:hover:text-white"
                            @click="previewOpen = false"
                        >
                            ×
                        </button>
                    </div>

                    <div class="mt-4 grid gap-2 sm:grid-cols-3">
                        <div class="rounded-xl border border-f5-green/35 bg-f5-green/10 p-3">
                            <div class="text-2xl font-bold tabular-nums text-f5-text dark:text-white">{{ createCount }}</div>
                            <div class="text-xs text-f5-text-muted dark:text-white/55">будет создано</div>
                        </div>
                        <div class="rounded-xl border border-f5-border bg-f5-surface-2 p-3 dark:border-white/10 dark:bg-white/5">
                            <div class="text-2xl font-bold tabular-nums text-f5-text dark:text-white">{{ existingCount }}</div>
                            <div class="text-xs text-f5-text-muted dark:text-white/55">уже есть / связано</div>
                        </div>
                        <div class="rounded-xl border border-f5-yellow/45 bg-f5-yellow/10 p-3">
                            <div class="text-2xl font-bold tabular-nums text-f5-text dark:text-white">{{ conflictCount }}</div>
                            <div class="text-xs text-f5-text-muted dark:text-white/55">конфликты</div>
                        </div>
                    </div>

                    <div v-if="preview.warnings?.length" class="mt-3 space-y-1">
                        <p
                            v-for="warning in preview.warnings"
                            :key="warning"
                            class="rounded-lg border border-f5-yellow/40 bg-f5-yellow/10 px-3 py-2 text-xs text-f5-text dark:text-white"
                        >
                            {{ warning }}
                        </p>
                    </div>

                    <p
                        v-if="previewError"
                        class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300"
                    >
                        {{ previewError }}
                    </p>
                </div>

                <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-5 py-4">
                    <section>
                        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                            <h4 class="text-xs font-semibold uppercase tracking-wide text-f5-text-muted dark:text-white/40">
                                Воронки и этапы
                            </h4>
                            <label
                                v-if="preview.funnels?.length"
                                class="flex cursor-pointer items-center gap-2 text-xs font-medium text-f5-text dark:text-white"
                            >
                                <input
                                    type="checkbox"
                                    class="h-4 w-4"
                                    :checked="allFunnelsSelected"
                                    :indeterminate="someFunnelsSelected"
                                    :disabled="exporting"
                                    @change="onSelectAllFunnelsChange"
                                >
                                <span>Все воронки ({{ selectedFunnelIds.length }}/{{ preview.funnels.length }})</span>
                            </label>
                        </div>
                        <div class="space-y-3">
                            <label
                                v-for="funnel in preview.funnels"
                                :key="funnel.id"
                                class="flex cursor-pointer gap-3 rounded-xl border p-3 transition"
                                :class="selectedFunnelIds.includes(funnel.id)
                                    ? 'border-f5-green/40 bg-f5-green/10 dark:border-f5-green/30'
                                    : 'border-f5-border bg-f5-surface-2 dark:border-white/10 dark:bg-white/5'"
                            >
                                <input
                                    type="checkbox"
                                    class="mt-1 h-4 w-4 shrink-0"
                                    :checked="selectedFunnelIds.includes(funnel.id)"
                                    :disabled="exporting"
                                    @change="onFunnelCheck(funnel.id, $event)"
                                >
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <h5 class="truncate text-sm font-semibold text-f5-text dark:text-white">{{ funnel.name }}</h5>
                                            <p class="mt-0.5 text-xs text-f5-text-muted dark:text-white/50">{{ funnel.message }}</p>
                                        </div>
                                        <span
                                            class="shrink-0 rounded-md border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                                            :class="itemTone(funnel.status)"
                                        >
                                            {{ itemLabel(funnel.status) }}
                                        </span>
                                    </div>
                                    <div v-if="funnel.stages?.length" class="mt-3 flex flex-wrap gap-1.5">
                                        <span
                                            v-for="stage in funnel.stages"
                                            :key="stage.id"
                                            class="rounded-md border px-2 py-1 text-[11px]"
                                            :class="itemTone(stage.status)"
                                            :title="stage.message"
                                        >
                                            {{ stage.name }}
                                        </span>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </section>

                    <section>
                        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                            <h4 class="text-xs font-semibold uppercase tracking-wide text-f5-text-muted dark:text-white/40">
                                Поля СКК
                            </h4>
                            <label class="flex cursor-pointer items-center gap-2 text-xs font-medium text-f5-text dark:text-white">
                                <input
                                    v-model="includeFields"
                                    type="checkbox"
                                    class="h-4 w-4"
                                    :disabled="exporting || !(preview.fields?.length)"
                                >
                                <span>Выгружать поля</span>
                            </label>
                        </div>

                        <p
                            v-if="!preview.fields?.length"
                            class="rounded-xl border border-dashed border-f5-border px-3 py-4 text-xs text-f5-text-muted dark:border-white/15 dark:text-white/45"
                        >
                            В проекте нет полей СКК для выгрузки.
                        </p>

                        <template v-else>
                            <div
                                v-if="includeFields"
                                class="mb-2 flex items-center justify-between gap-2 rounded-xl border border-f5-border bg-f5-surface-2/80 px-3 py-2 dark:border-white/10 dark:bg-white/5"
                            >
                                <label class="flex cursor-pointer items-center gap-2 text-xs font-medium text-f5-text dark:text-white">
                                    <input
                                        type="checkbox"
                                        class="h-4 w-4"
                                        :checked="allFieldsSelected"
                                        :indeterminate="someFieldsSelected"
                                        :disabled="exporting"
                                        @change="onSelectAllFieldsChange"
                                    >
                                    <span>Все поля</span>
                                </label>
                                <span class="text-xs text-f5-text-muted dark:text-white/45">
                                    {{ selectedFieldIds.length }} из {{ preview.fields.length }}
                                </span>
                            </div>

                            <div
                                class="grid gap-2 md:grid-cols-2"
                                :class="{ 'pointer-events-none opacity-50': !includeFields }"
                            >
                                <label
                                    v-for="field in preview.fields"
                                    :key="field.id"
                                    class="flex cursor-pointer gap-3 rounded-xl border p-3"
                                    :class="includeFields && selectedFieldIds.includes(field.id)
                                        ? itemTone(field.status)
                                        : 'border-f5-border bg-f5-surface-2 text-f5-text-muted dark:border-white/10 dark:bg-white/5 dark:text-white/55'"
                                >
                                    <input
                                        type="checkbox"
                                        class="mt-1 h-4 w-4 shrink-0"
                                        :checked="includeFields && selectedFieldIds.includes(field.id)"
                                        :disabled="exporting || !includeFields"
                                        @change="onFieldCheck(field.id, $event)"
                                    >
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold">{{ field.name }}</p>
                                                <p class="mt-0.5 text-xs opacity-70">
                                                    {{ field.entity_label }} · {{ field.value_type }}
                                                </p>
                                                <p class="mt-1 text-xs opacity-80">{{ field.message }}</p>
                                            </div>
                                            <span class="shrink-0 text-[10px] font-semibold uppercase tracking-wide">
                                                {{ itemLabel(field.status) }}
                                            </span>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </template>
                    </section>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-f5-border px-5 py-4 dark:border-white/10">
                    <p class="text-xs text-f5-text-muted dark:text-white/45">
                        Выбрано: воронок {{ selectedFunnelIds.length }},
                        полей {{ includeFields ? selectedFieldIds.length : 0 }}
                    </p>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            class="f5-btn f5-btn--ghost"
                            :disabled="exporting"
                            @click="previewOpen = false"
                        >
                            Отмена
                        </button>
                        <button
                            type="button"
                            class="f5-btn f5-btn--primary"
                            :disabled="exporting || !canExport"
                            @click="exportToAmo"
                        >
                            {{ exporting ? 'Выгрузка…' : 'Выгрузить выбранное' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>

    <Teleport to="body">
        <div
            v-if="auditModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-3 backdrop-blur-sm sm:p-5"
            role="dialog"
            aria-modal="true"
        >
            <div class="flex h-[92vh] w-full max-w-[min(96vw,92rem)] flex-col overflow-hidden rounded-2xl border border-f5-border bg-f5-surface shadow-2xl dark:border-white/10 dark:bg-f5-black">
                <div class="flex items-start justify-between gap-4 border-b border-f5-border px-5 py-4 dark:border-white/10 sm:px-6">
                    <div class="min-w-0">
                        <h3 class="text-lg font-semibold text-f5-text dark:text-white">
                            AI-аудит аккаунта amoCRM
                        </h3>
                        <p class="mt-1 text-sm text-f5-text-muted dark:text-white/50">
                            {{ amocrm.account_name || amocrm.subdomain }}.amocrm.ru
                            <span v-if="auditAt && !auditStreaming"> · {{ auditAt }}</span>
                            <span v-if="auditStreaming" class="text-f5-accent"> · ответ в реальном времени</span>
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-f5-text-muted transition-colors hover:bg-f5-surface-2 hover:text-f5-text dark:hover:bg-white/10 dark:hover:text-white"
                        @click="closeAuditModal"
                    >
                        ×
                    </button>
                </div>

                <div ref="auditScrollEl" class="min-h-0 flex-1 overflow-y-auto px-5 py-5 sm:px-8">
                    <div class="mx-auto max-w-5xl">
                        <p
                            v-if="auditStreaming && auditStatus"
                            class="mb-4 flex items-center gap-2 rounded-xl border border-f5-border bg-f5-surface-2 px-3 py-2 text-sm text-f5-text-muted dark:border-white/10 dark:bg-white/5 dark:text-white/60"
                        >
                            <span class="inline-block h-2 w-2 animate-pulse rounded-full bg-f5-accent"></span>
                            {{ auditStatus }}
                        </p>
                        <article class="whitespace-pre-wrap text-base leading-7 text-f5-text dark:text-white/90">
                            {{ auditResult }}<span
                                v-if="auditStreaming"
                                class="inline-block h-5 w-2.5 animate-pulse rounded-sm bg-f5-accent/70 align-text-bottom"
                            ></span>
                        </article>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2 border-t border-f5-border px-5 py-4 dark:border-white/10 sm:px-6">
                    <template v-if="auditStreaming">
                        <p class="mr-auto text-xs text-f5-text-muted dark:text-white/45">
                            ИИ пишет ответ — текст появляется в реальном времени.
                        </p>
                        <button
                            type="button"
                            class="f5-btn f5-btn--danger"
                            @click="closeAuditModal"
                        >
                            Остановить
                        </button>
                    </template>
                    <template v-else>
                        <a
                            :href="amocrm.urls.audit_docx"
                            class="f5-btn f5-btn--ghost"
                        >
                            Скачать DOCX
                        </a>
                        <button
                            type="button"
                            class="f5-btn f5-btn--ghost"
                            :disabled="googleDocsExporting"
                            @click="saveAuditToGoogleDocs"
                        >
                            {{ googleDocsExporting ? 'Сохранение…' : 'Google Docs' }}
                        </button>
                        <Link
                            v-if="aiChatUrl"
                            :href="aiChatUrl"
                            class="f5-btn f5-btn--primary"
                            preserve-scroll
                        >
                            Продолжить в ИИ-чате
                        </Link>
                        <button
                            type="button"
                            class="f5-btn f5-btn--ghost"
                            @click="closeAuditModal"
                        >
                            Закрыть
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </Teleport>

    <AmocrmPipelineImportModal
        :open="importOpen"
        :urls="amocrm.urls"
        @close="importOpen = false"
        @imported="onPipelinesImported"
    />

    <AmocrmAiSeedModal
        v-if="canEdit"
        :open="aiSeedOpen"
        :urls="amocrm.urls"
        :templates="amocrm.ai_seed_templates ?? []"
        @close="aiSeedOpen = false"
    />

    <AmocrmAuditScopeModal
        v-if="canEdit"
        :open="auditScopeOpen"
        :urls="amocrm.urls"
        :loading="auditLoading"
        @close="auditScopeOpen = false"
        @confirm="(scope) => runAiAudit(scope)"
    />

    <GoogleDocsModal
        :open="googleDocsOpen"
        :document="googleDoc"
        @close="closeGoogleDocsModal"
    />
</template>
