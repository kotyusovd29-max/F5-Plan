<script setup>
import { computed, ref, watch } from 'vue';
import {
    ANNUAL_FREE_MONTHS,
    ANNUAL_PAID_MONTHS,
    ANNUAL_PERIOD_KEY,
    calculateIntegrationsCost,
    formatRub,
    MIN_BILLING_USERS,
} from '../../utils/widgetPricing';

const props = defineProps({
    projectId: {
        type: [Number, String],
        required: true,
    },
    integrations: {
        type: Array,
        required: true,
    },
    catalog: {
        type: Object,
        required: true,
    },
    employeesCount: {
        type: [Number, String],
        default: null,
    },
});

const storageKey = computed(() => `f5plan:integrations-cost-expanded:${props.projectId}`);

function readExpandedState() {
    try {
        const stored = localStorage.getItem(storageKey.value);

        if (stored === '0') {
            return false;
        }

        if (stored === '1') {
            return true;
        }
    } catch {
        // ignore
    }

    return true;
}

const expanded = ref(readExpandedState());
const periodMonths = ref(1);
const usersCount = ref(normalizeUsers(props.employeesCount));

const periodOptions = [
    { months: 1, label: '1 мес' },
    { months: 6, label: '6 мес' },
    { months: ANNUAL_PERIOD_KEY, label: `${ANNUAL_PAID_MONTHS}+${ANNUAL_FREE_MONTHS}` },
];

watch(
    () => props.employeesCount,
    (value) => {
        if (value !== null && value !== '' && value !== undefined) {
            usersCount.value = normalizeUsers(value);
        }
    },
);

watch(expanded, (value) => {
    try {
        localStorage.setItem(storageKey.value, value ? '1' : '0');
    } catch {
        // ignore
    }
});

function normalizeUsers(value) {
    const parsed = Number.parseInt(String(value ?? ''), 10);

    return Number.isFinite(parsed) && parsed > 0 ? parsed : MIN_BILLING_USERS;
}

const cost = computed(() => calculateIntegrationsCost({
    integrations: props.integrations,
    catalogWidgets: props.catalog.widgets ?? [],
    usersCount: usersCount.value,
    months: periodMonths.value,
}));

const usesDefaultUsers = computed(() => {
    const value = props.employeesCount;

    return value === null || value === '' || value === undefined || Number(value) <= 0;
});

const periodLabel = computed(() => cost.value.periodLabel);

const periodColumnLabel = computed(() => {
    if (periodMonths.value === 1) {
        return 'В месяц';
    }

    return `За ${cost.value.periodShortLabel}`;
});

const collapsedSummary = computed(() => {
    if (props.integrations.length === 0) {
        return 'Нет виджетов';
    }

    if (!cost.value.hasCalculable) {
        return `${props.integrations.length} виджетов · без фиксированной цены`;
    }

    return `${formatRub(cost.value.periodTotal)} ${periodLabel.value}`;
});

function toggleExpanded() {
    expanded.value = !expanded.value;
}
</script>

