<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    reason: { type: String, default: 'expired' },
    company_name: { type: String, default: '' },
    funnel_name: { type: String, default: '' },
});

const title = computed(() => (
    props.reason === 'expired' ? 'Срок ссылки истёк' : 'Доска недоступна'
));

const text = computed(() => (
    props.reason === 'expired'
        ? 'Срок действия этой ссылки закончился. Попросите менеджера прислать новую.'
        : 'Доска временно недоступна.'
));
</script>

<template>
    <Head :title="title" />

    <div class="flex min-h-screen items-center justify-center bg-f5-surface-2 px-4">
        <div class="w-full max-w-md rounded-2xl border border-f5-border bg-f5-surface p-6 text-center shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-f5-text-muted">
                {{ company_name || 'Клиентская доска' }}
            </p>
            <h1 class="mt-2 text-xl font-bold text-f5-text">{{ title }}</h1>
            <p v-if="funnel_name" class="mt-1 text-sm font-medium text-f5-text">{{ funnel_name }}</p>
            <p class="mt-3 text-sm text-f5-text-muted">{{ text }}</p>
        </div>
    </div>
</template>
