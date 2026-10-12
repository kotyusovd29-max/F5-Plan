<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import AdminNav from '../../../Components/Admin/AdminNav.vue';

defineProps({
    dashboard: {
        type: Object,
        required: true,
    },
});

const refreshing = ref(false);

const summaryCards = [
    { key: 'users_total', label: 'Пользователи' },
    { key: 'admins_count', label: 'Администраторы' },
    { key: 'rops_count', label: 'РОПы' },
    { key: 'employees_count', label: 'Сотрудники' },
    { key: 'employees_without_rop', label: 'Без РОПа' },
    { key: 'online_now', label: 'Сейчас на сайте' },
];

const projectCards = [
    { key: 'projects_draft', label: 'Черновики' },
    { key: 'projects_pending', label: 'На согласовании' },
    { key: 'projects_approved', label: 'Согласованы' },
    { key: 'projects_implementation', label: 'В внедрении' },
    { key: 'projects_archived', label: 'Архив' },
    { key: 'projects_overdue', label: 'Просрочено по SLA' },
];

function refresh() {
    refreshing.value = true;
    router.post('/admin/dashboard/refresh', {}, {
        preserveScroll: true,
        onFinish: () => {
            refreshing.value = false;
        },
    });
}

function roleClass(role) {
    if (role === 'admin') {
        return 'f5-block-yellow';
    }

    if (role === 'rop') {
        return 'border border-f5-green/40 bg-f5-green/15 text-f5-text dark:text-white';
    }

    return 'bg-f5-surface-2 text-f5-text-muted dark:bg-white/10 dark:text-white/60';
}
</script>

