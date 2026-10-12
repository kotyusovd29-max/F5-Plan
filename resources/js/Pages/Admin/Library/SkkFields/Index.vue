<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '../../../../Layouts/AppLayout.vue';
import PageHeader from '../../../../Components/PageHeader.vue';
import PaginationLinks from '../../../../Components/PaginationLinks.vue';
import AdminNav from '../../../../Components/Admin/AdminNav.vue';

defineProps({
    fields: {
        type: Object,
        required: true,
    },
});

const page = usePage();

function destroy(url) {
    if (!window.confirm('Удалить шаблон поля?')) {
        return;
    }

    router.delete(url);
}
</script>

<template>
    <Head title="Библиотека: СКК" />

    <AppLayout>
        <template #header>
            <div>
                <h1 class="text-xl font-bold text-f5-text dark:text-white">Админ-панель</h1>
                <p class="text-sm text-f5-text-muted dark:text-white/50">Типовые поля СКК</p>
            </div>
        </template>

        <div class="mx-auto max-w-6xl">
            <AdminNav active="library-skk" />

            <PageHeader title="Библиотека полей СКК" subtitle="Глобальные шаблоны полей">
                <template #actions>
                    <Link href="/admin/library/skk-fields/create" class="f5-btn f5-btn--primary">
                        Добавить поле
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
                            <th class="px-4 py-3 text-left font-semibold">Сущность</th>
                            <th class="px-4 py-3 text-left font-semibold">Тип</th>
                            <th class="px-4 py-3 text-left font-semibold">Этап</th>
                            <th class="px-4 py-3 text-right font-semibold" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-f5-border dark:divide-white/10">
                        <tr v-if="fields.data.length === 0">
                            <td colspan="5" class="px-4 py-8 text-center text-f5-text-muted dark:text-white/50">
                                Шаблонов пока нет
                            </td>
                        </tr>
                        <tr v-for="field in fields.data" :key="field.id">
                            <td class="px-4 py-3 font-medium text-f5-text dark:text-white">{{ field.name }}</td>
                            <td class="px-4 py-3 text-f5-text-muted dark:text-white/60">{{ field.entity_label }}</td>
                            <td class="px-4 py-3 text-f5-text-muted dark:text-white/60">{{ field.value_type_label }}</td>
                            <td class="px-4 py-3 text-f5-text-muted dark:text-white/60">{{ field.stage_name || '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <Link :href="field.edit_url" class="f5-btn f5-btn--ghost !px-3 !py-1.5 text-xs">
                                        Изменить
                                    </Link>
                                    <button
                                        type="button"
                                        class="f5-btn !border-red-200 !px-3 !py-1.5 text-xs text-red-600 dark:text-red-400"
                                        @click="destroy(field.destroy_url)"
                                    >
                                        Удалить
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <PaginationLinks :links="fields.links" />
        </div>
    </AppLayout>
</template>
