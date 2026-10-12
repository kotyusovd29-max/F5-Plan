<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import AdminNav from '../../../Components/Admin/AdminNav.vue';
import StarterPackageForm from '../../../Components/Admin/StarterPackageForm.vue';
import { buildStarterPackagePayload } from '../../../utils/starterPackageForm';

defineProps({
    colorOptions: { type: Array, default: () => [] },
    entityOptions: { type: Array, default: () => [] },
    valueTypeOptions: { type: Array, default: () => [] },
    widgetCatalog: { type: Object, default: () => ({ widgets: [] }) },
});

const form = useForm({
    name: '',
    business_sphere: '',
    description: '',
    sort_order: 0,
    is_active: true,
    funnels: [
        {
            name: 'Продажи',
            description: '',
            stages: [
                { name: 'Новый лид', action: '', comment: '', color: '#99ccff' },
                { name: 'Квалификация', action: '', comment: '', color: '#ffff99' },
                { name: 'Успешно', action: '', comment: '', color: '#99ff99' },
            ],
        },
    ],
    skk_fields: [],
    integrations: [],
    work_stages: [],
});

function submit() {
    form
        .transform((data) => buildStarterPackagePayload(data))
        .post('/admin/starter-packages');
}
</script>

<template>
    <Head title="Новый стартовый пакет" />

    <AppLayout>
        <template #header>
            <div>
                <h1 class="text-xl font-bold text-f5-text dark:text-white">Админ-панель</h1>
            </div>
        </template>

        <div class="mx-auto max-w-4xl">
            <AdminNav active="starter-packages" />

            <PageHeader title="Новый стартовый пакет">
                <template #subtitle>
                    <Link href="/admin/starter-packages" class="text-f5-text-muted hover:text-f5-text dark:text-white/50">
                        ← К списку
                    </Link>
                </template>
            </PageHeader>

            <div class="f5-card p-6">
                <form class="space-y-6" @submit.prevent="submit">
                    <StarterPackageForm
                        :form="form"
                        :color-options="colorOptions"
                        :entity-options="entityOptions"
                        :value-type-options="valueTypeOptions"
                        :widget-catalog="widgetCatalog"
                    />
                    <button type="submit" class="f5-btn f5-btn--primary" :disabled="form.processing">
                        {{ form.processing ? 'Создание…' : 'Создать пакет' }}
                    </button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
