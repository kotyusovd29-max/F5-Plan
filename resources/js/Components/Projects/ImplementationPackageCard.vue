<script setup>
import { computed, ref } from 'vue';
import GoogleDocsModal from '../GoogleDocsModal.vue';
import { useGoogleDocsExport } from '../../composables/useGoogleDocsExport';
import {
    ANNUAL_FREE_MONTHS,
    ANNUAL_PAID_MONTHS,
    ANNUAL_PERIOD_KEY,
    calculateIntegrationsCost,
    formatRub,
    MIN_BILLING_USERS,
} from '../../utils/widgetPricing';
import { calculateAmocrmLicenses } from '../../utils/amocrmLicensePricing';

const props = defineProps({
    workStages: {
        type: Array,
        default: () => [],
    },
    integrations: {
        type: Array,
        default: () => [],
    },
    catalog: {
        type: Object,
        default: () => ({ widgets: [] }),
    },
    employeesCount: {
        type: [Number, String],
        default: null,
    },
    amocrmLicenseTariff: {
        type: String,
        default: null,
    },
    amocrmLicenses: {
        type: Object,
        default: () => ({
            tariffs: [],
            source_url: 'https://www.amocrm.ru/buy/',
            source_note: '',
        }),
    },
    exportXlsxUrl: {
        type: String,
        default: null,
    },
    exportGoogleSheetsUrl: {
        type: String,
        default: null,
    },
    canEdit: {
        type: Boolean,
        default: true,
    },
});

const open = ref(false);
const periodMonths = ref(1);
const detailsOpen = ref(false);
const exportError = ref('');

const {
    googleDoc,
    googleDocsOpen,
    googleDocsExporting,
    closeGoogleDocsModal,
    exportToGoogleDocs,
} = useGoogleDocsExport();

const periodOptions = [
    { months: 1, label: '1 мес' },
    { months: 6, label: '6 мес' },
    { months: ANNUAL_PERIOD_KEY, label: `${ANNUAL_PAID_MONTHS}+${ANNUAL_FREE_MONTHS}` },
];

const usersCount = computed(() => {
    const parsed = Number.parseInt(String(props.employeesCount ?? ''), 10);

    return Number.isFinite(parsed) && parsed > 0 ? parsed : MIN_BILLING_USERS;
});

const implementationTotal = computed(() => (
    (props.workStages ?? []).reduce((sum, stage) => {
        const cost = Number(stage.cost);

        return sum + (Number.isFinite(cost) ? cost : 0);
    }, 0)
));

const stagesWithCost = computed(() => (
    (props.workStages ?? []).filter((stage) => {
        const cost = Number(stage.cost);

        return Number.isFinite(cost) && cost > 0;
    })
));

const stagesWithoutCostCount = computed(() => (
    Math.max(0, (props.workStages ?? []).length - stagesWithCost.value.length)
));

const widgetCost = computed(() => calculateIntegrationsCost({
    integrations: props.integrations,
    catalogWidgets: props.catalog?.widgets ?? [],
    usersCount: usersCount.value,
    months: periodMonths.value,
}));

const licenseCost = computed(() => {
    const estimate = calculateAmocrmLicenses({
        tariffs: props.amocrmLicenses?.tariffs ?? [],
        usersCount: props.employeesCount,
        months: periodMonths.value,
        sourceUrl: props.amocrmLicenses?.source_url,
        sourceNote: props.amocrmLicenses?.source_note,
    });

    const selectedSlug = String(props.amocrmLicenseTariff ?? '').trim();
    const selected = selectedSlug
        ? estimate.tariffs.find((tariff) => tariff.slug === selectedSlug) ?? null
        : null;

    return {
        ...estimate,
        selected,
        hasLicense: Boolean(selected),
        periodTotal: selected?.linePeriod ?? 0,
    };
});

const packageTotal = computed(() => (
    implementationTotal.value
    + (widgetCost.value.hasCalculable ? widgetCost.value.periodTotal : 0)
    + (licenseCost.value.hasLicense ? licenseCost.value.periodTotal : 0)
));

