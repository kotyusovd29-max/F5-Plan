<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    token: { type: String, required: true },
    company_name: { type: String, default: '' },
    funnel_name: { type: String, default: '' },
    unlock_url: { type: String, required: true },
});

const form = useForm({
    password: '',
});

function submit() {
    form.post(props.unlock_url);
}
</script>

<template>
    <Head :title="`Доступ к доске — ${funnel_name}`" />

    <div class="flex min-h-screen items-center justify-center bg-f5-surface-2 px-4">
        <form
            class="w-full max-w-md rounded-2xl border border-f5-border bg-f5-surface p-6 shadow-sm"
            @submit.prevent="submit"
        >
            <p class="text-xs font-bold uppercase tracking-wide text-f5-text-muted">
                {{ company_name || 'Клиентская доска' }}
            </p>
            <h1 class="mt-1 text-xl font-bold text-f5-text">{{ funnel_name }}</h1>
            <p class="mt-2 text-sm text-f5-text-muted">
                Для просмотра доски введите пароль, который выдал менеджер.
            </p>

            <div class="mt-5">
                <label class="f5-label" for="share-password">Пароль</label>
                <input
                    id="share-password"
                    v-model="form.password"
                    type="password"
                    class="f5-input"
                    :class="{ 'f5-input--error': form.errors.password }"
                    required
                    autofocus
                >
                <p v-if="form.errors.password" class="f5-error">{{ form.errors.password }}</p>
            </div>

            <button
                type="submit"
                class="f5-btn f5-btn--primary mt-4 w-full"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Проверка…' : 'Открыть доску' }}
            </button>
        </form>
    </div>
</template>
