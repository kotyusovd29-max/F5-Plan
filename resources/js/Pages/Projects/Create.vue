<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    starterPackages: {
        type: Array,
        default: () => [],
    },
});

const step = ref(1);
const totalSteps = 5;
/** @type {import('vue').Ref<'pending' | 'none' | number>} */
const templateChoice = ref('pending');

const form = useForm({
    starter_package_id: null,
    company_name: '',
    website: '',
    business_sphere: '',
    current_services: '',
    employees_count: '',
    client_tasks: '',
    amocrm_deal_url: '',
});

const steps = [
    { id: 1, title: 'Шаблон', hint: 'Готовый набор по сфере или пустой проект' },
    { id: 2, title: 'Компания', hint: 'Клиент, сайт и ссылка на сделку' },
    { id: 3, title: 'Контекст', hint: 'Сфера и размер команды' },
    { id: 4, title: 'Задачи', hint: 'Текущие сервисы и цели внедрения' },
    { id: 5, title: 'Проверка', hint: 'Проверьте данные и создайте план' },
];

const currentStep = computed(() => steps.find((item) => item.id === step.value) ?? steps[0]);

const selectedPackage = computed(() => {
    if (typeof templateChoice.value !== 'number') {
        return null;
    }

    return props.starterPackages.find((item) => item.id === templateChoice.value) ?? null;
});

const canGoNext = computed(() => {
    if (step.value === 1) {
        return templateChoice.value !== 'pending';
    }

    if (step.value === 2) {
        return form.company_name.trim().length > 0;
    }

    return true;
});

function selectPackage(pkg) {
    templateChoice.value = pkg.id;
    form.starter_package_id = pkg.id;

    if (!form.business_sphere.trim()) {
        form.business_sphere = pkg.business_sphere ?? '';
    }
}

function selectNoTemplate() {
    templateChoice.value = 'none';
    form.starter_package_id = null;
}

function nextStep() {
    if (!canGoNext.value || step.value >= totalSteps) {
        return;
    }

    step.value += 1;
}

function prevStep() {
    if (step.value <= 1) {
        return;
    }

    step.value -= 1;
}

function goToStep(target) {
    if (target < 1 || target > totalSteps) {
        return;
    }

    if (target > 1 && templateChoice.value === 'pending') {
        step.value = 1;
        return;
    }

    if (target > 2 && form.company_name.trim() === '') {
        step.value = 2;
        return;
    }

    step.value = target;
}

function submit() {
    if (templateChoice.value === 'pending') {
        step.value = 1;
        return;
    }

    if (form.company_name.trim() === '') {
        step.value = 2;
        return;
    }

    form.transform((data) => ({
        ...data,
        starter_package_id: templateChoice.value === 'none' ? null : data.starter_package_id,
    })).post('/projects');
}

function display(value, fallback = 'Не указано') {
    const text = typeof value === 'string' ? value.trim() : value;
    return text ? text : fallback;
}
</script>

