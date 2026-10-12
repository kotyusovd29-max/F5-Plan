<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../../Layouts/AppLayout.vue';
import PageHeader from '../../../../Components/PageHeader.vue';
import AdminNav from '../../../../Components/Admin/AdminNav.vue';
import LibraryFunnelForm from '../../../../Components/Admin/LibraryFunnelForm.vue';

defineProps({
    colorOptions: {
        type: Array,
        default: () => [],
    },
    f5WidgetCatalog: {
        type: Object,
        default: () => ({ widgets: [] }),
    },
});

const form = useForm({
    name: '',
    description: '',
    sort_order: 0,
    stages: [
        { name: 'Новый лид', action: '', comment: '', stage_type: '', deadline: '', kind: 'normal', outcomes: [], color: '#99ccff' },
        { name: 'Квалификация', action: '', comment: '', stage_type: '', deadline: '', kind: 'normal', outcomes: [], color: '#ffff99' },
        { name: 'Успешно', action: '', comment: '', stage_type: '', deadline: '', kind: 'win', outcomes: [], color: '#99ff99' },
    ],
});

function submit() {
    form.post('/admin/library/funnels');
}
</script>

<template>
    <Head title="Новая воронка в библиотеке" />

    <AppLayout>
        <template #header>
            <div>
                <h1 class="text-xl font-bold text-f5-text dark:text-white">Админ-панель</h1>
            </div>
        </template>

        <div class="mx-auto max-w-3xl">
            <AdminNav active="library-funnels" />

            <PageHeader title="Новая воронка">
                <template #subtitle>
                    <Link href="/admin/library/funnels" class="text-f5-text-muted hover:text-f5-text dark:text-white/50">
                        ← К списку
                    </Link>
                </template>
            </PageHeader>

            <div class="f5-card p-6">
                <form class="space-y-6" @submit.prevent="submit">
                    <LibraryFunnelForm
                        :form="form"
                        :color-options="colorOptions"
                        :f5-widget-catalog="f5WidgetCatalog"
                    />
                    <button type="submit" class="f5-btn f5-btn--primary" :disabled="form.processing">
                        {{ form.processing ? 'Создание…' : 'Создать шаблон' }}
                    </button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
