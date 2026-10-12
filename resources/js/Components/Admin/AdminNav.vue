<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    active: {
        type: String,
        required: true,
    },
});

const links = [
    { key: 'users', href: '/admin/users', label: 'Пользователи' },
    { key: 'widgets', href: '/admin/widgets', label: 'Виджеты' },
    { key: 'starter-packages', href: '/admin/starter-packages', label: 'Стартовые пакеты' },
    { key: 'library-funnels', href: '/admin/library/funnels', label: 'Библиотека: воронки' },
    { key: 'library-skk', href: '/admin/library/skk-fields', label: 'Библиотека: СКК' },
];
</script>

<template>
    <nav class="mb-6 flex flex-wrap gap-2" aria-label="Разделы админки">
        <Link
            v-for="link in links"
            :key="link.key"
            :href="link.href"
            class="rounded-lg px-3 py-1.5 text-sm font-medium transition-colors"
            :class="active === link.key
                ? 'bg-f5-green/20 text-f5-text dark:text-white'
                : 'bg-f5-surface-2 text-f5-text-muted hover:text-f5-text dark:bg-white/5 dark:text-white/50 dark:hover:text-white'"
        >
            {{ link.label }}
        </Link>
    </nav>
</template>
