<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import AdminNav from '../../../Components/Admin/AdminNav.vue';
import WidgetForm from '../../../Components/Admin/WidgetForm.vue';

const props = defineProps({
    widget: {
        type: Object,
        required: true,
    },
    categories: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    slug: props.widget.slug,
    name: props.widget.name,
    description: props.widget.description,
    price: props.widget.price,
    category: props.widget.category,
    url: props.widget.url,
    is_active: props.widget.is_active,
    sort_order: props.widget.sort_order,
    pricing_model: props.widget.pricing_model || '',
    pricing_unit_monthly: props.widget.pricing_unit_monthly,
    pricing_quantity_label: props.widget.pricing_quantity_label || 'каналов',
    pricing_default_quantity: props.widget.pricing_default_quantity || 1,
    pricing_min_quantity: props.widget.pricing_min_quantity || 1,
    pricing_package_6: props.widget.pricing_package_6,
    pricing_package_12: props.widget.pricing_package_12,
});

function submit() {
    form.put(props.widget.update_url);
}

function destroy() {
    if (!window.confirm('Удалить виджет из каталога?')) {
        return;
    }

    router.delete(props.widget.destroy_url);
}
</script>

<template>
    <Head :title="`Виджет: ${widget.name}`" />

    <AppLayout>
        <template #header>
            <div>
                <h1 class="text-xl font-bold text-f5-text dark:text-white">Админ-панель</h1>
            </div>
        </template>

        <div class="mx-auto max-w-2xl">
            <AdminNav active="widgets" />

            <PageHeader :title="widget.name">
                <template #subtitle>
                    <Link href="/admin/widgets" class="text-f5-text-muted hover:text-f5-text dark:text-white/50 dark:hover:text-white">
                        ← К каталогу
                    </Link>
                </template>
            </PageHeader>

            <div class="f5-card p-6">
                <form class="space-y-6" @submit.prevent="submit">
                    <WidgetForm :form="form" :categories="categories" />
                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="f5-btn f5-btn--primary" :disabled="form.processing">
                            {{ form.processing ? 'Сохранение…' : 'Сохранить' }}
                        </button>
                        <button type="button" class="f5-btn f5-btn--danger" @click="destroy">
                            Удалить
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
