<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import PaginationLinks from '../../../Components/PaginationLinks.vue';
import AdminNav from '../../../Components/Admin/AdminNav.vue';

defineProps({
    packages: {
        type: Object,
        required: true,
    },
});

const page = usePage();

function destroy(url) {
    if (!window.confirm('Удалить стартовый пакет?')) {
        return;
    }

    router.delete(url);
}
</script>

<template>
    <Head title="Стартовые пакеты" />

    <AppLayout>
        <template #header>
            <div>
                <h1 class="text-xl font-bold text-f5-text dark:text-white">Админ-панель</h1>
                <p class="text-sm text-f5-text-muted dark:text-white/50">Готовые наборы по сфере</p>
            </div>
        </template>

        <div class="mx-auto max-w-6xl">
            <AdminNav active="starter-packages" />

            <PageHeader
                title="Стартовые пакеты"
                subtitle="Полный набор: воронки, СКК, виджеты и этапы работ — выбирается при создании проекта"
            >
                <template #actions>
                    <Link href="/admin/starter-packages/create" class="f5-btn f5-btn--primary">
                        Новый пакет
                    </Link>
                </template>
            </PageHeader>

            <div v-if="page.props.flash?.success" class="mb-4 rounded-lg bg-f5-green/15 px-4 py-3 text-sm">
                {{ page.props.flash.success }}
            </div>

            <div class="f5-card overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-f5-border bg-f5-surface-2 dark:border-white/10 dark:bg-white/5">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Пакет</th>
                            <th class="px-4 py-3 text-left font-semibold">Сфера</th>
                            <th class="px-4 py-3 text-left font-semibold">Состав</th>
                            <th class="px-4 py-3 text-left font-semibold">Статус</th>
                            <th class="px-4 py-3 text-right font-semibold" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-f5-border dark:divide-white/10">
                        <tr v-if="packages.data.length === 0">
                            <td colspan="5" class="px-4 py-8 text-center text-f5-text-muted dark:text-white/50">
                                Пакетов пока нет — создайте первый набор для сферы.
                            </td>
                        </tr>
                        <tr v-for="item in packages.data" :key="item.id">
                            <td class="px-4 py-3">
                                <div class="font-medium text-f5-text dark:text-white">{{ item.name }}</div>
                                <div v-if="item.description" class="mt-0.5 line-clamp-2 text-xs text-f5-text-muted dark:text-white/40">
                                    {{ item.description }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-f5-text dark:text-white/80">
                                {{ item.business_sphere }}
                            </td>
                            <td class="px-4 py-3 text-xs text-f5-text-muted dark:text-white/55">
                                {{ item.funnels_count }} вор. ·
                                {{ item.skk_fields_count }} СКК ·
                                {{ item.integrations_count }} видж. ·
                                {{ item.work_stages_count }} этапов
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="item.is_active
                                        ? 'bg-f5-green/15 text-f5-text dark:text-white'
                                        : 'bg-f5-surface-2 text-f5-text-muted dark:bg-white/5 dark:text-white/45'"
                                >
                                    {{ item.is_active ? 'Активен' : 'Скрыт' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <Link :href="item.edit_url" class="f5-btn f5-btn--ghost !px-3 !py-1.5 text-xs">
                                        Изменить
                                    </Link>
                                    <button
                                        type="button"
                                        class="f5-btn !border-red-200 !px-3 !py-1.5 text-xs text-red-600 dark:text-red-400"
                                        @click="destroy(item.destroy_url)"
                                    >
                                        Удалить
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <PaginationLinks :links="packages.links" />
        </div>
    </AppLayout>
</template>
