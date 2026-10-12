<script setup>
import { computed, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import FunnelStageCanvasBlock from '../../Components/Canvas/FunnelStageCanvasBlock.vue';
import FunnelBoardWidgetBlock from '../../Components/Canvas/FunnelBoardWidgetBlock.vue';
import FunnelCanvasArrows from '../../Components/Canvas/FunnelCanvasArrows.vue';
import FunnelBoardCommentsPanel from '../../Components/Funnels/FunnelBoardCommentsPanel.vue';
import FunnelBoardCommentMarkers from '../../Components/Funnels/FunnelBoardCommentMarkers.vue';
import FunnelBoardSwitcher from '../../Components/Funnels/FunnelBoardSwitcher.vue';
import { useCanvasViewport } from '../../composables/useCanvasViewport';
import { useShowLoseArrows } from '../../composables/useShowLoseArrows';
import { resolveOutcomeStyle } from '../../utils/funnelStageKind';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
    funnels: {
        type: Array,
        default: () => [],
    },
    funnel: {
        type: Object,
        required: true,
    },
    comments: {
        type: Array,
        default: () => [],
    },
    commentsEnabled: {
        type: Boolean,
        default: true,
    },
    urls: {
        type: Object,
        default: () => ({}),
    },
    viewport: {
        type: Object,
        default: () => ({}),
    },
    f5WidgetCatalog: {
        type: Object,
        default: () => ({ widgets: [] }),
    },
});

const markersStorageKey = computed(() => `funnel-board-markers:${props.funnel.id}`);

function readMarkersVisible() {
    try {
        const raw = window.localStorage.getItem(markersStorageKey.value);
        if (raw === null) return true;
        return raw !== '0';
    } catch {
        return true;
    }
}

const localStages = ref(props.funnel.stages.map((stage) => ({ ...stage })));
const localBoardWidgets = ref([...(props.funnel.board_widgets ?? [])].map((row) => ({ ...row })));
const activeStageId = ref(null);
const activeWidgetId = ref(null);
const boardMarkersVisible = ref(true);
const { showLoseArrows, toggleShowLoseArrows } = useShowLoseArrows(computed(() => props.funnel.id));

watch(
    () => props.funnel,
    (funnel) => {
        localStages.value = (funnel.stages ?? []).map((stage) => ({ ...stage }));
        localBoardWidgets.value = [...(funnel.board_widgets ?? [])].map((row) => ({ ...row }));

        if (activeStageId.value && !(funnel.stages ?? []).some((s) => Number(s.id) === Number(activeStageId.value))) {
            activeStageId.value = null;
        }

        if (activeWidgetId.value && !(funnel.board_widgets ?? []).some((w) => w.id === activeWidgetId.value)) {
            activeWidgetId.value = null;
        }
    },
);

watch(markersStorageKey, () => {
    boardMarkersVisible.value = readMarkersVisible();
}, { immediate: true });

watch(boardMarkersVisible, (value) => {
    try {
        window.localStorage.setItem(markersStorageKey.value, value ? '1' : '0');
    } catch {
        // ignore storage errors
    }
});

const {
    viewportRef,
    surfaceStyle,
    zoomLabel,
    zoom,
    onWheel,
    onViewportMouseDown,
    zoomIn,
    zoomOut,
    zoomReset,
} = useCanvasViewport(props.viewport, null, () => ({}));

const activeStage = computed(() => localStages.value.find((s) => Number(s.id) === Number(activeStageId.value)) ?? null);

const stageById = computed(() => {
    const map = new Map();
    localStages.value.forEach((stage) => map.set(Number(stage.id), stage));
    return map;
});

const widgetById = computed(() => {
    const map = new Map();
    localBoardWidgets.value.forEach((widget) => map.set(String(widget.id), widget));
    return map;
});

function selectStage(stageId) {
    activeStageId.value = Number(stageId);
    activeWidgetId.value = null;
}

function selectBoardWidget(widgetId) {
    activeWidgetId.value = String(widgetId);
    activeStageId.value = null;
}

function selectBoardComments() {
    activeStageId.value = null;
    activeWidgetId.value = null;
}

function stageCommentCount(stageId) {
    return (props.comments ?? []).filter((row) => Number(row.funnel_stage_id) === Number(stageId)).length;
}

function isPanTarget(target) {
    if (!(target instanceof Element)) {
        return false;
    }

    if (
        target.closest('.funnel-stage-block')
        || target.closest('.funnel-board-widget')
        || target.closest('.funnel-comment-stack')
        || target.closest('.funnel-board-questions')
    ) {
        return false;
    }

    return target.classList.contains('canvas-viewport')
        || target.classList.contains('canvas-surface')
        || target.classList.contains('funnel-canvas__arrows')
        || Boolean(target.closest('.funnel-canvas__arrows'));
}

