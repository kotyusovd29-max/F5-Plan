<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    ANNUAL_FREE_MONTHS,
    ANNUAL_PAID_MONTHS,
    ANNUAL_PERIOD_KEY,
    formatRub,
} from '../../utils/widgetPricing';
import {
    calculateAmocrmLicenses,
    MIN_AMOCRM_LICENSE_USERS,
} from '../../utils/amocrmLicensePricing';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
    employeesCount: {
        type: [Number, String],
        default: null,
    },
    licenses: {
        type: Object,
        default: () => ({
            tariffs: [],
            source_url: '',
            source_note: '',
        }),
    },
    canEdit: {
        type: Boolean,
        default: true,
    },
});

const periodMonths = ref(ANNUAL_PERIOD_KEY);
const usersCount = ref(normalizeUsers(props.employeesCount));
const selectedSlug = ref(props.project.amocrm_license_tariff || '');
const saving = ref(false);

const periodOptions = [
    { months: 1, label: '1 мес' },
    { months: 6, label: '6 мес' },
    { months: ANNUAL_PERIOD_KEY, label: `${ANNUAL_PAID_MONTHS}+${ANNUAL_FREE_MONTHS}` },
];

const tariffOptions = computed(() => props.licenses?.tariffs ?? []);

watch(
    () => props.employeesCount,
    (value) => {
        if (value !== null && value !== '' && value !== undefined) {
            usersCount.value = normalizeUsers(value);
        }
    },
);

watch(
    () => props.project.amocrm_license_tariff,
    (value) => {
        selectedSlug.value = value || '';
    },
);

function normalizeUsers(value) {
    const parsed = Number.parseInt(String(value ?? ''), 10);

    return Number.isFinite(parsed) && parsed > 0 ? parsed : MIN_AMOCRM_LICENSE_USERS;
}

const cost = computed(() => calculateAmocrmLicenses({
    tariffs: tariffOptions.value,
    usersCount: usersCount.value,
    months: periodMonths.value,
    sourceUrl: props.licenses?.source_url,
    sourceNote: props.licenses?.source_note,
}));

const selectedTariff = computed(() => (
    cost.value.tariffs.find((tariff) => tariff.slug === selectedSlug.value) ?? null
));

const usesDefaultUsers = computed(() => {
    const value = props.employeesCount;

    return value === null || value === '' || value === undefined || Number(value) <= 0;
});

function selectTariff(slug) {
    if (!props.canEdit || saving.value) {
        return;
    }

    if (selectedSlug.value === slug) {
        return;
    }

    const previous = selectedSlug.value;
    selectedSlug.value = slug;
    saving.value = true;

    router.put(props.project.urls.amocrm_license_update, {
        amocrm_license_tariff: slug,
    }, {
        preserveScroll: true,
        onError: () => {
            selectedSlug.value = previous;
        },
        onFinish: () => {
            saving.value = false;
        },
    });
}
</script>

<template>
    <div class="space-y-4 p-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0 space-y-1">
                <p class="text-xs text-f5-text-muted dark:text-white/50">
                    Выберите тариф — стоимость посчитается от числа пользователей и периода подписки.
                </p>
                <a
                    :href="cost.sourceUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex text-xs font-medium text-f5-accent hover:underline"
                >
                    amocrm.ru/buy
                </a>
            </div>
        </div>

        <div>
            <p class="mb-2 text-xs font-medium uppercase tracking-wide text-f5-text-muted dark:text-white/40">
                Тариф
            </p>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="tariff in tariffOptions"
                    :key="tariff.slug"
                    type="button"
                    class="rounded-lg px-3 py-2 text-sm font-semibold transition-colors"
                    :disabled="!canEdit || saving"
                    :class="selectedSlug === tariff.slug
                        ? 'bg-f5-black text-white dark:bg-f5-green dark:text-f5-black'
                        : 'border border-f5-border bg-f5-surface text-f5-text-muted hover:text-f5-text dark:border-white/15 dark:bg-f5-black dark:text-white/60 dark:hover:text-white'"
                    @click="selectTariff(tariff.slug)"
                >
                    {{ tariff.name }}
                    <span class="ml-1.5 font-normal opacity-80">
                        {{ formatRub(tariff.price_per_user) }}/мес
                    </span>
                </button>
            </div>
        </div>

        <template v-if="selectedTariff">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="flex flex-wrap items-end gap-4">
                    <div class="w-full max-w-[10rem]">
                        <label class="f5-label">Пользователей</label>
                        <input
                            v-model.number="usersCount"
                            type="number"
                            min="1"
                            class="f5-input"
                        >
                        <p
                            v-if="usesDefaultUsers"
                            class="mt-1 text-xs text-f5-text-muted dark:text-white/50"
                        >
                            Из сотрудников проекта (или {{ MIN_AMOCRM_LICENSE_USERS }} польз.)
                        </p>
                    </div>

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

                <div class="min-w-0 text-left lg:text-right">
                    <p class="text-xs uppercase tracking-wide text-f5-text-muted dark:text-white/40">
                        {{ selectedTariff.name }} · {{ cost.periodLabel }}
                    </p>
                    <p class="text-2xl font-bold text-f5-text dark:text-white">
                        {{ formatRub(selectedTariff.linePeriod) }}
                    </p>
                    <p class="mt-0.5 text-xs text-f5-text-muted dark:text-white/50">
                        {{ selectedTariff.calcLabel }}
                    </p>
                </div>
            </div>
        </template>

        <p
            v-else
            class="rounded-xl border border-dashed border-f5-border px-4 py-6 text-center text-sm text-f5-text-muted dark:border-white/15 dark:text-white/50"
        >
            Выберите тариф, чтобы увидеть стоимость лицензии.
        </p>
    </div>
</template>
