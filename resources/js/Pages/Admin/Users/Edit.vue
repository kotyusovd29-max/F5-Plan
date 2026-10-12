<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import UserForm from '../../../Components/UserForm.vue';
import AdminNav from '../../../Components/Admin/AdminNav.vue';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
    isSelf: {
        type: Boolean,
        default: false,
    },
    roleOptions: {
        type: Array,
        default: () => [],
    },
    ropOptions: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
    password_confirmation: '',
    role: props.user.role,
    rop_id: props.user.rop_id,
});

function submit() {
    form.put(`/admin/users/${props.user.id}`);
}
</script>

<template>
    <Head :title="`Редактирование — ${user.name}`" />

    <AppLayout>
        <template #header>
            <div>
                <h1 class="text-xl font-bold text-f5-text dark:text-white">
                    Админ-панель
                </h1>
            </div>
        </template>

        <div class="mx-auto max-w-2xl">
            <AdminNav active="users" />

            <PageHeader title="Редактирование пользователя">
                <template #subtitle>
                    <Link href="/admin/users" class="text-f5-text-muted hover:text-f5-text dark:text-white/50 dark:hover:text-white">
                        ← К списку пользователей
                    </Link>
                </template>
            </PageHeader>

            <div class="f5-card p-6">
                <form class="space-y-6" @submit.prevent="submit">
                    <UserForm
                        :form="form"
                        is-edit
                        :is-self="isSelf"
                        :role-options="roleOptions"
                        :rop-options="ropOptions"
                    />

                    <p
                        v-if="isSelf"
                        class="rounded-lg border border-f5-border bg-f5-surface-2/60 px-3 py-2 text-sm text-f5-text-muted dark:border-white/10 dark:bg-white/5 dark:text-white/60"
                    >
                        Роль: {{ user.role_label }}. Свою роль изменить нельзя.
                    </p>

                    <button type="submit" class="f5-btn f5-btn--primary" :disabled="form.processing">
                        {{ form.processing ? 'Сохранение…' : 'Сохранить изменения' }}
                    </button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
