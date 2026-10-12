<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
    google: {
        type: Object,
        default: () => ({
            configured: false,
            connect_url: '/google/connect',
            disconnect_url: '/google/disconnect',
        }),
    },
});

const page = usePage();
const flashError = computed(() => page.props.flash?.error || '');
const flashSuccess = computed(() => page.props.flash?.success || '');

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
    password_confirmation: '',
    current_password: '',
});

function submit() {
    form.put('/profile', {
        preserveScroll: true,
        onSuccess: () => {
            form.password = '';
            form.password_confirmation = '';
            form.current_password = '';
        },
    });
}

function disconnectGoogle() {
    router.post(props.google.disconnect_url || '/google/disconnect', {}, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Личный кабинет" />

    <AppLayout>
        <template #header>
            <div>
                <h1 class="text-xl font-bold text-f5-text dark:text-white">
                    Личный кабинет
                </h1>
            </div>
        </template>

        <div class="mx-auto max-w-2xl space-y-5">
            <PageHeader
                title="Настройки аккаунта"
                subtitle="Измените имя, email или пароль"
            />

            <p
                v-if="flashSuccess"
                class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-500/30 dark:bg-green-500/10 dark:text-green-300"
            >
                {{ flashSuccess }}
            </p>
            <p
                v-if="flashError"
                class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300"
            >
                {{ flashError }}
            </p>

            <div class="f5-card p-6">
                <h2 class="mb-2 text-base font-semibold text-f5-text dark:text-white">
                    Google Docs
                </h2>
                <p class="mb-4 text-sm text-f5-text-muted dark:text-white/55">
                    Подключите свой Google-аккаунт, чтобы сохранять планы и сценарии встреч в Google Docs
                    и открывать их прямо из F5 Plan.
                </p>

                <div
                    v-if="user.google_connected"
                    class="flex flex-col gap-3 rounded-xl border border-f5-border bg-f5-surface-2/60 p-4 dark:border-white/10 dark:bg-white/5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <p class="text-sm font-medium text-f5-text dark:text-white">
                            Подключён
                        </p>
                        <p class="text-sm text-f5-text-muted dark:text-white/50">
                            {{ user.google_email || 'Google-аккаунт' }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="f5-btn f5-btn--ghost f5-btn--sm self-start"
                        @click="disconnectGoogle"
                    >
                        Отключить
                    </button>
                </div>

                <div v-else class="space-y-3">
                    <p
                        v-if="!google.configured"
                        class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-200"
                    >
                        На сервере не заданы GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET.
                        Создайте OAuth Client (Web) в Google Cloud Console и добавьте redirect URI
                        <span class="break-all font-mono">/google/callback</span>.
                    </p>
                    <a
                        v-if="google.configured"
                        :href="google.connect_url"
                        class="f5-btn f5-btn--primary f5-btn--sm inline-flex"
                    >
                        Подключить Google
                    </a>
                    <span
                        v-else
                        class="f5-btn f5-btn--ghost f5-btn--sm inline-flex opacity-60"
                    >
                        Подключение недоступно
                    </span>
                </div>
            </div>

            <div class="f5-card p-6">
                <dl class="mb-6 grid gap-3 rounded-xl border border-f5-border bg-f5-surface-2/60 p-4 text-sm dark:border-white/10 dark:bg-white/5 sm:grid-cols-2">
                    <div>
                        <dt class="text-f5-text-muted dark:text-white/50">Роль</dt>
                        <dd class="mt-0.5 font-medium text-f5-text dark:text-white">
                            {{ user.role_label }}
                        </dd>
                    </div>
                    <div v-if="user.rop_name">
                        <dt class="text-f5-text-muted dark:text-white/50">РОП</dt>
                        <dd class="mt-0.5 font-medium text-f5-text dark:text-white">
                            {{ user.rop_name }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-f5-text-muted dark:text-white/50">В системе с</dt>
                        <dd class="mt-0.5 font-medium text-f5-text dark:text-white">
                            {{ user.created_at }}
                        </dd>
                    </div>
                </dl>

                <form class="space-y-5" @submit.prevent="submit">
                    <div>
                        <label for="name" class="f5-label">Имя</label>
                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="f5-input"
                            :class="{ 'f5-input--error': form.errors.name }"
                            required
                        >
                        <p v-if="form.errors.name" class="f5-error">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label for="email" class="f5-label">Email</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="f5-input"
                            :class="{ 'f5-input--error': form.errors.email }"
                            required
                        >
                        <p v-if="form.errors.email" class="f5-error">{{ form.errors.email }}</p>
                    </div>

                    <div class="border-t border-f5-border pt-5 dark:border-white/10">
                        <p class="mb-4 text-sm text-f5-text-muted dark:text-white/50">
                            Оставьте поля пароля пустыми, если не хотите его менять.
                        </p>

                        <div class="space-y-5">
                            <div>
                                <label for="current_password" class="f5-label">Текущий пароль</label>
                                <input
                                    id="current_password"
                                    v-model="form.current_password"
                                    type="password"
                                    class="f5-input"
                                    :class="{ 'f5-input--error': form.errors.current_password }"
                                    autocomplete="current-password"
                                >
                                <p v-if="form.errors.current_password" class="f5-error">{{ form.errors.current_password }}</p>
                            </div>

                            <div>
                                <label for="password" class="f5-label">Новый пароль</label>
                                <input
                                    id="password"
                                    v-model="form.password"
                                    type="password"
                                    class="f5-input"
                                    :class="{ 'f5-input--error': form.errors.password }"
                                    autocomplete="new-password"
                                >
                                <p v-if="form.errors.password" class="f5-error">{{ form.errors.password }}</p>
                            </div>

                            <div>
                                <label for="password_confirmation" class="f5-label">Подтверждение нового пароля</label>
                                <input
                                    id="password_confirmation"
                                    v-model="form.password_confirmation"
                                    type="password"
                                    class="f5-input"
                                    autocomplete="new-password"
                                >
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="f5-btn f5-btn--primary" :disabled="form.processing">
                        {{ form.processing ? 'Сохранение…' : 'Сохранить изменения' }}
                    </button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
