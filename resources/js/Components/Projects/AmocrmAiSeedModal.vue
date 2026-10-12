<script setup>
import { computed, ref, watch } from 'vue';
import { postJson } from '../../utils/fetchJson';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    urls: {
        type: Object,
        required: true,
    },
    templates: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close']);

const prompt = ref('');
const error = ref('');
const success = ref('');
const generating = ref(false);
const applying = ref(false);
const preview = ref(null);

const canGenerate = computed(() => (
    prompt.value.trim().length >= 5 && !generating.value && !applying.value
));

const canApply = computed(() => (
    preview.value?.payload
    && (
        (preview.value.summary?.contacts ?? 0)
        + (preview.value.summary?.leads ?? 0)
        + (preview.value.summary?.links ?? 0)
    ) > 0
    && !generating.value
    && !applying.value
));

watch(
    () => props.open,
    (value) => {
        if (!value) {
            return;
        }

        error.value = '';
        success.value = '';
        preview.value = null;

        if (!prompt.value && props.templates?.[0]?.message) {
            prompt.value = props.templates[0].message;
        }
    },
);

function useTemplate(template) {
    prompt.value = template.message || '';
    preview.value = null;
    success.value = '';
    error.value = '';
}

function close() {
    if (generating.value || applying.value) {
        return;
    }

    emit('close');
}

async function generatePreview() {
    if (!canGenerate.value || !props.urls?.ai_seed_preview) {
        return;
    }

    generating.value = true;
    error.value = '';
    success.value = '';
    preview.value = null;

    try {
        const { response, data } = await postJson(props.urls.ai_seed_preview, {
            prompt: prompt.value.trim(),
        });

        if (!response.ok || !data.success) {
            throw new Error(data?.message || 'Не удалось подготовить данные через ИИ.');
        }

        preview.value = data;

        const total = (data.summary?.contacts ?? 0)
            + (data.summary?.leads ?? 0)
            + (data.summary?.links ?? 0);

        if (total === 0) {
            error.value = 'ИИ не подготовил сущности для создания. Уточните запрос.';
        }
    } catch (exception) {
        error.value = exception?.message || 'Не удалось подготовить данные через ИИ.';
    } finally {
        generating.value = false;
    }
}

async function applySeed() {
    if (!canApply.value || !props.urls?.ai_seed) {
        return;
    }

    applying.value = true;
    error.value = '';
    success.value = '';

    try {
        const { response, data } = await postJson(props.urls.ai_seed, {
            contacts: preview.value.payload?.contacts ?? [],
            leads: preview.value.payload?.leads ?? [],
            links: preview.value.payload?.links ?? [],
        });

        if (!response.ok || !data.success) {
            throw new Error(data?.message || 'Не удалось создать данные в amoCRM.');
        }

        success.value = data.message || 'Данные созданы в amoCRM.';
        preview.value = null;
    } catch (exception) {
        error.value = exception?.message || 'Не удалось создать данные в amoCRM.';
    } finally {
        applying.value = false;
    }
}

