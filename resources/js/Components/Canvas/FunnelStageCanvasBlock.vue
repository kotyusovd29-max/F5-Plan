<script setup>
import { computed } from 'vue';
import { resolveStageKind } from '../../utils/funnelStageKind';

const props = defineProps({
    stage: {
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
    commentCount: {
        type: Number,
        default: 0,
    },
});

const emit = defineEmits(['pointer-down', 'select']);

const visualKind = computed(() => resolveStageKind(props.stage));

const metaText = computed(() => {
    const parts = [];
    if ((props.stage.stage_type ?? '').trim()) parts.push(props.stage.stage_type.trim());
    if ((props.stage.deadline ?? '').trim()) parts.push(props.stage.deadline.trim());
    const widgetsCount = Array.isArray(props.stage.widgets) ? props.stage.widgets.length : 0;
    if (widgetsCount > 0) parts.push(`${widgetsCount} виджет.`);
    return parts.join(' · ');
});

const launchesWidget = computed(() => (
    (props.stage.outcomes ?? []).some((row) => row?.widget_slug)
));

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
        class="funnel-stage-block"
        :class="{
            'is-dragging': isDragging,
            'is-active': isActive,
            'is-connect-source': connectSource,
            'is-readonly': readonly,
            'funnel-stage-block--win': visualKind === 'win',
            'funnel-stage-block--lose': visualKind === 'lose',
        }"
        :style="{ left: `${stage.canvas_pos_x}px`, top: `${stage.canvas_pos_y}px` }"
        @mousedown.stop="onPointerDown"
    >
        <header class="funnel-stage-block__header">
            <div class="funnel-stage-block__text">
                <span class="funnel-stage-block__number">Этап {{ stage.number }}</span>
                <span class="funnel-stage-block__title">{{ stage.name }}</span>
                <span v-if="metaText" class="funnel-stage-block__meta">{{ metaText }}</span>
                <span
                    v-if="launchesWidget"
                    class="funnel-stage-block__meta"
                    title="Результат автозадачи запускает виджет"
                >⚡ виджет</span>
            </div>
            <span
                v-if="commentCount > 0"
                class="funnel-stage-block__comments"
                :title="`${commentCount} комм.`"
            >
                {{ commentCount }}
            </span>
            <span v-if="!readonly" class="funnel-stage-block__drag" title="Перетащить" aria-hidden="true">⠿</span>
        </header>
    </article>
</template>
