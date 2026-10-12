<script setup>
import { Link } from '@inertiajs/vue3';
import ThemeToggle from '../ThemeToggle.vue';

defineProps({
    backHref: {
        type: String,
        required: true,
    },
    backLabel: {
        type: String,
        required: true,
    },
    title: {
        type: String,
        required: true,
    },
    zoomLabel: {
        type: String,
        default: '100%',
    },
    statusText: {
        type: String,
        default: 'Сохранено',
    },
    statusState: {
        type: String,
        default: 'saved',
    },
});
</script>

<template>
    <header class="canvas-toolbar">
        <div class="canvas-toolbar__left">
            <Link :href="backHref" class="canvas-toolbar__back">{{ backLabel }}</Link>
            <h1 class="canvas-toolbar__title">{{ title }}</h1>
        </div>

        <div class="canvas-toolbar__center">
            <slot name="center" />
        </div>

        <div class="canvas-toolbar__right">
            <button type="button" class="f5-btn f5-btn--ghost f5-btn--sm" title="Уменьшить" @click="$emit('zoom-out')">
                −
            </button>
            <span class="canvas-toolbar__zoom">{{ zoomLabel }}</span>
            <button type="button" class="f5-btn f5-btn--ghost f5-btn--sm" title="Увеличить" @click="$emit('zoom-in')">
                +
            </button>
            <button type="button" class="f5-btn f5-btn--ghost f5-btn--sm" title="Сбросить вид" @click="$emit('zoom-reset')">
                100%
            </button>
            <span
                class="canvas-toolbar__status"
                :class="{
                    'is-saving': statusState === 'saving',
                    'is-error': statusState === 'error',
                }"
            >{{ statusText }}</span>
            <ThemeToggle />
        </div>
    </header>
</template>
