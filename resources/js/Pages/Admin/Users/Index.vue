<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import AdminNav from '../../../Components/Admin/AdminNav.vue';

defineProps({
    groups: {
        type: Array,
        required: true,
    },
});

const page = usePage();

function deleteUser(url) {
    if (!window.confirm('Удалить пользователя?')) {
        return;
    }

    router.delete(url);
}
</script>

<template>
    <Head title="Пользователи" />

    <AppLayout>
        <template #header>
            <div>
                <h1 class="text-xl font-bold text-f5-text dark:text-white">
                    Админ-панель
                </h1>
                <p class="text-sm text-f5-text-muted dark:text-white/50">
                    Управление доступом
                </p>
            </div>
        </template>

        <div class="mx-auto max-w-6xl">
            <AdminNav active="users" />

            <PageHeader
                title="Пользователи"
                subtitle="Сотрудники сгруппированы по РОП. Время на сайте считается, пока открыта вкладка."
            >
                <template #actions>
                    <Link href="/admin/users/create" class="f5-btn f5-btn--primary">
                        Добавить пользователя
                    </Link>
                </template>
            </PageHeader>

            <div v-if="groups.length === 0" class="f5-card px-4 py-10 text-center text-f5-text-muted dark:text-white/50">
                Пользователей пока нет
            </div>

            <div v-else class="space-y-5">
                <section
                    v-for="group in groups"
                    :key="group.key"
                    class="f5-card overflow-hidden"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-f5-border px-4 py-3 dark:border-white/10">
                        <div>
                            <h2 class="text-base font-semibold text-f5-text dark:text-white">
                                {{ group.title }}
                            </h2>
                            <p v-if="group.subtitle" class="mt-0.5 text-xs text-f5-text-muted dark:text-white/50">
                                {{ group.subtitle }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2 text-xs">
                            <span class="rounded-md bg-f5-surface-2 px-2.5 py-1 font-medium text-f5-text dark:bg-white/10 dark:text-white/80">
                                Сегодня: {{ group.team_online_today_label }}
                            </span>
                            <span class="rounded-md border border-f5-green/40 bg-f5-green/15 px-2.5 py-1 font-medium text-f5-text dark:text-white">
                                Всего: {{ group.team_online_total_label }}
                            </span>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="border-b border-f5-border bg-f5-surface-2/80 dark:border-white/10 dark:bg-white/5">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-f5-text dark:text-white">Имя</th>
                                    <th class="px-4 py-3 text-left font-semibold text-f5-text dark:text-white">Email</th>
                                    <th class="px-4 py-3 text-left font-semibold text-f5-text dark:text-white">Роль</th>
                                    <th class="px-4 py-3 text-left font-semibold text-f5-text dark:text-white">На сайте</th>
                                    <th class="px-4 py-3 text-left font-semibold text-f5-text dark:text-white">Сегодня</th>
                                    <th class="px-4 py-3 text-left font-semibold text-f5-text dark:text-white">Всего</th>
                                    <th class="px-4 py-3 text-right font-semibold text-f5-text dark:text-white" />
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-f5-border dark:divide-white/10">
                                <tr
                                    v-for="user in group.users"
                                    :key="user.id"
                                    class="hover:bg-f5-surface-2/60 dark:hover:bg-white/[0.02]"
                                >
                                    <td class="px-4 py-3 font-medium text-f5-text dark:text-white">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="inline-block h-2 w-2 shrink-0 rounded-full"
                                                :class="user.is_online ? 'bg-emerald-500' : 'bg-f5-border dark:bg-white/20'"
                                                :title="user.last_seen_label"
                                            />
                                            <span>{{ user.name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-f5-text-muted dark:text-white/60">
                                        {{ user.email }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            v-if="user.role === 'admin'"
                                            class="f5-block-yellow inline-block rounded-md px-2 py-0.5 text-xs font-semibold"
                                        >
                                            {{ user.role_label }}
                                        </span>
                                        <span
                                            v-else-if="user.role === 'rop'"
                                            class="inline-block rounded-md border border-f5-green/40 bg-f5-green/15 px-2 py-0.5 text-xs font-semibold text-f5-text dark:text-white"
                                        >
                                            {{ user.role_label }}
                                        </span>
                                        <span
                                            v-else
                                            class="inline-block rounded-md bg-f5-surface-2 px-2 py-0.5 text-xs font-medium text-f5-text-muted dark:bg-white/10 dark:text-white/60"
                                        >
                                            {{ user.role_label }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-f5-text-muted dark:text-white/60">
                                        <span
                                            :class="user.is_online ? 'font-medium text-emerald-700 dark:text-emerald-400' : ''"
                                        >
                                            {{ user.last_seen_label }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-medium text-f5-text dark:text-white/80">
                                        {{ user.online_today_label }}
                                    </td>
                                    <td class="px-4 py-3 font-medium text-f5-text dark:text-white/80">
                                        {{ user.online_total_label }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-2">
                                            <Link
                                                :href="`/admin/users/${user.id}/edit`"
                                                class="f5-btn f5-btn--ghost !px-3 !py-1.5 text-xs"
                                            >
                                                Изменить
                                            </Link>
                                            <button
                                                v-if="user.id !== page.props.auth.user?.id"
                                                type="button"
                                                class="f5-btn !border-red-200 !px-3 !py-1.5 text-xs text-red-600 hover:bg-red-50 dark:!border-red-900/50 dark:text-red-400 dark:hover:bg-red-950/30"
                                                @click="deleteUser(`/admin/users/${user.id}`)"
                                            >
                                                Удалить
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
