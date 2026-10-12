<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import UserForm from '../../../Components/UserForm.vue';
import AdminNav from '../../../Components/Admin/AdminNav.vue';

const props = defineProps({
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
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'employee',
    rop_id: null,
});

function submit() {
    form.post('/admin/users');
}
</script>

<template>
    <Head title="Новый пользователь" />

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

            <PageHeader title="Новый пользователь">
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
                        :role-options="roleOptions"
                        :rop-options="ropOptions"
                    />

                    <button type="submit" class="f5-btn f5-btn--primary" :disabled="form.processing">
                        {{ form.processing ? 'Создание…' : 'Создать пользователя' }}
                    </button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
