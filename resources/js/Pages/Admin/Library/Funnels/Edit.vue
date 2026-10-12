<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../../Layouts/AppLayout.vue';
import PageHeader from '../../../../Components/PageHeader.vue';
import AdminNav from '../../../../Components/Admin/AdminNav.vue';
import LibraryFunnelForm from '../../../../Components/Admin/LibraryFunnelForm.vue';

const props = defineProps({
    funnel: {
        type: Object,
        required: true,
    },
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
    name: props.funnel.name,
    description: props.funnel.description,
    sort_order: props.funnel.sort_order,
    stages: props.funnel.stages.length
        ? props.funnel.stages.map((stage) => ({ ...stage }))
        : [{ name: '', action: '', comment: '', stage_type: '', deadline: '', kind: 'normal', outcomes: [], color: '#99ccff' }],
});

function submit() {
    form.put(props.funnel.update_url);
}

function destroy() {
    if (!window.confirm('Удалить шаблон воронки?')) {
        return;
    }

    router.delete(props.funnel.destroy_url);
}
</script>

<template>
    <Head :title="`Воронка: ${funnel.name}`" />

    <AppLayout>
        <template #header>
            <div>
                <h1 class="text-xl font-bold text-f5-text dark:text-white">Админ-панель</h1>
            </div>
        </template>

        <div class="mx-auto max-w-3xl">
            <AdminNav active="library-funnels" />

            <PageHeader :title="funnel.name">
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
