<script setup>
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { buildGeneratePreviewSections, buildGenerateSummary } from '../../utils/projectAi';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
    open: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close']);

const step = ref('form');
const status = ref('');
const statusType = ref('');
const generating = ref(false);
const applying = ref(false);
const currentProposal = ref(null);
const currentSections = ref([]);

const form = reactive({
    company_name: props.project.company_name,
    employees_count: props.project.employees_count ?? '',
    client_tasks: props.project.client_tasks ?? '',
    extra_instructions: '',
    sections: {
        skk_fields: true,
        integrations: true,
        funnels: true,
    },
});

const selectedSections = computed(() => Object.entries(form.sections)
    .filter(([, checked]) => checked)
    .map(([key]) => key));

const summaryParts = computed(() => buildGenerateSummary(
    currentProposal.value?._summary ?? {},
    currentSections.value,
));

const previewSections = computed(() => {
    if (!currentProposal.value) {
        return [];
    }

    const { _summary, ...proposal } = currentProposal.value;

    return buildGeneratePreviewSections(proposal, currentSections.value);
});

function close() {
    step.value = 'form';
    status.value = '';
    currentProposal.value = null;
    emit('close');
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function generate() {
    if (selectedSections.value.length === 0) {
        status.value = 'Выберите хотя бы один раздел для генерации.';
        statusType.value = 'error';
        return;
    }

    generating.value = true;
    status.value = '';
    statusType.value = '';
    step.value = 'loading';

    try {
        const response = await fetch(props.project.urls.ai_generate, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({
                company_name: form.company_name,
                employees_count: form.employees_count ? Number(form.employees_count) : null,
                client_tasks: form.client_tasks,
                extra_instructions: form.extra_instructions,
                sections: selectedSections.value,
            }),
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Не удалось получить ответ от ИИ.');
        }

        currentSections.value = [...selectedSections.value];
        currentProposal.value = {
            ...data.proposal,
            _summary: data.summary ?? {},
        };
        step.value = 'preview';
    } catch (error) {
        step.value = 'form';
        status.value = error.message || 'Ошибка при обращении к ИИ.';
        statusType.value = 'error';
    } finally {
        generating.value = false;
    }
}

async function applyProposal() {
    if (!currentProposal.value) {
        return;
    }

    applying.value = true;
    status.value = '';

    const { _summary, ...proposal } = currentProposal.value;

    try {
        const response = await fetch(props.project.urls.ai_apply, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({
                proposal,
                sections: currentSections.value,
            }),
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Не удалось применить предложение.');
        }

        router.visit(data.redirect || props.project.urls.show, {
            preserveScroll: true,
        });
    } catch (error) {
        status.value = error.message || 'Ошибка при применении.';
        statusType.value = 'error';
        applying.value = false;
    }
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
            role="dialog"
            aria-modal="true"
            aria-labelledby="ai-quick-fill-title"
        >
            <button
                type="button"
                class="absolute inset-0 bg-black/50"
                aria-label="Закрыть"
                @click="close"
            />

            <div class="relative flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-f5-border bg-f5-surface shadow-xl dark:border-white/10 dark:bg-f5-black">
                <header class="flex items-start justify-between gap-4 border-b border-f5-border px-5 py-4 dark:border-white/10">
                    <div>
                        <h3 id="ai-quick-fill-title" class="text-lg font-semibold text-f5-text dark:text-white">
                            Заполнить с ИИ
                        </h3>
                        <p class="mt-1 text-sm text-f5-text-muted dark:text-white/50">
                            F5AI предложит поля, интеграции и воронки по данным компании
                        </p>
                    </div>
                    <button type="button" class="f5-btn f5-btn--ghost f5-btn--sm !min-w-8" @click="close">×</button>
                </header>

                <div class="overflow-y-auto px-5 py-4">
                    <form v-if="step === 'form'" class="space-y-4" @submit.prevent="generate">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="f5-label">Название компании</label>
                                <input v-model="form.company_name" type="text" class="f5-input" required>
                            </div>
                            <div>
                                <label class="f5-label">Количество сотрудников</label>
                                <input v-model="form.employees_count" type="number" class="f5-input" min="1">
                            </div>
                        </div>

                        <div>
                            <label class="f5-label">Задачи клиента</label>
                            <textarea v-model="form.client_tasks" rows="3" class="f5-input resize-y" />
                        </div>

                        <div>
                            <label class="f5-label">Дополнительные требования</label>
                            <textarea
                                v-model="form.extra_instructions"
                                rows="2"
                                class="f5-input resize-y"
                                placeholder="Например: B2B-продажи, 2 воронки, поля для бюджета и источника лида"
                            />
                        </div>

                        <fieldset>
                            <legend class="f5-label">Что сгенерировать</legend>
                            <div class="mt-2 space-y-2 text-sm text-f5-text dark:text-white/80">
                                <label class="flex items-center gap-2">
                                    <input v-model="form.sections.skk_fields" type="checkbox" class="rounded border-f5-border dark:border-white/20">
                                    Поля (сделки, контакты, компании)
                                </label>
                                <label class="flex items-center gap-2">
                                    <input v-model="form.sections.integrations" type="checkbox" class="rounded border-f5-border dark:border-white/20">
                                    Интеграции и виджеты
                                </label>
                                <label class="flex items-center gap-2">
                                    <input v-model="form.sections.funnels" type="checkbox" class="rounded border-f5-border dark:border-white/20">
                                    Воронки с этапами
                                </label>
                            </div>
                        </fieldset>

                        <p class="text-xs text-f5-text-muted dark:text-white/50">
                            Новые элементы будут добавлены к существующим, ничего не удалится.
                        </p>

                        <div class="flex justify-end gap-2">
                            <button type="button" class="f5-btn f5-btn--ghost" @click="close">Отмена</button>
                            <button type="submit" class="f5-btn f5-btn--primary" :disabled="generating">
                                Сгенерировать
                            </button>
                        </div>
                    </form>

                    <div v-else-if="step === 'loading'" class="py-10 text-center">
                        <div class="ai-spinner mx-auto mb-4" aria-hidden="true" />
                        <p class="text-sm font-medium text-f5-text dark:text-white">ИИ анализирует данные компании…</p>
                        <p class="mt-1 text-xs text-f5-text-muted dark:text-white/50">Обычно это занимает до минуты</p>
                    </div>

                    <div v-else class="space-y-4">
                        <p v-if="summaryParts.length > 0" class="text-sm text-f5-text dark:text-white">
                            Будет добавлено: <strong>{{ summaryParts.join(', ') }}</strong>
                        </p>
                        <p v-else class="text-sm text-f5-text-muted dark:text-white/50">
                            ИИ не предложил элементов для выбранных разделов.
                        </p>

                        <div v-for="section in previewSections" :key="section.title" class="rounded-xl border border-f5-border p-4 dark:border-white/10">
                            <h4 class="text-sm font-semibold text-f5-text dark:text-white">{{ section.title }}</h4>
                            <p v-if="section.description" class="mt-1 text-xs text-f5-text-muted dark:text-white/50">{{ section.description }}</p>
                            <ul class="mt-3 space-y-2 text-sm">
                                <li v-for="item in section.items" :key="`${section.title}-${item.name}`">
                                    <span class="font-medium text-f5-text dark:text-white">{{ item.name }}</span>
                                    <span v-if="item.meta" class="text-f5-text-muted dark:text-white/50"> — {{ item.meta }}</span>
                                </li>
                            </ul>
                        </div>

                        <div class="flex justify-end gap-2">
                            <button type="button" class="f5-btn f5-btn--ghost" @click="step = 'form'">Назад</button>
                            <button type="button" class="f5-btn f5-btn--primary" :disabled="applying" @click="applyProposal">
                                {{ applying ? 'Применение…' : 'Применить в проект' }}
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    v-if="status"
                    class="border-t px-5 py-2.5 text-sm"
                    :class="statusType === 'error'
                        ? 'border-red-200 bg-red-50 text-red-700 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300'
                        : 'border-f5-border bg-f5-surface-2 text-f5-text dark:border-white/10 dark:bg-white/5 dark:text-white'"
                >
                    {{ status }}
                </div>
            </div>
        </div>
    </Teleport>
</template>