function outcomeDestination(outcome) {
    if (outcome.target_type === 'widget' && outcome.target_widget_id) {
        const widget = widgetById.value.get(String(outcome.target_widget_id));
        return widget ? `Виджет: ${widget.name || widget.widget_slug}` : 'Виджет не найден';
    }

    if (outcome.mode === 'stay' || !outcome.target_stage_id
        || Number(outcome.target_stage_id) === Number(activeStage.value?.id)) {
        return 'Остаётся';
    }

    const target = stageById.value.get(Number(outcome.target_stage_id));
    if (!target) {
        return 'Этап не найден';
    }

    return `Этап ${target.number}: ${target.name}`;
}

function outcomeStyle(outcome) {
    if (outcome.target_type === 'widget') {
        return 'move';
    }

    const target = outcome.target_stage_id != null
        ? stageById.value.get(Number(outcome.target_stage_id))
        : null;

    return resolveOutcomeStyle(outcome, activeStage.value, target);
}

function widgetNameBySlug(slug) {
    if (!slug) {
        return '';
    }

    const fromStage = (activeStage.value?.widgets ?? []).find((row) => row.widget_slug === slug);
    if (fromStage?.name) {
        return fromStage.name;
    }

    return props.f5WidgetCatalog?.widgets?.find((widget) => widget.slug === slug)?.name || slug;
}
</script>