function fieldEntries(fields) {
    if (!fields || typeof fields !== 'object') {
        return [];
    }

    return Object.entries(fields);
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
                class="flex max-h-[min(92vh,820px)] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-f5-border bg-f5-surface shadow-2xl dark:border-white/10 dark:bg-f5-black"
                role="dialog"
                aria-modal="true"
                aria-labelledby="amocrm-ai-seed-title"
            >
                <div class="shrink-0 border-b border-f5-border px-5 py-4 dark:border-white/10">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2
                                id="amocrm-ai-seed-title"
                                class="text-base font-semibold text-f5-text dark:text-white"
                            >
                                Заполнить amoCRM через ИИ
                            </h2>
                            <p class="mt-0.5 text-xs text-f5-text-muted dark:text-white/50">
                                Тестовые данные с меткой «F5 demo». Можно создавать контакты/сделки и прикреплять существующие контакты из amoCRM.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="f5-btn f5-btn--ghost f5-btn--sm shrink-0"
                            :disabled="generating || applying"
                            @click="close"
                        >
                            Закрыть
                        </button>
                    </div>
                </div>

                <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-4">
                    <p
                        v-if="error"
                        class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300"
                    >
                        {{ error }}
                    </p>
                    <p
                        v-if="success"
                        class="rounded-xl border border-f5-green/35 bg-f5-green/10 px-3 py-2 text-sm text-f5-text dark:text-white"
                    >
                        {{ success }}
                    </p>

                    <div v-if="templates.length" class="flex flex-wrap gap-2">
                        <button
                            v-for="template in templates"
                            :key="template.id"
                            type="button"
                            class="rounded-lg border border-f5-border bg-f5-surface-2 px-2.5 py-1.5 text-xs font-medium text-f5-text transition hover:border-f5-green/40 dark:border-white/10 dark:bg-white/5 dark:text-white/80"
                            :disabled="generating || applying"
                            @click="useTemplate(template)"
                        >
                            {{ template.label }}
                        </button>
                    </div>

                    <label class="block">
                        <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-f5-text-muted dark:text-white/40">
                            Запрос к ИИ
                        </span>
                        <textarea
                            v-model="prompt"
                            rows="4"
                            class="w-full rounded-xl border border-f5-border bg-f5-surface-2 px-3 py-2.5 text-sm text-f5-text outline-none transition focus:border-f5-green/50 dark:border-white/10 dark:bg-white/5 dark:text-white"
                            placeholder="Например: Создай в amo 20 разных контактов и заполни дату рождения, телефон и email"
                            :disabled="generating || applying"
                        />
                    </label>

                    <div v-if="preview" class="space-y-3">
                        <div class="rounded-xl border border-f5-border bg-f5-surface-2 p-3 text-sm dark:border-white/10 dark:bg-white/5">
                            <p class="font-medium text-f5-text dark:text-white">
                                {{ preview.message }}
                            </p>
                            <p class="mt-1 text-xs text-f5-text-muted dark:text-white/50">
                                Контактов: {{ preview.summary?.contacts ?? 0 }},
                                сделок: {{ preview.summary?.leads ?? 0 }},
                                связей: {{ preview.summary?.links ?? 0 }}
                                <span v-if="preview.summary?.fields_used?.length">
                                    · поля: {{ preview.summary.fields_used.join(', ') }}
                                </span>
                            </p>
                        </div>

                        <section v-if="preview.contacts?.length">
                            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-f5-text-muted dark:text-white/40">
                                Контакты ({{ preview.contacts.length }})
                            </h3>
                            <div class="max-h-56 space-y-2 overflow-y-auto">
                                <article
                                    v-for="(contact, index) in preview.contacts"
                                    :key="`c-${index}`"
                                    class="rounded-xl border border-f5-border px-3 py-2.5 text-xs dark:border-white/10"
                                >
                                    <p class="font-semibold text-f5-text dark:text-white">
                                        {{ contact.name }}
                                    </p>
                                    <p class="mt-0.5 text-f5-text-muted dark:text-white/50">
                                        <span v-if="contact.phone">{{ contact.phone }}</span>
                                        <span v-if="contact.phone && contact.email"> · </span>
                                        <span v-if="contact.email">{{ contact.email }}</span>
                                    </p>
                                    <p
                                        v-for="([name, value]) in fieldEntries(contact.fields)"
                                        :key="name"
                                        class="mt-0.5 text-f5-text-muted dark:text-white/45"
                                    >
                                        {{ name }}: {{ value }}
                                    </p>
                                </article>
                            </div>
                        </section>

                        <section v-if="preview.leads?.length">
                            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-f5-text-muted dark:text-white/40">
                                Сделки ({{ preview.leads.length }})
                            </h3>
                            <div class="max-h-40 space-y-2 overflow-y-auto">
                                <article
                                    v-for="(lead, index) in preview.leads"
                                    :key="`l-${index}`"
                                    class="rounded-xl border border-f5-border px-3 py-2.5 text-xs dark:border-white/10"
                                >
                                    <p class="font-semibold text-f5-text dark:text-white">
                                        {{ lead.name }}
                                        <span v-if="lead.price != null" class="font-normal text-f5-text-muted dark:text-white/50">
                                            · {{ lead.price }} ₽
                                        </span>
                                    </p>
                                    <p
                                        v-if="lead.pipeline || lead.status || lead.contact_name"
                                        class="mt-0.5 text-f5-text-muted dark:text-white/50"
                                    >
                                        <span v-if="lead.pipeline">{{ lead.pipeline }}</span>
                                        <span v-if="lead.pipeline && lead.status"> → </span>
                                        <span v-if="lead.status">{{ lead.status }}</span>
                                        <span v-if="lead.contact_name">
                                            · контакт: {{ lead.contact_name }}
                                        </span>
                                    </p>
                                    <p
                                        v-for="([name, value]) in fieldEntries(lead.fields)"
                                        :key="name"
                                        class="mt-0.5 text-f5-text-muted dark:text-white/45"
                                    >
                                        {{ name }}: {{ value }}
                                    </p>
                                </article>
                            </div>
                        </section>

                        <section v-if="preview.links?.length">
                            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-f5-text-muted dark:text-white/40">
                                Привязка контактов ({{ preview.links.length }})
                            </h3>
                            <div class="max-h-40 space-y-2 overflow-y-auto">
                                <article
                                    v-for="(link, index) in preview.links"
                                    :key="`link-${index}`"
                                    class="rounded-xl border border-f5-border px-3 py-2.5 text-xs dark:border-white/10"
                                >
                                    <p class="font-semibold text-f5-text dark:text-white">
                                        {{ link.lead_name || (`Сделка #${link.lead_id}`) }}
                                    </p>
                                    <p class="mt-0.5 text-f5-text-muted dark:text-white/50">
                                        → {{ link.contact_name || (`Контакт #${link.contact_id}`) }}
                                    </p>
                                </article>
                            </div>
                        </section>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2 border-t border-f5-border px-5 py-4 dark:border-white/10">
                    <button
                        type="button"
                        class="f5-btn f5-btn--ghost"
                        :disabled="generating || applying"
                        @click="close"
                    >
                        Отмена
                    </button>
                    <button
                        type="button"
                        class="f5-btn f5-btn--ghost"
                        :disabled="!canGenerate"
                        @click="generatePreview"
                    >
                        {{ generating ? 'ИИ готовит…' : 'Предпросмотр' }}
                    </button>
                    <button
                        type="button"
                        class="f5-btn f5-btn--primary"
                        :disabled="!canApply"
                        @click="applySeed"
                    >
                        {{ applying ? 'Применяю…' : 'Применить в amoCRM' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
