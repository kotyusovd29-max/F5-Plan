<script setup>
const props = defineProps({
    widget: {
        type: Object,
        required: true,
    },
    isDragging: {
        type: Boolean,
        default: false,
    },
    isActive: {
        type: Boolean,
        default: false,
    },
    connectSource: {
        type: Boolean,
        default: false,
    },
    readonly: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['pointer-down', 'select']);

function onPointerDown(event) {
    if (event.button !== 0) {
        return;
    }

    emit('select');

    if (!props.readonly) {
        emit('pointer-down', event);
    }
}
</script>

<template>
    <article
        class="funnel-board-widget"
        :class="{
            'is-dragging': isDragging,
            'is-active': isActive,
            'is-connect-source': connectSource,
            'is-readonly': readonly,
        }"
        :style="{ left: `${widget.canvas_pos_x}px`, top: `${widget.canvas_pos_y}px` }"
        @mousedown.stop="onPointerDown"
    >
        <header class="funnel-board-widget__header">
            <span class="funnel-board-widget__eyebrow">Виджет F5</span>
            <span v-if="!readonly" class="funnel-board-widget__drag" aria-hidden="true">⠿</span>
        </header>
        <p class="funnel-board-widget__title">{{ widget.name || widget.widget_slug }}</p>
        <p v-if="widget.comment" class="funnel-board-widget__comment">{{ widget.comment }}</p>
        <p v-else class="funnel-board-widget__comment funnel-board-widget__comment--empty">
            Без комментария
        </p>
    </article>
</template>