<template>
    <Head title="Дашборд пользователей" />

    <AppLayout>
        <template #header>
            <div>
                <h1 class="text-xl font-bold text-f5-text dark:text-white">
                    Админ-панель
                </h1>
                <p class="text-sm text-f5-text-muted dark:text-white/50">
                    Сводка по команде
                </p>
            </div>
        </template>

        <div class="mx-auto max-w-7xl">
            <AdminNav active="dashboard" />

            <PageHeader
                title="Дашборд пользователей"
                :subtitle="`Снимок от ${dashboard.captured_at}. Учебный проект в расчёт не входит.`"
            >
                <template #actions>
                    <button
                        type="button"
                        class="f5-btn f5-btn--primary"
                        :disabled="refreshing"
                        @click="refresh"
                    >
                        {{ refreshing ? 'Обновление…' : 'Обновить снимок' }}
                    </button>
                </template>
            </PageHeader>

            <section class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <article
                    v-for="card in summaryCards"
                    :key="card.key"
                    class="f5-card px-4 py-3"
                >
                    <p class="text-xs text-f5-text-muted dark:text-white/50">
                        {{ card.label }}
                    </p>
                    <p class="mt-1 text-2xl font-semibold text-f5-text dark:text-white">
                        {{ dashboard.summary[card.key] ?? 0 }}
                    </p>
                </article>
            </section>

            <section class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <article class="f5-card px-4 py-3">
                    <p class="text-xs text-f5-text-muted dark:text-white/50">Время на сайте сегодня</p>
                    <p class="mt-1 text-2xl font-semibold text-f5-text dark:text-white">
                        {{ dashboard.summary.online_today_label }}
                    </p>
                </article>
                <article class="f5-card px-4 py-3">
                    <p class="text-xs text-f5-text-muted dark:text-white/50">Время на сайте всего</p>
                    <p class="mt-1 text-2xl font-semibold text-f5-text dark:text-white">
                        {{ dashboard.summary.online_total_label }}
                    </p>
                </article>
                <article class="f5-card px-4 py-3">
                    <p class="text-xs text-f5-text-muted dark:text-white/50">Запросы и ответы ИИ</p>
                    <p class="mt-1 text-2xl font-semibold text-f5-text dark:text-white">
                        {{ dashboard.summary.ai_requests ?? 0 }}
                        <span class="text-base font-medium text-f5-text-muted dark:text-white/50">
                            / {{ dashboard.summary.ai_responses ?? 0 }}
                        </span>
                    </p>
                </article>
            </section>

            <section class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <article
                    v-for="card in projectCards"
                    :key="card.key"
                    class="f5-card px-4 py-3"
                >
                    <p class="text-xs text-f5-text-muted dark:text-white/50">
                        {{ card.label }}
                    </p>
                    <p class="mt-1 text-2xl font-semibold text-f5-text dark:text-white">
                        {{ dashboard.summary[card.key] ?? 0 }}
                    </p>
                </article>
            </section>

            <section v-if="dashboard.departments?.length" class="mb-6 grid gap-3 md:grid-cols-2">
                <article
                    v-for="department in dashboard.departments"
                    :key="department.title"
                    class="f5-card px-4 py-3"
                >
                    <h2 class="text-sm font-semibold text-f5-text dark:text-white">
                        {{ department.title }}
                    </h2>
                    <p class="mt-2 text-sm text-f5-text-muted dark:text-white/60">
                        Людей: {{ department.users_count }},
                        на сайте: {{ department.online_now }},
                        сегодня: {{ department.online_today_label }}.
                        Планов: {{ department.projects_total }},
                        просрочено: {{ department.projects_overdue }},
                        запросов к ИИ: {{ department.ai_requests }}.
                    </p>
                </article>
            </section>

            <section class="f5-card overflow-hidden">
                <div class="border-b border-f5-border px-4 py-3 dark:border-white/10">
                    <h2 class="text-base font-semibold text-f5-text dark:text-white">
                        По людям
                    </h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-f5-border bg-f5-surface-2/80 dark:border-white/10 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-f5-text dark:text-white">Человек</th>
                                <th class="px-4 py-3 text-left font-semibold text-f5-text dark:text-white">Роль</th>
                                <th class="px-4 py-3 text-left font-semibold text-f5-text dark:text-white">На сайте</th>
                                <th class="px-4 py-3 text-left font-semibold text-f5-text dark:text-white">Сегодня</th>
                                <th class="px-4 py-3 text-left font-semibold text-f5-text dark:text-white">Планы</th>
                                <th class="px-4 py-3 text-left font-semibold text-f5-text dark:text-white">Просрочки</th>
                                <th class="px-4 py-3 text-left font-semibold text-f5-text dark:text-white">ИИ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-f5-border dark:divide-white/10">
                            <tr
                                v-for="user in dashboard.users"
                                :key="user.user_id"
                                class="hover:bg-f5-surface-2/60 dark:hover:bg-white/[0.02]"
                            >
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2 font-medium text-f5-text dark:text-white">
                                        <span
                                            class="inline-block h-2 w-2 shrink-0 rounded-full"
                                            :class="user.is_online ? 'bg-emerald-500' : 'bg-f5-border dark:bg-white/20'"
                                        />
                                        {{ user.name }}
                                    </div>
                                    <p class="mt-0.5 text-xs text-f5-text-muted dark:text-white/50">
                                        {{ user.email }}
                                        <span v-if="user.rop_name"> · РОП: {{ user.rop_name }}</span>
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-block rounded-md px-2 py-0.5 text-xs font-semibold"
                                        :class="roleClass(user.role)"
                                    >
                                        {{ user.role_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-f5-text-muted dark:text-white/60">
                                    {{ user.last_seen_label }}
                                </td>
                                <td class="px-4 py-3 text-f5-text dark:text-white/80">
                                    {{ user.online_today_label }}
                                    <span class="block text-xs text-f5-text-muted dark:text-white/40">
                                        всего {{ user.online_total_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-f5-text dark:text-white/80">
                                    {{ user.projects_total }}
                                    <span class="block text-xs text-f5-text-muted dark:text-white/40">
                                        черн. {{ user.projects_draft }},
                                        согл. {{ user.projects_pending }},
                                        ок {{ user.projects_approved }},
                                        внедр. {{ user.projects_implementation }},
                                        архив {{ user.projects_archived }}
                                        <template v-if="user.editor_projects">
                                            · редактор {{ user.editor_projects }}
                                        </template>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span :class="user.projects_overdue > 0 ? 'font-semibold text-amber-700 dark:text-amber-300' : 'text-f5-text-muted dark:text-white/50'">
                                        {{ user.projects_overdue }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-f5-text dark:text-white/80">
                                    {{ user.ai_requests }} / {{ user.ai_responses }}
                                    <span v-if="user.last_ai_at" class="block text-xs text-f5-text-muted dark:text-white/40">
                                        {{ user.last_ai_thread_label }}
                                        <template v-if="user.last_ai_project"> · {{ user.last_ai_project }}</template>
                                        · {{ user.last_ai_at }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