<template>
    <section class="border-b border-f5-border bg-f5-surface-2/60 dark:border-white/10 dark:bg-white/5">
        <button
            type="button"
            class="flex w-full items-center gap-3 px-5 py-4 text-left transition-colors hover:bg-f5-surface-muted/60 dark:hover:bg-white/5"
            :aria-expanded="expanded"
            @click="toggleExpanded"
        >
            <span
                class="shrink-0 text-f5-text-muted transition-transform dark:text-white/40"
                :class="{ 'rotate-90': expanded }"
                aria-hidden="true"
            >▶</span>

            <span class="min-w-0 flex-1">
                <span class="block text-sm font-semibold text-f5-text dark:text-white">
                    Расчёт стоимости
                </span>
                <span
                    v-if="!expanded"
                    class="mt-0.5 block truncate text-xs text-f5-text-muted dark:text-white/50"
                >
                    {{ collapsedSummary }}
                </span>
            </span>

            <span class="shrink-0 text-xs text-f5-accent">
                {{ expanded ? 'Свернуть' : 'Развернуть' }}
            </span>
        </button>

        <div v-show="expanded" class="border-t border-f5-border px-5 py-4 dark:border-white/10">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <p class="text-xs text-f5-text-muted dark:text-white/50">
                    Учитываются разные модели: за пользователя, за канал (Chatter), фиксированные пакеты (1С)
                    и свои сервисы (подписка или единовременная сумма).
                    Годовой период считается как {{ ANNUAL_PAID_MONTHS }} оплачиваемых + {{ ANNUAL_FREE_MONTHS }} бесплатных месяцев.
                </p>

                <div class="flex flex-wrap gap-2" @click.stop>
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

            <p
                v-if="integrations.length === 0"
                class="mt-4 text-sm text-f5-text-muted dark:text-white/50"
            >
                Добавьте виджеты из каталога F5 — здесь появится расчёт стоимости.
            </p>

            <template v-else>
                <div class="mt-4 flex flex-wrap items-end gap-4">
                    <div
                        v-if="cost.hasUserBasedLines"
                        class="w-full max-w-[10rem]"
                        @click.stop
                    >
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
                            Укажите число сотрудников в основной информации проекта
                        </p>
                        <p
                            v-else-if="cost.usesMinimumUsers"
                            class="mt-1 text-xs text-amber-700 dark:text-amber-300"
                        >
                            В расчёте используется минимум {{ MIN_BILLING_USERS }} пользователей
                        </p>
                    </div>

                    <div v-if="cost.hasCalculable" class="min-w-0 flex-1">
                        <p class="text-xs uppercase tracking-wide text-f5-text-muted dark:text-white/40">
                            Итого {{ periodLabel }}
                        </p>
                        <p class="text-2xl font-bold text-f5-text dark:text-white">
                            {{ formatRub(cost.periodTotal) }}
                        </p>
                        <p
                            v-if="periodMonths > 1"
                            class="mt-0.5 text-xs text-f5-text-muted dark:text-white/50"
                        >
                            <template v-if="cost.isAnnualBonus">
                                Оплата за {{ cost.paidMonths }} мес. · {{ cost.freeMonths }} мес. бесплатно
                            </template>
                            <template v-else>
                                {{ formatRub(cost.monthlyTotal) }} / мес
                            </template>
                        </p>
                    </div>

                    <p
                        v-else
                        class="text-sm text-f5-text-muted dark:text-white/50"
                    >
                        Нет виджетов с фиксированной помесячной ценой для расчёта.
                    </p>
                </div>

                <div v-if="cost.lines.length > 0" class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[32rem] text-left text-sm">
                        <thead>
                            <tr class="border-b border-f5-border text-xs uppercase tracking-wide text-f5-text-muted dark:border-white/10 dark:text-white/40">
                                <th class="pb-2 pr-3 font-medium">Виджет</th>
                                <th class="pb-2 pr-3 font-medium">В каталоге</th>
                                <th class="pb-2 pr-3 font-medium">Расчёт</th>
                                <th class="pb-2 font-medium text-right">{{ periodColumnLabel }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="line in cost.lines"
                                :key="line.name"
                                class="border-b border-f5-border/70 last:border-0 dark:border-white/5"
                            >
                                <td class="py-2 pr-3 font-medium text-f5-text dark:text-white">
                                    {{ line.name }}
                                </td>
                                <td class="py-2 pr-3 text-f5-text-muted dark:text-white/60">
                                    {{ line.priceLabel }}
                                </td>
                                <td class="py-2 pr-3 text-f5-text-muted dark:text-white/60">
                                    {{ line.calcLabel }}
                                </td>
                                <td class="py-2 text-right font-medium text-f5-text dark:text-white">
                                    {{ formatRub(line.linePeriod) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p
                    v-if="cost.skipped.length > 0"
                    class="mt-3 text-xs text-f5-text-muted dark:text-white/50"
                >
                    Не учтено в сумме:
                    <span
                        v-for="(item, index) in cost.skipped"
                        :key="`${item.name}-${index}`"
                    >
                        {{ item.name }} ({{ item.reason }})<template v-if="index < cost.skipped.length - 1">; </template>
                    </span>
                </p>
            </template>
        </div>
    </section>
</template>
