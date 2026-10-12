<script setup>
import { reactive, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import PaginationLinks from '../../../Components/PaginationLinks.vue';
import AdminNav from '../../../Components/Admin/AdminNav.vue';

const props = defineProps({
    widgets: {
        type: Object,
        required: true,
    },
    categories: {
        type: Object,
        default: () => ({}),
    },
    sourceUrl: {
        type: String,
        default: null,
    },
    filters: {
        type: Object,
        default: () => ({ q: '', category: '' }),
    },
});

const page = usePage();
const localFilters = reactive({
    q: props.filters.q ?? '',
    category: props.filters.category ?? '',
});

watch(
    () => props.filters,
    (filters) => {
        localFilters.q = filters.q ?? '';
        localFilters.category = filters.category ?? '';
    },
);

function applyFilters() {
    router.get('/admin/widgets', {
        q: localFilters.q || undefined,
        category: localFilters.category || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
}

function deleteWidget(url) {
    if (!window.confirm('Удалить виджет из каталога?')) {
        return;
    }

    router.delete(url);
}
</script>

<template>
    <Head title="Виджеты" />

    <AppLayout>
        <template #header>
            <div>
                <h1 class="text-xl font-bold text-f5-text dark:text-white">Админ-панель</h1>
                <p class="text-sm text-f5-text-muted dark:text-white/50">Каталог виджетов F5</p>
            </div>
        </template>

        <div class="mx-auto max-w-6xl">
            <AdminNav active="widgets" />

            <PageHeader title="Виджеты" subtitle="Управление каталогом без правки config">
                <template #actions>
                    <a
                        v-if="sourceUrl"
                        :href="sourceUrl"
                        target="_blank"
                        rel="noopener"
                        class="f5-btn f5-btn--ghost"
                    >
                        Сайт каталога
                    </a>
                    <Link href="/admin/widgets/create" class="f5-btn f5-btn--primary">
                        Добавить виджет
                    </Link>
                </template>
            </PageHeader>

            <form class="mb-4 flex flex-wrap gap-3" @submit.prevent="applyFilters">
                <input
                    v-model="localFilters.q"
                    type="search"
                    class="f5-input max-w-xs"
                    placeholder="Поиск по названию или slug"
                >
                <select v-model="localFilters.category" class="f5-input max-w-xs">
                    <option value="">Все категории</option>
                    <option v-for="(label, key) in categories" :key="key" :value="key">
                        {{ label }}
                    </option>
                </select>
                <button type="submit" class="f5-btn f5-btn--primary">Найти</button>
            </form>

            <div v-if="page.props.flash?.success" class="mb-4 rounded-lg bg-f5-green/15 px-4 py-3 text-sm text-f5-text dark:text-white">
                {{ page.props.flash.success }}
            </div>

            <div class="f5-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-f5-border bg-f5-surface-2 dark:border-white/10 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Название</th>
                                <th class="px-4 py-3 text-left font-semibold">Категория</th>
                                <th class="px-4 py-3 text-left font-semibold">Цена</th>
                                <th class="px-4 py-3 text-left font-semibold">Статус</th>
                                <th class="px-4 py-3 text-right font-semibold" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-f5-border dark:divide-white/10">
                            <tr v-if="widgets.data.length === 0">
                                <td colspan="5" class="px-4 py-8 text-center text-f5-text-muted dark:text-white/50">
                                    Виджетов пока нет. Запустите сидер или добавьте вручную.
                                </td>
                            </tr>
                            <tr
                                v-for="widget in widgets.data"
                                :key="widget.id"
                                class="hover:bg-f5-surface-2/60 dark:hover:bg-white/[0.02]"
                            >
                                <td class="px-4 py-3">
                                    <div class="font-medium text-f5-text dark:text-white">{{ widget.name }}</div>
                                    <div class="text-xs text-f5-text-muted dark:text-white/40">{{ widget.slug }}</div>
                                </td>
                                <td class="px-4 py-3 text-f5-text-muted dark:text-white/60">
                                    {{ widget.category_label }}
                                </td>
                                <td class="px-4 py-3 text-f5-text-muted dark:text-white/60">
                                    {{ widget.price || '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-block rounded-md px-2 py-0.5 text-xs font-semibold"
                                        :class="widget.is_active
                                            ? 'bg-f5-green/15 text-f5-text dark:text-white'
                                            : 'bg-f5-surface-2 text-f5-text-muted dark:bg-white/10 dark:text-white/50'"
                                    >
                                        {{ widget.is_active ? 'Активен' : 'Скрыт' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        <Link :href="widget.edit_url" class="f5-btn f5-btn--ghost !px-3 !py-1.5 text-xs">
                                            Изменить
                                        </Link>
                                        <button
                                            type="button"
                                            class="f5-btn !border-red-200 !px-3 !py-1.5 text-xs text-red-600 dark:!border-red-900/50 dark:text-red-400"
                                            @click="deleteWidget(widget.destroy_url)"
                                        >
                                            Удалить
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <PaginationLinks :links="widgets.links" />
        </div>
    </AppLayout>
</template>
