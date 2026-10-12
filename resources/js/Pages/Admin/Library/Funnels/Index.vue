<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '../../../../Layouts/AppLayout.vue';
import PageHeader from '../../../../Components/PageHeader.vue';
import PaginationLinks from '../../../../Components/PaginationLinks.vue';
import AdminNav from '../../../../Components/Admin/AdminNav.vue';

defineProps({
    funnels: {
        type: Object,
        required: true,
    },
});

const page = usePage();

function destroy(url) {
    if (!window.confirm('Удалить шаблон воронки?')) {
        return;
    }

    router.delete(url);
}
</script>

<template>
    <Head title="Библиотека: воронки" />

    <AppLayout>
        <template #header>
            <div>
                <h1 class="text-xl font-bold text-f5-text dark:text-white">Админ-панель</h1>
                <p class="text-sm text-f5-text-muted dark:text-white/50">Типовые воронки</p>
            </div>
        </template>

        <div class="mx-auto max-w-6xl">
            <AdminNav active="library-funnels" />

            <PageHeader title="Библиотека воронок" subtitle="Глобальные шаблоны для всех консультантов">
                <template #actions>
                    <Link href="/admin/library/funnels/create" class="f5-btn f5-btn--primary">
                        Добавить воронку
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
                            <th class="px-4 py-3 text-left font-semibold">Название</th>
                            <th class="px-4 py-3 text-left font-semibold">Этапы</th>
                            <th class="px-4 py-3 text-right font-semibold" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-f5-border dark:divide-white/10">
                        <tr v-if="funnels.data.length === 0">
                            <td colspan="3" class="px-4 py-8 text-center text-f5-text-muted dark:text-white/50">
                                Шаблонов пока нет
                            </td>
                        </tr>
                        <tr v-for="funnel in funnels.data" :key="funnel.id">
                            <td class="px-4 py-3">
                                <div class="font-medium text-f5-text dark:text-white">{{ funnel.name }}</div>
                                <div v-if="funnel.description" class="text-xs text-f5-text-muted dark:text-white/40">
                                    {{ funnel.description }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-f5-text-muted dark:text-white/60">
                                {{ funnel.stages_count }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <Link :href="funnel.edit_url" class="f5-btn f5-btn--ghost !px-3 !py-1.5 text-xs">
                                        Изменить
                                    </Link>
                                    <button
                                        type="button"
                                        class="f5-btn !border-red-200 !px-3 !py-1.5 text-xs text-red-600 dark:text-red-400"
                                        @click="destroy(funnel.destroy_url)"
                                    >
                                        Удалить
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <PaginationLinks :links="funnels.links" />
        </div>
    </AppLayout>
</template>