const hasAnyContent = computed(() => (
    (props.workStages?.length ?? 0) > 0
    || (props.integrations?.length ?? 0) > 0
    || licenseCost.value.hasLicense
));

const usesDefaultUsers = computed(() => {
    const value = props.employeesCount;

    return value === null || value === '' || value === undefined || Number(value) <= 0;
});

const triggerSummary = computed(() => {
    if (!hasAnyContent.value) {
        return 'Нет данных';
    }

    if (packageTotal.value > 0) {
        return formatRub(packageTotal.value);
    }

    return 'Без суммы';
});

function openModal() {
    open.value = true;
    exportError.value = '';
}

function closeModal() {
    open.value = false;
}

async function saveToGoogleSheets() {
    if (!props.exportGoogleSheetsUrl || !props.canEdit) {
        return;
    }

    exportError.value = '';

    try {
        await exportToGoogleDocs(props.exportGoogleSheetsUrl, {
            errorMessage: 'Не удалось сохранить в Google Таблицы.',
            connectMessage: 'Подключите Google в личном кабинете, чтобы сохранять смету в Google Таблицы.',
        });
    } catch (error) {
        exportError.value = error?.message || 'Не удалось сохранить в Google Таблицы.';
    }
}
</script>

<template>
    <div>
        <button
            type="button"
            class="flex w-full items-center justify-between gap-3 rounded-2xl border border-f5-green/30 bg-f5-green/5 px-5 py-4 text-left transition-colors hover:bg-f5-green/10 dark:border-f5-green/20 dark:bg-f5-green/5 dark:hover:bg-f5-green/10"
            @click="openModal"
        >
            <span class="min-w-0">
                <span class="block text-sm font-semibold text-f5-text dark:text-white">
                    Пакет внедрения
                </span>
                <span class="mt-0.5 block text-xs text-f5-text-muted dark:text-white/50">
                    Работы + лицензия amo + виджеты · {{ triggerSummary }}
                </span>
            </span>
            <span class="shrink-0 text-xs font-medium text-f5-accent">
                Открыть
            </span>
        </button>

        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="closeModal"
        >
            <div class="flex max-h-[85vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-f5-border bg-f5-surface shadow-xl dark:border-white/10 dark:bg-f5-black">
                <div class="flex items-start justify-between gap-3 border-b border-f5-border px-5 py-4 dark:border-white/10">
                    <div>
                        <h2 class="text-base font-semibold text-f5-text dark:text-white">
                            Пакет внедрения
                        </h2>
                        <p class="mt-0.5 text-xs text-f5-text-muted dark:text-white/50">
                            Разовые работы + лицензия amoCRM + подписка на виджеты
                        </p>
                    </div>
                    <button type="button" class="f5-btn f5-btn--ghost f5-btn--sm" @click="closeModal">
                        Закрыть
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-4">
                    <div v-if="!hasAnyContent" class="text-sm text-f5-text-muted dark:text-white/50">
                        Добавьте этапы работ, лицензию amoCRM или виджеты — здесь появится коммерческая картина пакета.
                    </div>

                    <div v-else class="space-y-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="text-xs text-f5-text-muted dark:text-white/50">
                                Период для лицензии и виджетов
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="option in periodOptions"
                                    :key="option.months"
                                    type="button"
                                    class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors"
                                    :class="periodMonths === option.months
                                        ? 'bg-f5-black text-white dark:bg-f5-green dark:text-f5-black'
                                        : 'border border-f5-border bg-f5-surface text-f5-text-muted hover:text-f5-text dark:border-white/15 dark:bg-f5-black dark:text-white/60 dark:hover:text-white'"
                                    @click="periodMonths = option.months"
                                >
                                    {{ option.label }}
                                </button>
                            </div>
                        </div>

                        <dl class="space-y-3">
                            <div class="flex items-baseline justify-between gap-4">
                                <dt class="text-sm text-f5-text-muted dark:text-white/60">
                                    Внедрение
                                    <span class="text-xs">(разово)</span>
                                </dt>
                                <dd class="text-sm font-semibold text-f5-text dark:text-white">
                                    <template v-if="implementationTotal > 0">
                                        {{ formatRub(implementationTotal) }}
                                    </template>
                                    <template v-else-if="workStages.length > 0">
                                        —
                                    </template>
                                    <template v-else>
                                        нет этапов
                                    </template>
                                </dd>
                            </div>

                            <div class="flex items-baseline justify-between gap-4">
                                <dt class="text-sm text-f5-text-muted dark:text-white/60">
                                    Лицензия amoCRM
                                    <span v-if="licenseCost.hasLicense" class="text-xs">
                                        ({{ licenseCost.selected.name }} · {{ licenseCost.periodLabel }})
                                    </span>
                                </dt>
                                <dd class="text-sm font-semibold text-f5-text dark:text-white">
                                    <template v-if="licenseCost.hasLicense">
                                        {{ formatRub(licenseCost.periodTotal) }}
                                    </template>
                                    <template v-else>
                                        не выбрана
                                    </template>
                                </dd>
                            </div>

                            <div class="flex items-baseline justify-between gap-4">
                                <dt class="text-sm text-f5-text-muted dark:text-white/60">
                                    Виджеты
                                    <span class="text-xs">({{ widgetCost.periodLabel }})</span>
                                </dt>
                                <dd class="text-sm font-semibold text-f5-text dark:text-white">
                                    <template v-if="widgetCost.hasCalculable">
                                        {{ formatRub(widgetCost.periodTotal) }}
                                    </template>
                                    <template v-else-if="integrations.length > 0">
                                        без фикс. цены
                                    </template>
                                    <template v-else>
                                        нет виджетов
                                    </template>
                                </dd>
                            </div>

                            <div class="flex items-baseline justify-between gap-4 border-t border-f5-border pt-3 dark:border-white/10">
                                <dt class="text-sm font-semibold text-f5-text dark:text-white">
                                    Итого пакета
                                </dt>
                                <dd class="text-xl font-bold text-f5-text dark:text-white">
                                    {{ formatRub(packageTotal) }}
                                </dd>
                            </div>
                        </dl>

                        <p
                            v-if="widgetCost.hasUserBasedLines || licenseCost.hasLicense"
                            class="text-xs text-f5-text-muted dark:text-white/50"
                        >
                            <template v-if="licenseCost.hasLicense">
                                Лицензия amoCRM: {{ licenseCost.billingUsers }} польз.
                            </template>
                            <template v-if="licenseCost.hasLicense && widgetCost.hasUserBasedLines">
                                ·
                            </template>
                            <template v-if="widgetCost.hasUserBasedLines">
                                <template v-if="usesDefaultUsers || widgetCost.usesMinimumUsers">
                                    Виджеты: {{ widgetCost.billingUsers }} польз. (мин. {{ MIN_BILLING_USERS }}).
                                </template>
                                <template v-else>
                                    Виджеты: {{ widgetCost.billingUsers }} польз.
                                </template>
                            </template>
                        </p>

                        <div>
                            <button
                                type="button"
                                class="flex w-full items-center gap-2 text-left text-xs font-medium text-f5-accent"
                                :aria-expanded="detailsOpen"
                                @click="detailsOpen = !detailsOpen"
                            >
                                <span
                                    class="transition-transform"
                                    :class="{ 'rotate-90': detailsOpen }"
                                    aria-hidden="true"
                                >▶</span>
                                {{ detailsOpen ? 'Скрыть расшифровку' : 'Показать расшифровку' }}
                            </button>

                            <div v-show="detailsOpen" class="mt-3 space-y-4">
                                <div>
                                    <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-f5-text-muted dark:text-white/40">
                                        Этапы работ
                                    </h4>
                                    <ul v-if="stagesWithCost.length > 0" class="space-y-1.5 text-sm">
                                        <li
                                            v-for="stage in stagesWithCost"
                                            :key="stage.id"
                                            class="flex justify-between gap-3"
                                        >
                                            <span class="min-w-0 truncate text-f5-text dark:text-white">{{ stage.name }}</span>
                                            <span class="shrink-0 text-f5-text-muted dark:text-white/60">
                                                {{ formatRub(Number(stage.cost)) }}
                                            </span>
                                        </li>
                                    </ul>
                                    <p v-else class="text-sm text-f5-text-muted dark:text-white/50">
                                        Нет этапов с указанной стоимостью.
                                    </p>
                                    <p
                                        v-if="stagesWithoutCostCount > 0"
                                        class="mt-1 text-xs text-f5-text-muted dark:text-white/40"
                                    >
                                        Ещё {{ stagesWithoutCostCount }} без суммы
                                    </p>
                                </div>

                                <div>
                                    <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-f5-text-muted dark:text-white/40">
                                        Лицензия amoCRM
                                    </h4>
                                    <ul v-if="licenseCost.hasLicense" class="space-y-1.5 text-sm">
                                        <li class="flex justify-between gap-3">
                                            <span class="min-w-0 truncate text-f5-text dark:text-white">
                                                {{ licenseCost.selected.name }}
                                                <span class="text-xs text-f5-text-muted dark:text-white/45">
                                                    · {{ licenseCost.selected.calcLabel }}
                                                </span>
                                            </span>
                                            <span class="shrink-0 text-f5-text-muted dark:text-white/60">
                                                {{ formatRub(licenseCost.periodTotal) }}
                                            </span>
                                        </li>
                                    </ul>
                                    <p v-else class="text-sm text-f5-text-muted dark:text-white/50">
                                        Тариф не выбран — укажите в блоке «Лицензия amoCRM».
                                    </p>
                                </div>

                                <div>
                                    <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-f5-text-muted dark:text-white/40">
                                        Виджеты
                                    </h4>
                                    <ul v-if="widgetCost.lines.length > 0" class="space-y-1.5 text-sm">
                                        <li
                                            v-for="line in widgetCost.lines"
                                            :key="line.name"
                                            class="flex justify-between gap-3"
                                        >
                                            <span class="min-w-0 truncate text-f5-text dark:text-white">{{ line.name }}</span>
                                            <span class="shrink-0 text-f5-text-muted dark:text-white/60">
                                                {{ formatRub(line.linePeriod) }}
                                            </span>
                                        </li>
                                    </ul>
                                    <p v-else class="text-sm text-f5-text-muted dark:text-white/50">
                                        Нет виджетов с фиксированной ценой.
                                    </p>
                                    <p
                                        v-if="widgetCost.skipped.length > 0"
                                        class="mt-1 text-xs text-f5-text-muted dark:text-white/40"
                                    >
                                        Без фикс. цены:
                                        {{ widgetCost.skipped.map((item) => item.name).join(', ') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-2 border-t border-f5-border px-5 py-4 dark:border-white/10">
                    <div class="flex flex-wrap gap-2">
                        <a
                            v-if="exportXlsxUrl"
                            :href="exportXlsxUrl"
                            class="f5-btn f5-btn--ghost f5-btn--sm"
                        >
                            Скачать XLSX
                        </a>
                        <button
                            v-if="canEdit && exportGoogleSheetsUrl"
                            type="button"
                            class="f5-btn f5-btn--primary f5-btn--sm"
                            :disabled="googleDocsExporting"
                            @click="saveToGoogleSheets"
                        >
                            {{ googleDocsExporting ? 'Сохранение…' : 'В Google Таблицы' }}
                        </button>
                    </div>
                    <p v-if="exportError" class="text-xs text-red-600 dark:text-red-400">
                        {{ exportError }}
                    </p>
                </div>
            </div>
        </div>

        <GoogleDocsModal
            :open="googleDocsOpen"
            :document="googleDoc"
            @close="closeGoogleDocsModal"
        />
    </div>
</template>
