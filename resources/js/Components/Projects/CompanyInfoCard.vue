<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
    form: {
        type: Object,
        required: true,
    },
    canEdit: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['save']);

const isEditing = ref(false);

watch(() => props.project, (project) => {
    props.form.company_name = project.company_name;
    props.form.website = project.website ?? '';
    props.form.business_sphere = project.business_sphere ?? '';
    props.form.current_services = project.current_services ?? '';
    props.form.employees_count = project.employees_count ?? '';
    props.form.client_tasks = project.client_tasks ?? '';
    props.form.amocrm_deal_url = project.amocrm_deal_url ?? '';
}, { deep: true });

const websiteHref = computed(() => normalizeHttpUrl(props.project.website));

const amocrmDealHref = computed(() => normalizeHttpUrl(props.project.amocrm_deal_url));

function normalizeHttpUrl(value) {
    const url = typeof value === 'string' ? value.trim() : '';
    if (!url) {
        return null;
    }

    return /^https?:\/\//i.test(url) ? url : `https://${url}`;
}

function display(value, fallback = 'Не указано') {
    const text = typeof value === 'string' ? value.trim() : value;
    return text ? text : fallback;
}

function startEdit() {
    if (!props.canEdit) {
        return;
    }

    isEditing.value = true;
}

function cancelEdit() {
    isEditing.value = false;
    props.form.company_name = props.project.company_name;
    props.form.website = props.project.website ?? '';
    props.form.business_sphere = props.project.business_sphere ?? '';
    props.form.current_services = props.project.current_services ?? '';
    props.form.employees_count = props.project.employees_count ?? '';
    props.form.client_tasks = props.project.client_tasks ?? '';
    props.form.amocrm_deal_url = props.project.amocrm_deal_url ?? '';
    props.form.clearErrors();
}

function submit() {
    emit('save', () => {
        isEditing.value = false;
    });
}
</script>

