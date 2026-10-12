<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../../Layouts/AppLayout.vue';
import PageHeader from '../../../../Components/PageHeader.vue';
import AdminNav from '../../../../Components/Admin/AdminNav.vue';
import LibrarySkkFieldForm from '../../../../Components/Admin/LibrarySkkFieldForm.vue';

defineProps({
    entityOptions: { type: Array, default: () => [] },
    valueTypeOptions: { type: Array, default: () => [] },
});

const form = useForm({
    entity_type: 'deals',
    name: '',
    value_type: 'text',
    is_required: false,
    show_in_card: true,
    show_in_list: false,
    show_in_filter: false,
    options_text: '',
    stage_name: '',
    comment: '',
    sort_order: 0,
});

const needsOptions = computed(() => ['select', 'multiselect'].includes(form.value_type));

function submit() {
    form
        .transform((data) => ({
            entity_type: data.entity_type,
            name: data.name,
            value_type: data.value_type,
            is_required: data.is_required,
            show_in_card: data.show_in_card,
            show_in_list: data.show_in_list,
            show_in_filter: data.show_in_filter,
            options: needsOptions.value
                ? data.options_text.split(/\r?\n/).map((line) => line.trim()).filter(Boolean)
                : null,
            stage_name: data.stage_name || null,
            comment: data.comment || null,
            sort_order: data.sort_order,
        }))
        .post('/admin/library/skk-fields');
}
</script>

<template>
    <Head title="Новое поле СКК" />

    <AppLayout>
        <template #header>
            <div>
                <h1 class="text-xl font-bold text-f5-text dark:text-white">Админ-панель</h1>
            </div>
        </template>

        <div class="mx-auto max-w-2xl">
            <AdminNav active="library-skk" />

            <PageHeader title="Новое поле СКК">
                <template #subtitle>
                    <Link href="/admin/library/skk-fields" class="text-f5-text-muted hover:text-f5-text dark:text-white/50">
                        ← К списку
                    </Link>
                </template>
            </PageHeader>

            <div class="f5-card p-6">
                <form class="space-y-6" @submit.prevent="submit">
                    <LibrarySkkFieldForm
                        :form="form"
                        :entity-options="entityOptions"
                        :value-type-options="valueTypeOptions"
                    />
                    <button type="submit" class="f5-btn f5-btn--primary" :disabled="form.processing">
                        {{ form.processing ? 'Создание…' : 'Создать шаблон' }}
                    </button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