<template>
    <Head :title="`${funnel.name} — ${project.company_name}`" />

    <div class="page--canvas project-canvas funnel-canvas public-funnel-board">
        <header class="public-board-toolbar">
            <div class="public-board-toolbar__main">
                <p class="public-board-toolbar__eyebrow">{{ project.company_name }}</p>
                <h1 class="public-board-toolbar__title">{{ funnel.name }}</h1>
                <p class="public-board-toolbar__hint">
                    Просмотр схемы воронок · можно оставлять комментарии к доске или этапу
                </p>
                <FunnelBoardSwitcher
                    :funnels="funnels"
                    :active-id="funnel.id"
                    url-key="url"
                />
            </div>

            <div class="public-board-toolbar__right">
                <div class="funnel-canvas-legend" aria-label="Легенда">
                    <span><i class="funnel-dot funnel-dot--blue" />переход</span>
                    <span><i class="funnel-dot funnel-dot--green" />успех</span>
                    <span><i class="funnel-dot funnel-dot--red" />отказ</span>
                    <span><i class="funnel-dot funnel-dot--orange" />остаётся</span>
                </div>
                <button
                    type="button"
                    class="f5-btn f5-btn--ghost f5-btn--sm funnel-canvas-lose-toggle"
                    :class="{ 'is-active': showLoseArrows }"
                    :aria-pressed="showLoseArrows"
                    :title="showLoseArrows
                        ? 'Скрыть постоянные стрелки в отказ'
                        : 'Всегда показывать переходы в отказ / закрыто'"
                    @click="toggleShowLoseArrows"
                >
                    {{ showLoseArrows ? 'Отказ: всегда' : 'Отказ: по выбору' }}
                </button>
                <div class="public-board-zoom">
                    <button type="button" class="f5-btn f5-btn--ghost f5-btn--sm" @click="zoomOut">−</button>
                    <span>{{ zoomLabel }}</span>
                    <button type="button" class="f5-btn f5-btn--ghost f5-btn--sm" @click="zoomIn">+</button>
                    <button type="button" class="f5-btn f5-btn--ghost f5-btn--sm" @click="zoomReset">100%</button>
                </div>
            </div>
        </header>

        <div class="funnel-canvas__workspace">
            <div
                ref="viewportRef"
                class="canvas-viewport"
                @mousedown="onViewportMouseDown($event, isPanTarget)"
                @wheel="onWheel"
            >
                <FunnelBoardCommentMarkers
                    v-if="commentsEnabled && boardMarkersVisible"
                    mode="board"
                    :comments="comments"
                    :stages="localStages"
                    :active-stage-id="activeStageId"
                    @select-board="selectBoardComments"
                />
                <div class="canvas-surface funnel-canvas-surface" :style="surfaceStyle">
                    <FunnelCanvasArrows
                        :stages="localStages"
                        :board-widgets="localBoardWidgets"
                        :active-stage-id="activeStageId"
                        :active-widget-id="activeWidgetId"
                        :f5-widget-catalog="f5WidgetCatalog"
                        :show-lose-arrows="showLoseArrows"
                    />
                    <FunnelBoardCommentMarkers
                        v-if="commentsEnabled && boardMarkersVisible"
                        mode="stages"
                        :comments="comments"
                        :stages="localStages"
                        :active-stage-id="activeStageId"
                        @select-stage="selectStage"
                    />
                    <FunnelStageCanvasBlock
                        v-for="stage in localStages"
                        :key="stage.id"
                        :stage="stage"
                        readonly
                        :comment-count="commentsEnabled && boardMarkersVisible ? stageCommentCount(stage.id) : 0"
                        :is-active="Number(activeStageId) === Number(stage.id)"
                        @select="selectStage(stage.id)"
                    />
                    <FunnelBoardWidgetBlock
                        v-for="widget in localBoardWidgets"
                        :key="widget.id"
                        :widget="widget"
                        readonly
                        :is-active="activeWidgetId === widget.id"
                        @select="selectBoardWidget(widget.id)"
                    />
                </div>
            </div>

            <aside class="funnel-canvas__sidebar">
                <div class="funnel-canvas-sidebar__header">
                    <p v-if="activeStage" class="funnel-canvas-sidebar__eyebrow">
                        {{ funnel.name }} · этап {{ activeStage.number }}
                    </p>
                    <h2 class="funnel-canvas-sidebar__title">
                        {{ activeStage ? activeStage.name : 'Выберите этап на доске' }}
                    </h2>
                </div>

                <div v-if="activeStage" class="funnel-canvas-sidebar__detail">
                    <div v-if="activeStage.action" class="funnel-canvas-auto">
                        <b>Автозадача</b>
                        {{ activeStage.action }}
                    </div>
                    <div v-else class="funnel-canvas-hint">Автозадача не указана.</div>

                    <div class="funnel-canvas-chips">
                        <span v-if="activeStage.stage_type" class="funnel-canvas-chip">
                            Тип: {{ activeStage.stage_type }}
                        </span>
                        <span v-if="activeStage.deadline" class="funnel-canvas-chip">
                            Срок: {{ activeStage.deadline }}
                        </span>
                    </div>

                    <h3 class="funnel-canvas-sidebar__subtitle">Результаты этапа</h3>

                    <div v-if="(activeStage.outcomes ?? []).length" class="funnel-canvas-flows">
                        <div
                            v-for="(outcome, index) in activeStage.outcomes"
                            :key="index"
                            class="funnel-canvas-flow"
                        >
                            <div class="funnel-canvas-flow__box">
                                <b>Результат {{ index + 1 }}</b>
                                {{ outcome.result }}
                                <span v-if="outcome.label" class="funnel-canvas-flow__comment">
                                    {{ outcome.label }}
                                </span>
                                <span
                                    v-if="outcome.widget_slug"
                                    class="funnel-canvas-flow__comment"
                                >
                                    Виджет: {{ widgetNameBySlug(outcome.widget_slug) }}
                                    <template v-if="outcome.widget_comment">
                                        — {{ outcome.widget_comment }}
                                    </template>
                                </span>
                            </div>
                            <div class="funnel-canvas-flow__arr">→</div>
                            <div
                                class="funnel-canvas-flow__box"
                                :class="`funnel-canvas-flow__box--${outcomeStyle(outcome)}`"
                            >
                                <b>Куда попадёт сделка</b>
                                {{ outcomeDestination(outcome) }}
                            </div>
                        </div>
                    </div>
                    <p v-else class="funnel-canvas-hint">
                        Для этого этапа результаты пока не описаны.
                    </p>

                    <p v-if="activeStage.comment" class="funnel-canvas-hint">
                        {{ activeStage.comment }}
                    </p>

                    <FunnelBoardCommentsPanel
                        v-if="commentsEnabled"
                        v-model:board-markers-visible="boardMarkersVisible"
                        :comments="comments"
                        :stages="localStages"
                        :active-stage-id="activeStageId"
                        :store-url="urls.comments_store"
                        :destroy-base-url="urls.comments_destroy_base"
                        can-post
                        can-manage
                    />
                    <p v-else class="funnel-canvas-hint">
                        Комментарии на этой доске отключены.
                    </p>
                </div>
                <div v-else class="funnel-canvas-sidebar__form">
                    <p class="funnel-canvas-hint">
                        Нажмите карточку этапа, чтобы посмотреть автозадачу и куда уйдёт сделка.
                    </p>
                    <FunnelBoardCommentsPanel
                        v-if="commentsEnabled"
                        v-model:board-markers-visible="boardMarkersVisible"
                        :comments="comments"
                        :stages="localStages"
                        :active-stage-id="null"
                        :store-url="urls.comments_store"
                        :destroy-base-url="urls.comments_destroy_base"
                        can-post
                        can-manage
                    />
                    <p v-else class="funnel-canvas-hint">
                        Комментарии на этой доске отключены.
                    </p>
                </div>
            </aside>
        </div>
    </div>
</template>
