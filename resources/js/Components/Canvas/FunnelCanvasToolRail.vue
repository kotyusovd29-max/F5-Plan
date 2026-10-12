<script setup>
defineProps({
    tool: {
        type: String,
        default: 'select',
    },
    canEdit: {
        type: Boolean,
        default: false,
    },
    canDelete: {
        type: Boolean,
        default: false,
    },
    connectHint: {
        type: String,
        default: '',
    },
    showLoseArrows: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    'update:tool',
    'update:showLoseArrows',
    'add-widget',
    'add-stage',
    'delete',
]);
</script>

<template>
    <aside class="funnel-tool-rail" aria-label="Инструменты доски">
        <button
            type="button"
            class="funnel-tool-rail__btn"
            :class="{ 'is-active': tool === 'select' }"
            title="Выбор и перемещение"
            @click="emit('update:tool', 'select')"
        >
            <span aria-hidden="true">◇</span>
            <small>Выбор</small>
        </button>

        <button
            type="button"
            class="funnel-tool-rail__btn funnel-tool-rail__btn--lose"
            :class="{ 'is-active': showLoseArrows }"
            :title="showLoseArrows
                ? 'Скрыть постоянные стрелки в отказ — показывать только при выборе этапа'
                : 'Всегда показывать переходы в этапы «отказ / закрыто»'"
            :aria-pressed="showLoseArrows"
            @click="emit('update:showLoseArrows', !showLoseArrows)"
        >
            <span aria-hidden="true">↘</span>
            <small>Отказ</small>
        </button>

        <button
            v-if="canEdit"
            type="button"
            class="funnel-tool-rail__btn"
            :class="{ 'is-active': tool === 'connect' }"
            title="Соединить этапы и виджеты"
            @click="emit('update:tool', 'connect')"
        >
            <span aria-hidden="true">↗</span>
            <small>Связь</small>
        </button>

        <button
            v-if="canEdit"
            type="button"
            class="funnel-tool-rail__btn"
            title="Добавить виджет на доску"
            @click="emit('add-widget')"
        >
            <span aria-hidden="true">▣</span>
            <small>Виджет</small>
        </button>

        <button
            v-if="canEdit"
            type="button"
            class="funnel-tool-rail__btn"
            title="Добавить этап"
            @click="emit('add-stage')"
        >
            <span aria-hidden="true">＋</span>
            <small>Этап</small>
        </button>

        <button
            v-if="canEdit"
            type="button"
            class="funnel-tool-rail__btn funnel-tool-rail__btn--danger"
            :disabled="!canDelete"
            title="Удалить выбранное"
            @click="emit('delete')"
        >
            <span aria-hidden="true">✕</span>
            <small>Удал.</small>
        </button>

        <p v-if="connectHint" class="funnel-tool-rail__hint">{{ connectHint }}</p>
    </aside>
</template>