<template>
    <section class="group relative overflow-hidden rounded-2xl border border-f5-border bg-f5-surface shadow-sm dark:border-white/10 dark:bg-f5-black">
        <div class="pointer-events-none absolute inset-y-0 left-0 w-1.5 bg-gradient-to-b from-f5-green to-f5-yellow" aria-hidden="true" />
        <div class="pointer-events-none absolute -right-16 -top-16 h-40 w-40 rounded-full bg-f5-yellow/10 blur-3xl" aria-hidden="true" />
        <div class="pointer-events-none absolute -bottom-20 left-1/3 h-44 w-44 rounded-full bg-f5-green/10 blur-3xl" aria-hidden="true" />

        <div
            v-if="!isEditing"
            class="relative p-6 pl-8 sm:p-8 sm:pl-10"
        >
            <div class="mb-6 flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-f5-text-muted dark:text-white/40">
                        Информация о компании
                    </p>
                    <h2 class="text-2xl font-bold tracking-tight text-f5-text dark:text-white sm:text-3xl">
                        {{ project.company_name }}
                    </h2>
                </div>

                <button
                    v-if="canEdit"
                    type="button"
                    class="f5-btn f5-btn--ghost f5-btn--sm shrink-0 opacity-0 transition-all duration-200 group-hover:opacity-100 focus:opacity-100"
                    @click="startEdit"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Редактировать
                </button>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <div class="rounded-xl border border-f5-border bg-f5-surface-2/80 p-4 dark:border-white/10 dark:bg-white/5">
                    <div class="mb-2 flex items-center gap-2">
                        <span class="f5-block-green flex h-7 w-7 items-center justify-center rounded-lg text-xs font-bold">👥</span>
                        <span class="text-xs font-semibold uppercase tracking-wide text-f5-text-muted dark:text-white/40">Сотрудников</span>
                    </div>
                    <p class="text-lg font-semibold text-f5-text dark:text-white">
                        {{ project.employees_count ?? '—' }}
                    </p>
                </div>

                <div class="rounded-xl border border-f5-border bg-f5-surface-2/80 p-4 dark:border-white/10 dark:bg-white/5">
                    <div class="mb-2 flex items-center gap-2">
                        <span class="f5-block-yellow flex h-7 w-7 items-center justify-center rounded-lg text-xs font-bold">🌐</span>
                        <span class="text-xs font-semibold uppercase tracking-wide text-f5-text-muted dark:text-white/40">Сайт</span>
                    </div>
                    <p class="text-sm font-medium text-f5-text dark:text-white">
                        <a
                            v-if="websiteHref"
                            :href="websiteHref"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-f5-text underline decoration-f5-green/50 underline-offset-2 hover:text-f5-green dark:text-white"
                        >
                            {{ project.website }}
                        </a>
                        <span v-else class="text-f5-text-muted dark:text-white/40">Не указан</span>
                    </p>
                </div>

                <div class="rounded-xl border border-f5-border bg-f5-surface-2/80 p-4 dark:border-white/10 dark:bg-white/5">
                    <div class="mb-2 flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-f5-black text-xs font-bold text-white dark:bg-f5-yellow dark:text-f5-black">🏢</span>
                        <span class="text-xs font-semibold uppercase tracking-wide text-f5-text-muted dark:text-white/40">Сфера бизнеса</span>
                    </div>
                    <p class="text-sm font-medium leading-relaxed text-f5-text dark:text-white/90">
                        {{ display(project.business_sphere) }}
                    </p>
                </div>

                <div class="rounded-xl border border-dashed border-f5-border bg-f5-surface-2/50 p-4 sm:col-span-2 xl:col-span-3 dark:border-white/15 dark:bg-white/[0.03]">
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#313942] text-[10px] font-bold text-white">amo</span>
                        <span class="text-xs font-semibold uppercase tracking-wide text-f5-text-muted dark:text-white/40">Сделка в amoCRM</span>
                        <span class="rounded-full border border-f5-border px-2 py-0.5 text-[10px] font-medium text-f5-text-muted dark:border-white/10 dark:text-white/45">
                            только для команды · не в ИИ и документах
                        </span>
                    </div>
                    <p class="text-sm font-medium text-f5-text dark:text-white">
                        <a
                            v-if="amocrmDealHref"
                            :href="amocrmDealHref"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="break-all text-f5-text underline decoration-f5-green/50 underline-offset-2 hover:text-f5-green dark:text-white"
                        >
                            {{ project.amocrm_deal_url }}
                        </a>
                        <span v-else class="text-f5-text-muted dark:text-white/40">Не указана</span>
                    </p>
                </div>

                <div class="rounded-xl border border-f5-border bg-f5-surface-2/80 p-4 sm:col-span-2 xl:col-span-3 dark:border-white/10 dark:bg-white/5">
                    <div class="mb-2 flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-f5-black text-xs font-bold text-white dark:bg-f5-green dark:text-f5-black">⚙</span>
                        <span class="text-xs font-semibold uppercase tracking-wide text-f5-text-muted dark:text-white/40">Сервисы сейчас</span>
                    </div>
                    <p class="line-clamp-3 text-sm leading-relaxed text-f5-text dark:text-white/90">
                        {{ display(project.current_services) }}
                    </p>
                </div>

                <div class="rounded-xl border border-f5-border bg-gradient-to-br from-f5-green/10 to-f5-yellow/10 p-4 sm:col-span-2 xl:col-span-3 dark:border-white/10">
                    <div class="mb-2 flex items-center gap-2">
                        <span class="f5-block-green flex h-7 w-7 items-center justify-center rounded-lg text-xs font-bold">🎯</span>
                        <span class="text-xs font-semibold uppercase tracking-wide text-f5-text-muted dark:text-white/40">Задачи клиента</span>
                    </div>
                    <p class="whitespace-pre-wrap text-sm leading-relaxed text-f5-text dark:text-white/90">
                        {{ display(project.client_tasks) }}
                    </p>
                </div>
            </div>
        </div>

        <div v-else class="relative border-t border-f5-border bg-f5-surface-2/50 p-6 pl-8 sm:p-8 sm:pl-10 dark:border-white/10 dark:bg-white/[0.02]">
            <div class="mb-6 flex items-center justify-between gap-4">
                <h3 class="text-lg font-semibold text-f5-text dark:text-white">
                    Редактирование
                </h3>
                <button type="button" class="f5-btn f5-btn--ghost f5-btn--sm" @click="cancelEdit">
                    Отмена
                </button>
            </div>

            <form class="space-y-5" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="company_name" class="f5-label">Название компании</label>
                        <input
                            id="company_name"
                            v-model="form.company_name"
                            type="text"
                            class="f5-input"
                            :class="{ 'f5-input--error': form.errors.company_name }"
                            required
                        >
                        <p v-if="form.errors.company_name" class="f5-error">{{ form.errors.company_name }}</p>
                    </div>

                    <div>
                        <label for="website" class="f5-label">Сайт</label>
                        <input
                            id="website"
                            v-model="form.website"
                            type="text"
                            class="f5-input"
                            :class="{ 'f5-input--error': form.errors.website }"
                            placeholder="example.ru"
                        >
                        <p v-if="form.errors.website" class="f5-error">{{ form.errors.website }}</p>
                    </div>

                    <div>
                        <label for="business_sphere" class="f5-label">Сфера бизнеса</label>
                        <input
                            id="business_sphere"
                            v-model="form.business_sphere"
                            type="text"
                            class="f5-input"
                            :class="{ 'f5-input--error': form.errors.business_sphere }"
                            placeholder="Например: производство, B2B-услуги, e-commerce"
                        >
                        <p v-if="form.errors.business_sphere" class="f5-error">{{ form.errors.business_sphere }}</p>
                    </div>

                    <div>
                        <label for="employees_count" class="f5-label">Количество сотрудников</label>
                        <input
                            id="employees_count"
                            v-model="form.employees_count"
                            type="number"
                            min="1"
                            class="f5-input"
                            :class="{ 'f5-input--error': form.errors.employees_count }"
                        >
                        <p v-if="form.errors.employees_count" class="f5-error">{{ form.errors.employees_count }}</p>
                    </div>

                    <div class="sm:col-span-2">
                        <label for="amocrm_deal_url" class="f5-label">Ссылка на сделку в amoCRM</label>
                        <input
                            id="amocrm_deal_url"
                            v-model="form.amocrm_deal_url"
                            type="text"
                            class="f5-input"
                            :class="{ 'f5-input--error': form.errors.amocrm_deal_url }"
                            placeholder="https://example.amocrm.ru/leads/detail/123"
                        >
                        <p class="mt-1 text-xs text-f5-text-muted dark:text-white/45">
                            Информационное поле для команды. Не попадает в ИИ, DOCX и Google Docs.
                        </p>
                        <p v-if="form.errors.amocrm_deal_url" class="f5-error">{{ form.errors.amocrm_deal_url }}</p>
                    </div>

                    <div class="sm:col-span-2">
                        <label for="current_services" class="f5-label">Какими сервисами пользуются сейчас</label>
                        <textarea
                            id="current_services"
                            v-model="form.current_services"
                            rows="3"
                            class="f5-input resize-y"
                            :class="{ 'f5-input--error': form.errors.current_services }"
                            placeholder="1С, МойСклад, Excel, WhatsApp..."
                        />
                        <p v-if="form.errors.current_services" class="f5-error">{{ form.errors.current_services }}</p>
                    </div>

                    <div class="sm:col-span-2">
                        <label for="client_tasks" class="f5-label">Задачи клиента</label>
                        <textarea
                            id="client_tasks"
                            v-model="form.client_tasks"
                            rows="5"
                            class="f5-input resize-y"
                            :class="{ 'f5-input--error': form.errors.client_tasks }"
                        />
                        <p v-if="form.errors.client_tasks" class="f5-error">{{ form.errors.client_tasks }}</p>
                    </div>
                </div>

                <button type="submit" class="f5-btn f5-btn--primary" :disabled="form.processing">
                    {{ form.processing ? 'Сохранение…' : 'Сохранить' }}
                </button>
            </form>
        </div>
    </section>
</template>
