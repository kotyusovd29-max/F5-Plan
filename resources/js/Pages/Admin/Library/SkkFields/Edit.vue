<script setup>
import { computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../../Layouts/AppLayout.vue';
import PageHeader from '../../../../Components/PageHeader.vue';
import AdminNav from '../../../../Components/Admin/AdminNav.vue';
import LibrarySkkFieldForm from '../../../../Components/Admin/LibrarySkkFieldForm.vue';

const props = defineProps({
    field: { type: Object, required: true },
    entityOptions: { type: Array, default: () => [] },
    valueTypeOptions: { type: Array, default: () => [] },
});

const form = useForm({
    entity_type: props.field.entity_type,
    name: props.field.name,
    value_type: props.field.value_type,
    is_required: props.field.is_required,
    show_in_card: props.field.show_in_card,
    show_in_list: props.field.show_in_list,
    show_in_filter: props.field.show_in_filter,
    options_text: (props.field.options || []).join('\n'),
    stage_name: props.field.stage_name,
    comment: props.field.comment,
    sort_order: props.field.sort_order,
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
        .put(props.field.update_url);
}

function destroy() {
    if (!window.confirm('Удалить шаблон поля?')) {
        return;
    }

    router.delete(props.field.destroy_url);
}
</script>

<template>
    <Head :title="`Поле СКК: ${field.name}`" />

    <AppLayout>
        <template #header>
            <div>
                <h1 class="text-xl font-bold text-f5-text dark:text-white">Админ-панель</h1>
            </div>
        </template>

        <div class="mx-auto max-w-2xl">
            <AdminNav active="library-skk" />

            <PageHeader :title="field.name">
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
