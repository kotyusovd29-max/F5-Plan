<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
        default: false,
    },
    label: {
        type: String,
        default: '',
    },
    tourId: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <Link
        :href="href"
        :title="label || undefined"
        :data-tour="tourId || undefined"
        class="app-nav-link flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors"
        :class="active
            ? 'bg-f5-green text-f5-black'
            : 'text-f5-sidebar-text hover:bg-f5-sidebar-hover hover:text-white'"
    >
        <slot />
    </Link>
</template>