<template>
    <Head title="Новый проект" />

    <AppLayout>
        <template #header>
            <div>
                <h1 class="text-xl font-bold text-f5-text dark:text-white">
                    Новый проект
                </h1>
                <p class="text-sm text-f5-text-muted dark:text-white/50">
                    Пошаговое создание плана внедрения
                </p>
            </div>
        </template>

        <div class="mx-auto max-w-2xl">
            <div class="f5-card overflow-hidden">
                <div class="flex">
                    <div class="w-2 shrink-0 bg-gradient-to-b from-f5-green to-f5-yellow" aria-hidden="true" />

                    <div class="flex-1 p-6 sm:p-8">
                        <div class="mb-6">
                            <Link
                                href="/"
                                class="text-sm text-f5-text-muted hover:text-f5-text dark:text-white/50 dark:hover:text-white"
                            >
                                ← К проектам
                            </Link>
                            <h2 class="mt-3 text-2xl font-bold text-f5-text dark:text-white">
                                {{ currentStep.title }}
                            </h2>
                            <p class="mt-2 text-sm text-f5-text-muted dark:text-white/50">
                                Шаг {{ step }} из {{ totalSteps }} · {{ currentStep.hint }}
                            </p>
                        </div>

                        <ol class="mb-8 grid grid-cols-5 gap-1.5 sm:gap-2">
                            <li
                                v-for="item in steps"
                                :key="item.id"
                            >
                                <button
                                    type="button"
                                    class="w-full rounded-xl border px-1.5 py-2 text-left transition-colors sm:px-2"
                                    :class="item.id === step
                                        ? 'border-f5-green bg-f5-green/15 dark:bg-f5-green/20'
                                        : item.id < step
                                            ? 'border-f5-border bg-f5-surface-2 dark:border-white/10 dark:bg-white/5'
                                            : 'border-f5-border dark:border-white/10'"
                                    @click="goToStep(item.id)"
                                >
                                    <span class="block text-[10px] uppercase tracking-wide text-f5-text-muted dark:text-white/40">
                                        {{ item.id }}
                                    </span>
                                    <span class="mt-0.5 block truncate text-[11px] font-semibold text-f5-text dark:text-white sm:text-xs">
                                        {{ item.title }}
                                    </span>
                                </button>
                            </li>
                        </ol>

                        <form class="space-y-5" @submit.prevent="step === totalSteps ? submit() : nextStep()">
                            <template v-if="step === 1">
                                <p class="text-sm text-f5-text-muted dark:text-white/55">
                                    Выберите готовый набор по сфере или продолжите без шаблона — структуру можно собрать вручную и через ИИ.
                                </p>

                                <button
                                    type="button"
                                    class="w-full rounded-2xl border px-4 py-4 text-left transition"
                                    :class="templateChoice === 'none'
                                        ? 'border-f5-green bg-f5-green/10 dark:bg-f5-green/15'
                                        : 'border-f5-border hover:border-f5-green/50 dark:border-white/10'"
                                    @click="selectNoTemplate"
                                >
                                    <span class="block text-sm font-semibold text-f5-text dark:text-white">
                                        Без шаблона
                                    </span>
                                    <span class="mt-1 block text-xs text-f5-text-muted dark:text-white/50">
                                        Пустой проект — воронки, поля и виджеты добавите сами
                                    </span>
                                </button>

                                <div v-if="starterPackages.length === 0" class="rounded-xl border border-dashed border-f5-border px-4 py-6 text-sm text-f5-text-muted dark:border-white/10 dark:text-white/45">
                                    Активных стартовых пакетов пока нет. Администратор может добавить их в разделе «Стартовые пакеты».
                                </div>

                                <div class="space-y-3">
                                    <button
                                        v-for="pkg in starterPackages"
                                        :key="pkg.id"
                                        type="button"
                                        class="w-full rounded-2xl border px-4 py-4 text-left transition"
                                        :class="templateChoice === pkg.id
                                            ? 'border-f5-green bg-f5-green/10 dark:bg-f5-green/15'
                                            : 'border-f5-border hover:border-f5-green/50 dark:border-white/10'"
                                        @click="selectPackage(pkg)"
                                    >
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <span class="text-sm font-semibold text-f5-text dark:text-white">
                                                {{ pkg.name }}
                                            </span>
                                            <span class="rounded-full bg-f5-surface-2 px-2 py-0.5 text-[11px] font-medium text-f5-text-muted dark:bg-white/5 dark:text-white/50">
                                                {{ pkg.business_sphere }}
                                            </span>
                                        </div>
                                        <p
                                            v-if="pkg.description"
                                            class="mt-2 text-xs leading-relaxed text-f5-text-muted dark:text-white/50"
                                        >
                                            {{ pkg.description }}
                                        </p>
                                        <p class="mt-2 text-[11px] text-f5-text-muted dark:text-white/45">
                                            {{ pkg.funnels_count }} вор. ·
                                            {{ pkg.skk_fields_count }} СКК ·
                                            {{ pkg.integrations_count }} видж. ·
                                            {{ pkg.work_stages_count }} этапов
                                        </p>
                                    </button>
                                </div>
                                <p v-if="form.errors.starter_package_id" class="f5-error">
                                    {{ form.errors.starter_package_id }}
                                </p>
                            </template>

                            <template v-else-if="step === 2">
                                <div>
                                    <label for="company_name" class="f5-label">Название компании</label>
                                    <input
                                        id="company_name"
                                        v-model="form.company_name"
                                        type="text"
                                        class="f5-input"
                                        :class="{ 'f5-input--error': form.errors.company_name }"
                                        placeholder="Например: ООО «Ромашка»"
                                        required
                                        autofocus
                                    >
                                    <p v-if="form.errors.company_name" class="f5-error">
                                        {{ form.errors.company_name }}
                                    </p>
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
                                    <p v-if="form.errors.website" class="f5-error">
                                        {{ form.errors.website }}
                                    </p>
                                </div>

                                <div>
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
                                        Только для команды. Не уходит в ИИ и документы.
                                    </p>
                                    <p v-if="form.errors.amocrm_deal_url" class="f5-error">
                                        {{ form.errors.amocrm_deal_url }}
                                    </p>
                                </div>
                            </template>

                            <template v-else-if="step === 3">
                                <div>
                                    <label for="business_sphere" class="f5-label">Сфера бизнеса</label>
                                    <input
                                        id="business_sphere"
                                        v-model="form.business_sphere"
                                        type="text"
                                        class="f5-input"
                                        :class="{ 'f5-input--error': form.errors.business_sphere }"
                                        placeholder="Например: производство, B2B-услуги, e-commerce"
                                        autofocus
                                    >
                                    <p
                                        v-if="selectedPackage"
                                        class="mt-1 text-xs text-f5-text-muted dark:text-white/45"
                                    >
                                        Подставлено из пакета «{{ selectedPackage.name }}» — можно изменить.
                                    </p>
                                    <p v-if="form.errors.business_sphere" class="f5-error">
                                        {{ form.errors.business_sphere }}
                                    </p>
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
                                        placeholder="Сколько человек будет работать в CRM"
                                    >
                                    <p v-if="form.errors.employees_count" class="f5-error">
                                        {{ form.errors.employees_count }}
                                    </p>
                                </div>
                            </template>

                            <template v-else-if="step === 4">
                                <div>
                                    <label for="current_services" class="f5-label">Какими сервисами пользуются сейчас</label>
                                    <textarea
                                        id="current_services"
                                        v-model="form.current_services"
                                        rows="3"
                                        class="f5-input resize-y"
                                        :class="{ 'f5-input--error': form.errors.current_services }"
                                        placeholder="1С, МойСклад, Excel, WhatsApp..."
                                        autofocus
                                    />
                                    <p v-if="form.errors.current_services" class="f5-error">
                                        {{ form.errors.current_services }}
                                    </p>
                                </div>

                                <div>
                                    <label for="client_tasks" class="f5-label">Задачи клиента</label>
                                    <textarea
                                        id="client_tasks"
                                        v-model="form.client_tasks"
                                        rows="5"
                                        class="f5-input resize-y"
                                        :class="{ 'f5-input--error': form.errors.client_tasks }"
                                        placeholder="Какие задачи должен решить amoCRM для этого клиента..."
                                    />
                                    <p v-if="form.errors.client_tasks" class="f5-error">
                                        {{ form.errors.client_tasks }}
                                    </p>
                                </div>
                            </template>

                            <template v-else>
                                <dl class="space-y-3 rounded-xl border border-f5-border bg-f5-surface-2 p-4 text-sm dark:border-white/10 dark:bg-white/5">
                                    <div>
                                        <dt class="text-f5-text-muted dark:text-white/50">Шаблон</dt>
                                        <dd class="mt-0.5 font-medium text-f5-text dark:text-white">
                                            {{ selectedPackage ? selectedPackage.name : 'Без шаблона' }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-f5-text-muted dark:text-white/50">Компания</dt>
                                        <dd class="mt-0.5 font-medium text-f5-text dark:text-white">{{ display(form.company_name) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-f5-text-muted dark:text-white/50">Сайт</dt>
                                        <dd class="mt-0.5 font-medium text-f5-text dark:text-white">{{ display(form.website) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-f5-text-muted dark:text-white/50">Сделка в amoCRM</dt>
                                        <dd class="mt-0.5 break-all font-medium text-f5-text dark:text-white">{{ display(form.amocrm_deal_url) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-f5-text-muted dark:text-white/50">Сфера</dt>
                                        <dd class="mt-0.5 font-medium text-f5-text dark:text-white">{{ display(form.business_sphere) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-f5-text-muted dark:text-white/50">Сотрудники</dt>
                                        <dd class="mt-0.5 font-medium text-f5-text dark:text-white">{{ display(form.employees_count) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-f5-text-muted dark:text-white/50">Текущие сервисы</dt>
                                        <dd class="mt-0.5 whitespace-pre-wrap font-medium text-f5-text dark:text-white">{{ display(form.current_services) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-f5-text-muted dark:text-white/50">Задачи клиента</dt>
                                        <dd class="mt-0.5 whitespace-pre-wrap font-medium text-f5-text dark:text-white">{{ display(form.client_tasks) }}</dd>
                                    </div>
                                </dl>
                                <p class="text-sm text-f5-text-muted dark:text-white/50">
                                    <template v-if="selectedPackage">
                                        После создания в проект попадут воронки, СКК, виджеты и этапы работ из пакета — их можно править.
                                    </template>
                                    <template v-else>
                                        После создания откроется страница проекта: добавьте воронки, поля и при необходимости запустите ИИ.
                                    </template>
                                </p>
                            </template>

                            <div class="flex flex-wrap gap-2 pt-2">
                                <button
                                    v-if="step > 1"
                                    type="button"
                                    class="f5-btn f5-btn--ghost"
                                    @click="prevStep"
                                >
                                    Назад
                                </button>
                                <button
                                    v-if="step < totalSteps"
                                    type="submit"
                                    class="f5-btn f5-btn--primary ml-auto"
                                    :disabled="!canGoNext"
                                >
                                    Далее
                                </button>
                                <button
                                    v-else
                                    type="submit"
                                    class="f5-btn f5-btn--primary ml-auto !px-6"
                                    :disabled="form.processing || !canGoNext"
                                >
                                    {{ form.processing ? 'Создание…' : 'Создать проект' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
