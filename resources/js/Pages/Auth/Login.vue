<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';

defineProps({
    appName: {
        type: String,
        default: 'F5 Plan',
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Вход" />

    <GuestLayout>
        <div class="w-full max-w-md">
            <div class="f5-card border-white/10 bg-f5-black/90 p-8 shadow-2xl backdrop-blur-sm">
                <div class="mb-8 text-center">
                    <p class="f5-block-yellow mb-2 inline-block rounded-lg px-3 py-1 text-xs font-semibold uppercase tracking-widest">
                        Топ-1 партнёр amoCRM
                    </p>
                    <h1 class="text-2xl font-bold text-white">
                        Вход в {{ appName }}
                    </h1>
                    <p class="mt-2 text-sm text-white/60">
                        Планы внедрения и автоматизация продаж
                    </p>
                </div>

                <form class="space-y-5" @submit.prevent="submit">
                    <div>
                        <label for="email" class="f5-label text-white/90">Email</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="f5-input"
                            :class="{ 'f5-input--error': form.errors.email }"
                            required
                            autofocus
                            autocomplete="username"
                        >
                        <p v-if="form.errors.email" class="f5-error">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <div>
                        <label for="password" class="f5-label text-white/90">Пароль</label>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            class="f5-input"
                            :class="{ 'f5-input--error': form.errors.password }"
                            required
                            autocomplete="current-password"
                        >
                        <p v-if="form.errors.password" class="f5-error">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <label class="flex cursor-pointer items-center gap-2 text-sm text-white/70">
                        <input
                            v-model="form.remember"
                            type="checkbox"
                            class="h-4 w-4 rounded border-white/20 bg-transparent text-f5-green focus:ring-f5-green/30"
                        >
                        Запомнить меня
                    </label>

                    <button
                        type="submit"
                        class="f5-btn f5-btn--primary w-full !py-3"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Вход…' : 'Войти' }}
                    </button>
                </form>
            </div>
        </div>
    </GuestLayout>
</template>
