<script setup>
defineProps({
    dashboard: {
        type: Object,
        required: true,
    },
});

function formatPct(value) {
    if (value === null || value === undefined) {
        return '—';
    }

    return `${value}%`;
}
</script>

<template>
    <section class="mb-6 space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-f5-text dark:text-white">
                    Панель команды
                </h2>
                <p class="mt-0.5 text-sm text-f5-text-muted dark:text-white/50">
                    Воронка статусов, SLA и загрузка · {{ dashboard.total_active }} активных планов
                </p>
            </div>
            <p
                v-if="dashboard.conversion_to_implementation_pct != null"
                class="text-sm text-f5-text-muted dark:text-white/50"
            >
                До внедрения:
                <span class="font-semibold text-f5-text dark:text-white">
                    {{ formatPct(dashboard.conversion_to_implementation_pct) }}
                </span>
            </p>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <a
                v-for="(step, index) in dashboard.funnel"
                :key="step.status"
                :href="step.filter_url"
                class="rounded-2xl border border-f5-border bg-f5-surface p-4 no-underline transition-colors hover:border-f5-green dark:border-white/10 dark:bg-f5-black dark:hover:border-f5-green"
            >
                <p class="text-xs font-semibold uppercase tracking-wide text-f5-text-muted dark:text-white/40">
                    {{ step.label }}
                </p>
                <p class="mt-2 text-3xl font-bold text-f5-text dark:text-white">
                    {{ step.count }}
                </p>
                <p v-if="index > 0" class="mt-1 text-xs text-f5-text-muted dark:text-white/50">
                    <template v-if="step.from_previous_pct != null">
                        {{ formatPct(step.from_previous_pct) }} от предыдущего
                    </template>
                    <template v-else>
                        нет базы для конверсии
                    </template>
                </p>
                <p v-else class="mt-1 text-xs text-f5-text-muted dark:text-white/50">
                    Старт воронки
                </p>
            </a>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-2xl border border-f5-border bg-f5-surface p-5 dark:border-white/10 dark:bg-f5-black">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-f5-text dark:text-white">
                            SLA по статусам
                        </h3>
                        <p class="mt-0.5 text-xs text-f5-text-muted dark:text-white/50">
                            Дней в текущем статусе сверх порога
                        </p>
                    </div>
                    <span
                        class="rounded-md px-2 py-0.5 text-xs font-semibold"
                        :class="dashboard.sla.overdue_count > 0
                            ? 'bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-200'
                            : 'bg-f5-green/15 text-f5-text dark:text-white'"
                    >
                        {{ dashboard.sla.overdue_count }} просрочено
                    </span>
                </div>

                <ul class="mt-4 space-y-2 text-sm">
                    <li
                        v-for="row in dashboard.sla.avg_days_by_status"
                        :key="row.status"
                        class="flex items-center justify-between gap-3"
                    >
                        <span class="text-f5-text-muted dark:text-white/60">{{ row.label }}</span>
                        <span class="font-medium text-f5-text dark:text-white">
                            <template v-if="row.avg_days != null">
                                ср. {{ row.avg_days }} дн. · {{ row.count }} шт.
                            </template>
                            <template v-else>
                                —
                            </template>
                        </span>
                    </li>
                </ul>

                <p class="mt-3 text-[11px] text-f5-text-muted dark:text-white/40">
                    Пороги:
                    <template v-for="(item, index) in dashboard.sla.thresholds" :key="item.status">
                        {{ item.label }} {{ item.days }}д<template v-if="index < dashboard.sla.thresholds.length - 1"> · </template>
                    </template>
                </p>

                <div v-if="dashboard.sla.overdue_projects?.length" class="mt-4 border-t border-f5-border pt-3 dark:border-white/10">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-f5-text-muted dark:text-white/40">
                        Просроченные
                    </p>
                    <ul class="space-y-1.5">
                        <li
                            v-for="item in dashboard.sla.overdue_projects"
                            :key="item.id"
                        >
                            <a
                                :href="item.url"
                                class="flex items-center justify-between gap-2 rounded-lg px-2 py-1.5 text-sm no-underline hover:bg-f5-surface-2 dark:hover:bg-white/5"
                            >
                                <span class="min-w-0 truncate font-medium text-f5-text dark:text-white">
                                    {{ item.company_name }}
                                </span>
                                <span class="shrink-0 text-xs text-amber-700 dark:text-amber-300">
                                    {{ item.days }}д · {{ item.status_label }}
                                </span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="rounded-2xl border border-f5-border bg-f5-surface p-5 dark:border-white/10 dark:bg-f5-black">
                <h3 class="text-sm font-semibold text-f5-text dark:text-white">
                    Загрузка сотрудников
                </h3>
                <p class="mt-0.5 text-xs text-f5-text-muted dark:text-white/50">
                    Активные планы по статусам
                </p>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-f5-border text-xs uppercase tracking-wide text-f5-text-muted dark:border-white/10 dark:text-white/40">
                                <th class="pb-2 pr-3 font-medium">Сотрудник</th>
                                <th class="pb-2 pr-2 font-medium text-right">Всего</th>
                                <th class="pb-2 pr-2 font-medium text-right">Черн.</th>
                                <th class="pb-2 pr-2 font-medium text-right">Согл.</th>
                                <th class="pb-2 pr-2 font-medium text-right">ОК</th>
                                <th class="pb-2 pr-2 font-medium text-right">Внедр.</th>
                                <th class="pb-2 font-medium text-right">SLA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in dashboard.employees"
                                :key="row.id"
                                class="border-b border-f5-border/70 last:border-0 dark:border-white/5"
                            >
                                <td class="py-2 pr-3">
                                    <a
                                        :href="row.filter_url"
                                        class="font-medium text-f5-text no-underline hover:text-f5-accent dark:text-white"
                                    >
                                        {{ row.name }}
                                    </a>
                                </td>
                                <td class="py-2 pr-2 text-right font-semibold text-f5-text dark:text-white">
                                    {{ row.total }}
                                </td>
                                <td class="py-2 pr-2 text-right text-f5-text-muted dark:text-white/60">
                                    {{ row.draft }}
                                </td>
                                <td class="py-2 pr-2 text-right text-f5-text-muted dark:text-white/60">
                                    {{ row.pending_approval }}
                                </td>
                                <td class="py-2 pr-2 text-right text-f5-text-muted dark:text-white/60">
                                    {{ row.approved }}
                                </td>
                                <td class="py-2 pr-2 text-right text-f5-text-muted dark:text-white/60">
                                    {{ row.in_implementation }}
                                </td>
                                <td
                                    class="py-2 text-right font-medium"
                                    :class="row.overdue > 0
                                        ? 'text-amber-700 dark:text-amber-300'
                                        : 'text-f5-text-muted dark:text-white/50'"
                                >
                                    {{ row.overdue }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</template>
