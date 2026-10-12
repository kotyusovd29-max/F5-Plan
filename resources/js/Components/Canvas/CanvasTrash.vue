<script setup>
defineProps({
    visible: {
        type: Boolean,
        default: false,
    },
    active: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div
        class="canvas-trash"
        :class="{ 'is-hidden': !visible, 'is-active': active }"
        :aria-hidden="!visible"
    >
        <div class="canvas-trash__inner">
            <span class="canvas-trash__icon" aria-hidden="true">🗑</span>
            <span class="canvas-trash__label">Перетащите сюда для удаления</span>
        </div>
    </div>
</template>
