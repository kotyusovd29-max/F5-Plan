<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import AdminNav from '../../../Components/Admin/AdminNav.vue';
import WidgetForm from '../../../Components/Admin/WidgetForm.vue';

defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    slug: '',
    name: '',
    description: '',
    price: '',
    category: 'utilities',
    url: '',
    is_active: true,
    sort_order: 0,
    pricing_model: '',
    pricing_unit_monthly: null,
    pricing_quantity_label: 'каналов',
    pricing_default_quantity: 1,
    pricing_min_quantity: 1,
    pricing_package_6: null,
    pricing_package_12: null,
});

function submit() {
    form.post('/admin/widgets');
}
</script>

<template>
    <Head title="Новый виджет" />

    <AppLayout>
        <template #header>
            <div>
                <h1 class="text-xl font-bold text-f5-text dark:text-white">Админ-панель</h1>
            </div>
        </template>

        <div class="mx-auto max-w-2xl">
            <AdminNav active="widgets" />

            <PageHeader title="Новый виджет">
                <template #subtitle>
                    <Link href="/admin/widgets" class="text-f5-text-muted hover:text-f5-text dark:text-white/50 dark:hover:text-white">
                        ← К каталогу
                    </Link>
                </template>
            </PageHeader>

            <div class="f5-card p-6">
                <form class="space-y-6" @submit.prevent="submit">
                    <WidgetForm :form="form" :categories="categories" />
                    <button type="submit" class="f5-btn f5-btn--primary" :disabled="form.processing">
                        {{ form.processing ? 'Создание…' : 'Создать виджет' }}
                    </button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
