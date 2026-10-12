<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import AdminNav from '../../../Components/Admin/AdminNav.vue';
import StarterPackageForm from '../../../Components/Admin/StarterPackageForm.vue';
import { buildStarterPackagePayload } from '../../../utils/starterPackageForm';

const props = defineProps({
    package: { type: Object, required: true },
    colorOptions: { type: Array, default: () => [] },
    entityOptions: { type: Array, default: () => [] },
    valueTypeOptions: { type: Array, default: () => [] },
    widgetCatalog: { type: Object, default: () => ({ widgets: [] }) },
});

const form = useForm({
    name: props.package.name,
    business_sphere: props.package.business_sphere,
    description: props.package.description ?? '',
    sort_order: props.package.sort_order ?? 0,
    is_active: props.package.is_active ?? true,
    funnels: props.package.funnels?.length
        ? props.package.funnels
        : [],
    skk_fields: props.package.skk_fields ?? [],
    integrations: props.package.integrations ?? [],
    work_stages: props.package.work_stages ?? [],
});

function submit() {
    form
        .transform((data) => buildStarterPackagePayload(data))
        .put(props.package.update_url);
}

function destroy() {
    if (!window.confirm('Удалить стартовый пакет?')) {
        return;
    }

    router.delete(props.package.destroy_url);
}
</script>

<template>
    <Head :title="`Пакет: ${package.name}`" />

    <AppLayout>
        <template #header>
            <div>
                <h1 class="text-xl font-bold text-f5-text dark:text-white">Админ-панель</h1>
            </div>
        </template>

        <div class="mx-auto max-w-4xl">
            <AdminNav active="starter-packages" />

            <PageHeader :title="package.name">
                <template #subtitle>
                    <Link href="/admin/starter-packages" class="text-f5-text-muted hover:text-f5-text dark:text-white/50">
                        ← К списку
                    </Link>
                </template>
                <template #actions>
                    <button type="button" class="f5-btn f5-btn--danger f5-btn--sm" @click="destroy">
                        Удалить
                    </button>
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
                        {{ form.processing ? 'Сохранение…' : 'Сохранить' }}
                    </button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
