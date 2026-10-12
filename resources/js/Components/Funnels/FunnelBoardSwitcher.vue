<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    funnels: {
        type: Array,
        default: () => [],
    },
    activeId: {
        type: [Number, String],
        required: true,
    },
    urlKey: {
        type: String,
        default: 'url',
    },
});
</script>

<template>
    <nav
        v-if="funnels.length > 1"
        class="funnel-board-switcher"
        aria-label="Воронки проекта"
    >
        <Link
            v-for="item in funnels"
            :key="item.id"
            :href="item[urlKey] || item.url || item.canvas_url"
            class="funnel-board-switcher__tab"
            :class="{ 'is-active': Number(item.id) === Number(activeId) }"
            preserve-scroll
        >
            {{ item.name }}
        </Link>
    </nav>
</template>
