<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import CanvasLayout from '../../Layouts/CanvasLayout.vue';
import CanvasToolbar from '../../Components/Canvas/CanvasToolbar.vue';
import FunnelStageCanvasBlock from '../../Components/Canvas/FunnelStageCanvasBlock.vue';
import FunnelBoardWidgetBlock from '../../Components/Canvas/FunnelBoardWidgetBlock.vue';
import FunnelCanvasArrows from '../../Components/Canvas/FunnelCanvasArrows.vue';
import FunnelCanvasToolRail from '../../Components/Canvas/FunnelCanvasToolRail.vue';
import FunnelStageOutcomesEditor from '../../Components/Funnels/FunnelStageOutcomesEditor.vue';
import FunnelStageWidgetsEditor from '../../Components/Funnels/FunnelStageWidgetsEditor.vue';
import FunnelWidgetPickerModal from '../../Components/Funnels/FunnelWidgetPickerModal.vue';
import FunnelBoardSwitcher from '../../Components/Funnels/FunnelBoardSwitcher.vue';
import { useCanvasViewport } from '../../composables/useCanvasViewport';
import { useShowLoseArrows } from '../../composables/useShowLoseArrows';
import { submitCanvasForm } from '../../utils/canvasForm';
import { resolveOutcomeStyle, resolveStageKind } from '../../utils/funnelStageKind';
import { confirmDialog } from '../../utils/confirmDialog';

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
    viewport: {
        type: Object,
        default: () => ({}),
    },
    urls: {
        type: Object,
        required: true,
    },
    shareUrl: {
        type: String,
        default: null,
    },
    shareSettings: {
        type: Object,
        default: () => ({}),
    },
    f5WidgetCatalog: {
        type: Object,
        default: () => ({ widgets: [], categories: {} }),
    },
});

const canEdit = computed(() => props.project.can_edit !== false);
const localStages = ref(props.funnel.stages.map((stage) => ({ ...stage })));
const localBoardWidgets = ref([...(props.funnel.board_widgets ?? [])].map((row) => ({ ...row })));
const draggingStageId = ref(null);
const draggingWidgetId = ref(null);
const activeStageId = ref(null);
const activeWidgetId = ref(null);
const selectionKind = ref(null); // stage | widget | null
const tool = ref('select'); // select | connect
const connectState = ref(null); // { kind, id, outcomeIndex? }
const connectCursor = ref(null); // { x, y } in canvas coords
const showWidgetPicker = ref(false);
const sidebarCollapsed = ref(false);
const { showLoseArrows } = useShowLoseArrows(computed(() => props.funnel.id));
const resultPrompt = ref({
    visible: false,
    value: '',
    resolve: null,
});
const resultPromptInput = ref(null);
const sidebarMode = ref('detail'); // detail | edit | create | widget
const funnelName = ref(props.funnel.name);
const funnelDescription = ref(props.funnel.description ?? '');
const clientShareUrl = ref(props.shareUrl ?? '');
const shareBusy = ref(false);
const shareFeedback = ref('');
const sharePanelOpen = ref(false);
const shareForm = ref({
    expires_at: props.shareSettings?.expires_at ?? '',
    password: '',
    clear_password: false,
    comments_enabled: props.shareSettings?.comments_enabled !== false,
    has_password: !!props.shareSettings?.has_password,
    expires_at_label: props.shareSettings?.expires_at_label ?? '',
    is_expired: !!props.shareSettings?.is_expired,
});
const editBoardWidget = ref({ comment: '', next_type: null, next_id: null });

watch(
    () => props.shareUrl,
    (value) => {
        clientShareUrl.value = value ?? '';
    },
);

watch(
    () => props.shareSettings,
    (value) => {
        shareForm.value.expires_at = value?.expires_at ?? '';
        shareForm.value.comments_enabled = value?.comments_enabled !== false;
        shareForm.value.has_password = !!value?.has_password;
        shareForm.value.expires_at_label = value?.expires_at_label ?? '';
        shareForm.value.is_expired = !!value?.is_expired;
        shareForm.value.password = '';
        shareForm.value.clear_password = false;
    },
    { deep: true },
);

const newStage = ref(emptyStageForm());
const editStage = ref(emptyStageForm());

let dragStart = null;

const {
    viewportRef,
    surfaceStyle,
    zoomLabel,
    status,
    zoom,
    offsetX,
    offsetY,
    setStatus,
    scheduleSave,
    viewportPayload,
    onWheel,
    onViewportMouseDown,
    zoomIn,
    zoomOut,
    zoomReset,
} = useCanvasViewport(props.viewport, canEdit.value ? props.urls.save : null, () => ({
    stages: localStages.value.map((stage) => ({
        id: stage.id,
        canvas_pos_x: Math.round(stage.canvas_pos_x),
        canvas_pos_y: Math.round(stage.canvas_pos_y),
    })),
    board_widgets: localBoardWidgets.value.map((widget) => ({
        id: widget.id,
        widget_slug: widget.widget_slug,
        comment: widget.comment ?? null,
        canvas_pos_x: Math.round(widget.canvas_pos_x),
        canvas_pos_y: Math.round(widget.canvas_pos_y),
        next_type: widget.next_type ?? null,
        next_id: widget.next_id ?? null,
        path_points: Array.isArray(widget.path_points) ? widget.path_points : [],
    })),
    viewport: viewportPayload(),
}));

const activeStage = computed(() => localStages.value.find((s) => s.id === activeStageId.value) ?? null);
const activeBoardWidget = computed(() => localBoardWidgets.value.find((w) => w.id === activeWidgetId.value) ?? null);

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

const connectHint = computed(() => {
    if (tool.value !== 'connect') return '';
    if (!connectState.value) return 'Кликните источник (этап или виджет)';
    if (connectState.value.kind === 'stage' && connectState.value.outcomeIndex == null) {
        return 'Кликните цель — затем введите название результата';
    }
    return 'Кликните цель (этап или виджет на доске)';
});

const connectPreview = computed(() => {
    if (tool.value !== 'connect' || !connectState.value || !connectCursor.value) {
        return null;
    }

    let from = null;

    if (connectState.value.kind === 'stage') {
        const stage = stageById.value.get(Number(connectState.value.id));
        if (!stage) return null;
        from = { kind: 'stage', ref: stage };
    } else if (connectState.value.kind === 'widget') {
        const widget = widgetById.value.get(String(connectState.value.id));
        if (!widget) return null;
        from = { kind: 'widget', ref: widget };
    }

    if (!from) {
        return null;
    }

    return {
        from,
        to: {
            kind: 'point',
            x: connectCursor.value.x,
            y: connectCursor.value.y,
        },
    };
});

function clientToCanvas(clientX, clientY) {
    const viewport = viewportRef.value;

    if (!viewport) {
        return null;
    }

    const rect = viewport.getBoundingClientRect();
    const x = (clientX - rect.left - offsetX.value) / zoom.value;
    const y = (clientY - rect.top - offsetY.value) / zoom.value;

    return {
        x: Math.round(x),
        y: Math.round(y),
    };
}

function clearConnectPreview() {
    connectCursor.value = null;
}

watch(connectState, (value) => {
    if (!value) {
        clearConnectPreview();
    }
});

watch(tool, (value) => {
    if (value !== 'connect') {
        clearConnectPreview();
    }
});

const canDeleteSelection = computed(() => {
    if (!canEdit.value) return false;
    if (selectionKind.value === 'widget' && activeBoardWidget.value) return true;
    if (selectionKind.value === 'stage' && activeStage.value && (activeStage.value.outcomes ?? []).length) return true;
    return false;
});

const catalogForPicker = computed(() => (props.f5WidgetCatalog?.widgets ?? []).filter((w) => w.slug !== 'custom'));

/** While dragging arrow waypoints, ignore Inertia funnel props so mid-save reloads cannot snap arrows back. */
const waypointDragging = ref(false);
let pendingWaypointStageId = null;
let pendingWaypointWidgetId = null;

function cloneStageFromProps(stage) {
    return {
        ...stage,
        outcomes: Array.isArray(stage.outcomes)
            ? stage.outcomes.map((row) => ({
                ...row,
                path_points: Array.isArray(row.path_points)
                    ? row.path_points.map((point) => ({ ...point }))
                    : [],
            }))
            : [],
        widgets: Array.isArray(stage.widgets)
            ? stage.widgets.map((row) => ({ ...row }))
            : [],
    };
}

function cloneWidgetFromProps(widget) {
    return {
        ...widget,
        path_points: Array.isArray(widget.path_points)
            ? widget.path_points.map((point) => ({ ...point }))
            : [],
    };
}

watch(
    () => props.funnel,
    (funnel) => {
        // Avoid snap-back while the user is actively moving geometry on the board.
        if (waypointDragging.value || draggingStageId.value != null || draggingWidgetId.value != null) {
            return;
        }

        localStages.value = funnel.stages.map((stage) => cloneStageFromProps(stage));
        localBoardWidgets.value = [...(funnel.board_widgets ?? [])].map((row) => cloneWidgetFromProps(row));
        funnelName.value = funnel.name;
        funnelDescription.value = funnel.description ?? '';

        if (activeStageId.value && !funnel.stages.some((s) => s.id === activeStageId.value)) {
            activeStageId.value = null;
            if (selectionKind.value === 'stage') {
                selectionKind.value = null;
            }
        }

        if (activeWidgetId.value && !localBoardWidgets.value.some((w) => w.id === activeWidgetId.value)) {
            activeWidgetId.value = null;
            if (selectionKind.value === 'widget') {
                selectionKind.value = activeStageId.value ? 'stage' : null;
                sidebarMode.value = 'detail';
            }
        }

        if (sidebarMode.value === 'edit' && activeStage.value) {
            loadEditForm(activeStage.value);
        }
        if (sidebarMode.value === 'widget' && activeBoardWidget.value) {
            loadWidgetForm(activeBoardWidget.value);
        }
    },
    { deep: true },
);

function emptyStageForm() {
    return {
        name: '',
        action: '',
        comment: '',
        stage_type: '',
        deadline: '',
        kind: 'normal',
        outcomes: [],
        widgets: [],
    };
}

function loadEditForm(stage) {
    editStage.value = {
        name: stage.name ?? '',
        action: stage.action ?? '',
        comment: stage.comment ?? '',
        stage_type: stage.stage_type ?? '',
        deadline: stage.deadline ?? '',
        kind: stage.kind || 'normal',
        outcomes: Array.isArray(stage.outcomes) ? stage.outcomes.map((row) => ({ ...row })) : [],
        widgets: Array.isArray(stage.widgets)
            ? stage.widgets.map((row) => ({
                widget_slug: row.widget_slug,
                comment: row.comment ?? '',
            }))
            : [],
    };
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

function outcomesLaunchingWidgets(stage) {
    return (stage?.outcomes ?? []).filter((row) => row?.widget_slug);
}

function loadWidgetForm(widget) {
    editBoardWidget.value = {
        comment: widget.comment ?? '',
        next_type: widget.next_type ?? null,
        next_id: widget.next_id ?? null,
    };
}

function selectStage(stageId) {
    if (tool.value === 'connect') {
        handleConnectClick('stage', Number(stageId));
        return;
    }

    activeStageId.value = Number(stageId);
    activeWidgetId.value = null;
    selectionKind.value = 'stage';
    sidebarMode.value = 'detail';
    sidebarCollapsed.value = false;
}

function selectBoardWidget(widgetId) {
    if (tool.value === 'connect') {
        handleConnectClick('widget', String(widgetId));
        return;
    }

    activeWidgetId.value = String(widgetId);
    activeStageId.value = null;
    selectionKind.value = 'widget';
    sidebarMode.value = 'widget';
    sidebarCollapsed.value = false;
    const widget = widgetById.value.get(String(widgetId));
    if (widget) loadWidgetForm(widget);
}

function openEdit() {
    if (!activeStage.value || !canEdit.value) {
        return;
    }
    loadEditForm(activeStage.value);
    sidebarMode.value = 'edit';
    sidebarCollapsed.value = false;
}

function openCreate() {
    if (!canEdit.value) {
        return;
    }
    tool.value = 'select';
    connectState.value = null;
    newStage.value = emptyStageForm();
    sidebarMode.value = 'create';
    sidebarCollapsed.value = false;
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

function isPanTarget(target) {
    if (!(target instanceof Element)) {
        return false;
    }

    if (
        target.closest('.funnel-stage-block')
        || target.closest('.funnel-board-widget')
        || target.closest('.funnel-canvas__waypoint')
        || target.closest('.funnel-canvas__arrow-hit')
    ) {
        return false;
    }

    return target.classList.contains('canvas-viewport')
        || target.classList.contains('canvas-surface')
        || target.classList.contains('funnel-canvas__arrows')
        || target.closest('.funnel-canvas__arrows');
}

function onStagePointerDown(stageId, event) {
    if (!canEdit.value || tool.value === 'connect') {
        return;
    }
    if (event.button !== 0) {
        return;
    }

    const stage = localStages.value.find((item) => Number(item.id) === Number(stageId));

    if (!stage) {
        return;
    }

    dragStart = {
        type: 'stage',
        id: Number(stageId),
        x: event.clientX,
        y: event.clientY,
        left: stage.canvas_pos_x,
        top: stage.canvas_pos_y,
        moved: false,
    };

    event.preventDefault();
}

function onWidgetPointerDown(widgetId, event) {
    if (!canEdit.value || tool.value === 'connect') {
        return;
    }
    if (event.button !== 0) {
        return;
    }

    const widget = localBoardWidgets.value.find((item) => item.id === widgetId);

    if (!widget) {
        return;
    }

    dragStart = {
        type: 'widget',
        id: String(widgetId),
        x: event.clientX,
        y: event.clientY,
        left: widget.canvas_pos_x,
        top: widget.canvas_pos_y,
        moved: false,
    };

    event.preventDefault();
}

function onMouseMove(event) {
    if (tool.value === 'connect' && connectState.value) {
        connectCursor.value = clientToCanvas(event.clientX, event.clientY);
    }

    if (!dragStart) {
        return;
    }

    const rawDx = event.clientX - dragStart.x;
    const rawDy = event.clientY - dragStart.y;

    if (!dragStart.moved && Math.hypot(rawDx, rawDy) < 6) {
        return;
    }

    dragStart.moved = true;
    const dx = rawDx / zoom.value;
    const dy = rawDy / zoom.value;

    if (dragStart.type === 'stage') {
        const stage = localStages.value.find((item) => Number(item.id) === dragStart.id);
        if (!stage) return;
        draggingStageId.value = dragStart.id;
        stage.canvas_pos_x = Math.round(Math.max(0, dragStart.left + dx));
        stage.canvas_pos_y = Math.round(Math.max(0, dragStart.top + dy));
        return;
    }

    const widget = localBoardWidgets.value.find((item) => item.id === dragStart.id);
    if (!widget) return;
    draggingWidgetId.value = dragStart.id;
    widget.canvas_pos_x = Math.round(Math.max(0, dragStart.left + dx));
    widget.canvas_pos_y = Math.round(Math.max(0, dragStart.top + dy));
}

function onMouseUp() {
    if (!dragStart) {
        return;
    }

    const didDrag = dragStart.moved;
    draggingStageId.value = null;
    draggingWidgetId.value = null;
    dragStart = null;

    if (didDrag) {
        scheduleSave();
    }
}

function uuid() {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
        return crypto.randomUUID();
    }

    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = Math.random() * 16 | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
}

async function persistBoardWidgets() {
    scheduleSave();
}

async function persistStageOutcomes(stage, outcomes, { skipReload = false } = {}) {
    const result = await submitCanvasForm({
        url: stage.urls.update,
        fields: {
            name: stage.name,
            action: stage.action ?? '',
            comment: stage.comment ?? '',
            stage_type: stage.stage_type ?? '',
            deadline: stage.deadline ?? '',
            kind: stage.kind || 'normal',
            outcomes,
            widgets: Array.isArray(stage.widgets)
                ? stage.widgets.map((row) => ({
                    widget_slug: row.widget_slug,
                    comment: row.comment ?? '',
                }))
                : [],
        },
        method: 'PUT',
        onStatus: (text, state) => setStatus(text, state),
        reloadOnly: ['funnel'],
        skipReload,
    });

    return result.ok;
}

function startConnectFromOutcome(outcomeIndex) {
    if (!activeStage.value || !canEdit.value) return;
    tool.value = 'connect';
    connectState.value = {
        kind: 'stage',
        id: Number(activeStage.value.id),
        outcomeIndex,
    };
}

function askResultName(defaultValue = '') {
    return new Promise((resolve) => {
        resultPrompt.value = {
            visible: true,
            value: defaultValue,
            resolve,
        };
        nextTick(() => {
            resultPromptInput.value?.focus?.();
            resultPromptInput.value?.select?.();
        });
    });
}

function submitResultPrompt() {
    const name = (resultPrompt.value.value ?? '').trim();
    if (!name) {
        return;
    }

    const resolve = resultPrompt.value.resolve;
    resultPrompt.value = { visible: false, value: '', resolve: null };
    resolve?.(name);
}

function cancelResultPrompt() {
    const resolve = resultPrompt.value.resolve;
    resultPrompt.value = { visible: false, value: '', resolve: null };
    resolve?.(null);
}

async function handleConnectClick(kind, id) {
    if (!canEdit.value) return;

    if (!connectState.value) {
        if (kind === 'stage') {
            activeStageId.value = Number(id);
            activeWidgetId.value = null;
            selectionKind.value = 'stage';
            sidebarMode.value = 'detail';
            connectState.value = { kind: 'stage', id: Number(id), outcomeIndex: null };
            return;
        }

        connectState.value = { kind: 'widget', id: String(id) };
        activeWidgetId.value = String(id);
        activeStageId.value = null;
        selectionKind.value = 'widget';
        sidebarMode.value = 'widget';
        return;
    }

    // Source stage without outcome: ask result name, then create outcome + connect
    if (connectState.value.kind === 'stage' && connectState.value.outcomeIndex == null) {
        if (kind === 'stage' && Number(id) === Number(connectState.value.id)) {
            return;
        }

        const source = stageById.value.get(Number(connectState.value.id));
        if (!source) return;

        const resultName = await askResultName('');
        if (!resultName) {
            return;
        }

        const outcomes = [...(source.outcomes ?? [])];
        const targetPatch = kind === 'widget'
            ? {
                result: resultName,
                mode: 'move',
                target_type: 'widget',
                target_widget_id: String(id),
                target_stage_id: null,
                label: null,
                widget_slug: null,
                path_points: [],
            }
            : {
                result: resultName,
                mode: resolveStageKind(stageById.value.get(Number(id))) === 'lose'
                    ? 'lose'
                    : resolveStageKind(stageById.value.get(Number(id))) === 'win'
                        ? 'win'
                        : 'move',
                target_type: 'stage',
                target_stage_id: Number(id),
                target_widget_id: null,
                label: null,
                widget_slug: null,
                path_points: [],
            };

        outcomes.push(targetPatch);
        const ok = await persistStageOutcomes(source, outcomes);
        if (ok) {
            tool.value = 'select';
            connectState.value = null;
        }
        return;
    }

    if (connectState.value.kind === 'stage') {
        const source = stageById.value.get(Number(connectState.value.id));
        if (!source) return;
        const outcomes = (source.outcomes ?? []).map((row, index) => {
            if (index !== connectState.value.outcomeIndex) return row;
            if (kind === 'widget') {
                return {
                    ...row,
                    mode: 'move',
                    target_type: 'widget',
                    target_widget_id: String(id),
                    target_stage_id: null,
                    widget_slug: null,
                    path_points: [],
                };
            }
            const target = stageById.value.get(Number(id));
            return {
                ...row,
                mode: resolveStageKind(target) === 'lose' ? 'lose' : resolveStageKind(target) === 'win' ? 'win' : 'move',
                target_type: 'stage',
                target_stage_id: Number(id),
                target_widget_id: null,
                path_points: [],
            };
        });
        const ok = await persistStageOutcomes(source, outcomes);
        if (ok) {
            tool.value = 'select';
            connectState.value = null;
        }
        return;
    }

    if (connectState.value.kind === 'widget') {
        const source = localBoardWidgets.value.find((w) => w.id === connectState.value.id);
        if (!source) return;
        if (kind === 'widget' && String(id) === String(source.id)) return;

        source.next_type = kind === 'widget' ? 'widget' : 'stage';
        source.next_id = kind === 'widget' ? String(id) : Number(id);
        source.path_points = [];
        await persistBoardWidgets();
        tool.value = 'select';
        connectState.value = null;
        setStatus('Связь сохранена', 'saved');
    }
}

function openWidgetPicker() {
    if (!canEdit.value) return;
    showWidgetPicker.value = true;
    tool.value = 'select';
}

function onBoardWidgetsUpdate(next) {
    if (!canEdit.value) return;

    localBoardWidgets.value = Array.isArray(next) ? next.map((row) => ({ ...row })) : [];

    if (
        activeWidgetId.value
        && editBoardWidget.value
        && sidebarMode.value === 'widget'
    ) {
        const active = localBoardWidgets.value.find((widget) => String(widget.id) === String(activeWidgetId.value));
        if (active) {
            editBoardWidget.value.comment = active.comment ?? '';
        }
    }

    persistBoardWidgets();
}

function addBoardWidget(catalogWidget) {
    if (!canEdit.value || !catalogWidget?.slug) return;

    const catalog = catalogForPicker.value.find((w) => w.slug === catalogWidget.slug) ?? catalogWidget;

    const offset = localBoardWidgets.value.length * 24;
    localBoardWidgets.value.push({
        id: uuid(),
        widget_slug: catalog.slug,
        name: catalog.name,
        url: catalog.url ?? null,
        comment: '',
        canvas_pos_x: 120 + offset,
        canvas_pos_y: 140 + offset,
        next_type: null,
        next_id: null,
    });

    showWidgetPicker.value = false;
    persistBoardWidgets();
    selectBoardWidget(localBoardWidgets.value[localBoardWidgets.value.length - 1].id);
    tool.value = 'select';
}

function onBoardWidgetNextChange(raw) {
    if (!raw) {
        editBoardWidget.value.next_type = null;
        editBoardWidget.value.next_id = null;
        return;
    }

    const [type, ...rest] = raw.split(':');
    editBoardWidget.value.next_type = type;
    editBoardWidget.value.next_id = type === 'stage' ? Number(rest.join(':')) : rest.join(':');
}

async function saveActiveBoardWidget() {
    const widget = activeBoardWidget.value;
    if (!widget) return;

    widget.comment = editBoardWidget.value.comment || null;
    widget.next_type = editBoardWidget.value.next_type || null;
    widget.next_id = editBoardWidget.value.next_type
        ? (editBoardWidget.value.next_type === 'stage'
            ? Number(editBoardWidget.value.next_id)
            : String(editBoardWidget.value.next_id))
        : null;

    await persistBoardWidgets();
    setStatus('Виджет сохранён', 'saved');
}

async function deleteSelection() {
    if (!canEdit.value) return;

    if (selectionKind.value === 'widget' && activeBoardWidget.value) {
        const confirmed = await confirmDialog({
            title: 'Удалить виджет с доски?',
            message: `Виджет «${activeBoardWidget.value.name || activeBoardWidget.value.widget_slug}» будет удалён. Связи этапов на него очистятся при следующем сохранении этапов.`,
        });
        if (!confirmed) return;

        const removedId = activeBoardWidget.value.id;
        localBoardWidgets.value = localBoardWidgets.value.filter((w) => w.id !== removedId);
        localBoardWidgets.value.forEach((w) => {
            if (w.next_type === 'widget' && String(w.next_id) === String(removedId)) {
                w.next_type = null;
                w.next_id = null;
            }
        });

        // Clear stage outcomes pointing to removed widget
        for (const stage of localStages.value) {
            const outcomes = (stage.outcomes ?? []).map((row) => (
                row.target_widget_id === removedId
                    ? { ...row, target_type: null, target_widget_id: null, mode: 'stay' }
                    : row
            ));
            const changed = JSON.stringify(outcomes) !== JSON.stringify(stage.outcomes ?? []);
            if (changed) {
                await persistStageOutcomes(stage, outcomes);
            }
        }

        activeWidgetId.value = null;
        selectionKind.value = null;
        sidebarMode.value = 'detail';
        await persistBoardWidgets();
        return;
    }

    if (selectionKind.value === 'stage' && activeStage.value) {
        const confirmed = await confirmDialog({
            title: 'Удалить последний результат?',
            message: 'Будет удалён последний результат автозадачи выбранного этапа.',
        });
        if (!confirmed) return;

        const outcomes = [...(activeStage.value.outcomes ?? [])];
        if (!outcomes.length) return;
        outcomes.pop();
        await persistStageOutcomes(activeStage.value, outcomes);
    }
}

async function deleteOutcomeAt(index) {
    if (!activeStage.value || !canEdit.value) return;
    const outcomes = (activeStage.value.outcomes ?? []).filter((_, i) => i !== index);
    await persistStageOutcomes(activeStage.value, outcomes);
}

function syncEditFormPathPoints(stageId, outcomeIndex, pathPoints) {
    if (
        sidebarMode.value === 'edit'
        && Number(activeStageId.value) === Number(stageId)
        && editStage.value.outcomes?.[outcomeIndex]
    ) {
        editStage.value.outcomes[outcomeIndex] = {
            ...editStage.value.outcomes[outcomeIndex],
            path_points: pathPoints,
        };
    }
}

function onOutcomeWaypointsUpdate({ stageId, outcomeIndex, pathPoints }) {
    if (!canEdit.value) return;

    const stage = localStages.value.find((item) => Number(item.id) === Number(stageId));
    if (!stage?.outcomes?.[outcomeIndex]) return;

    stage.outcomes[outcomeIndex] = {
        ...stage.outcomes[outcomeIndex],
        path_points: pathPoints,
    };

    syncEditFormPathPoints(stageId, outcomeIndex, pathPoints);
    pendingWaypointStageId = Number(stageId);

    // During drag only update local geometry; persist on drag-end to avoid reload snap-back.
    if (!waypointDragging.value) {
        persistStageOutcomes(stage, stage.outcomes, { skipReload: true });
    }
}

function onWidgetWaypointsUpdate({ widgetId, pathPoints }) {
    if (!canEdit.value) return;

    const widget = localBoardWidgets.value.find((item) => String(item.id) === String(widgetId));
    if (!widget) return;

    widget.path_points = pathPoints;
    pendingWaypointWidgetId = String(widgetId);

    if (!waypointDragging.value) {
        scheduleSave();
    }
}

function onWaypointDragStart() {
    waypointDragging.value = true;
}

function onWaypointDragEnd() {
    waypointDragging.value = false;

    if (pendingWaypointStageId != null) {
        const stage = localStages.value.find((item) => Number(item.id) === Number(pendingWaypointStageId));
        pendingWaypointStageId = null;
        if (stage) {
            persistStageOutcomes(stage, stage.outcomes, { skipReload: true });
        }
    }

    if (pendingWaypointWidgetId != null) {
        pendingWaypointWidgetId = null;
        scheduleSave();
    }
}

function resetOutcomePath(index) {
    if (!activeStage.value || !canEdit.value) return;
    onOutcomeWaypointsUpdate({
        stageId: Number(activeStage.value.id),
        outcomeIndex: index,
        pathPoints: [],
    });
}

async function saveFunnelName() {
    await submitCanvasForm({
        url: props.funnel.urls.update,
        fields: { name: funnelName.value, description: funnelDescription.value },
        method: 'PUT',
        onStatus: (text, state) => setStatus(text, state),
        reloadOnly: ['funnel'],
    });
}

async function createStage() {
    const result = await submitCanvasForm({
        url: props.funnel.urls.stages_store,
        fields: { ...newStage.value },
        onStatus: (text, state) => setStatus(text, state),
        reloadOnly: ['funnel'],
    });

    if (result.ok) {
        newStage.value = emptyStageForm();
        sidebarMode.value = 'detail';
    }
}

async function saveActiveStage() {
    if (!activeStage.value) {
        return;
    }

    const liveOutcomes = activeStage.value.outcomes ?? [];
    const outcomes = (editStage.value.outcomes ?? []).map((row, index) => ({
        ...row,
        path_points: liveOutcomes[index]?.path_points ?? row.path_points ?? [],
    }));

    const result = await submitCanvasForm({
        url: activeStage.value.urls.update,
        fields: {
            ...editStage.value,
            outcomes,
        },
        method: 'PUT',
        onStatus: (text, state) => setStatus(text, state),
        reloadOnly: ['funnel'],
    });

    if (result.ok) {
        sidebarMode.value = 'detail';
    }
}

async function deleteActiveStage() {
    if (!activeStage.value) {
        return;
    }

    const result = await submitCanvasForm({
        url: activeStage.value.urls.destroy,
        fields: {},
        method: 'DELETE',
        confirmMessage: `Удалить этап «${activeStage.value.name}»?`,
        onStatus: (text, state) => setStatus(text, state),
        reloadOnly: ['funnel'],
    });

    if (result.ok) {
        sidebarMode.value = 'detail';
    }
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function applyShareResponse(data) {
    clientShareUrl.value = data.url ?? '';
    shareForm.value.expires_at = data.expires_at ?? '';
    shareForm.value.comments_enabled = data.comments_enabled !== false;
    shareForm.value.has_password = !!data.has_password;
    shareForm.value.expires_at_label = data.expires_at_label ?? '';
    shareForm.value.is_expired = !!data.is_expired;
    shareForm.value.password = '';
    shareForm.value.clear_password = false;
}

async function postShare(body = {}) {
    if (!props.urls.share) {
        return null;
    }

    shareBusy.value = true;
    shareFeedback.value = '';

    try {
        const response = await fetch(props.urls.share, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify(body),
        });

        if (!response.ok) {
            throw new Error('share failed');
        }

        const data = await response.json();
        applyShareResponse(data);
        return data;
    } catch {
        shareFeedback.value = 'Не удалось сохранить настройки ссылки';
        return null;
    } finally {
        shareBusy.value = false;
    }
}

async function ensureClientShareUrl(regenerate = false) {
    const data = await postShare({ regenerate });
    return data?.url ?? null;
}

async function copyClientLink() {
    const url = clientShareUrl.value || await ensureClientShareUrl(false);

    if (!url) {
        return;
    }

    sharePanelOpen.value = true;

    try {
        await navigator.clipboard.writeText(url);
        shareFeedback.value = 'Ссылка скопирована';
        setStatus('Ссылка для клиента скопирована', 'saved');
    } catch {
        shareFeedback.value = url;
        setStatus('Скопируйте ссылку вручную', 'error');
    }
}

async function regenerateClientLink() {
    const data = await postShare({ regenerate: true });
    const url = data?.url ?? null;

    if (!url) {
        return;
    }

    try {
        await navigator.clipboard.writeText(url);
        shareFeedback.value = 'Новая ссылка скопирована';
        setStatus('Новая ссылка скопирована', 'saved');
    } catch {
        shareFeedback.value = url;
    }
}

async function saveShareSettings() {
    const payload = {
        comments_enabled: shareForm.value.comments_enabled,
        expires_at: shareForm.value.expires_at || null,
    };

    if (shareForm.value.clear_password) {
        payload.clear_password = true;
    } else if (shareForm.value.password.trim() !== '') {
        payload.password = shareForm.value.password.trim();
    }

    const data = await postShare(payload);

    if (!data) {
        return;
    }

    shareFeedback.value = data.message || 'Настройки сохранены';
    setStatus(data.message || 'Настройки ссылки сохранены', 'saved');
}

async function revokeClientLink() {
    const confirmed = await confirmDialog({
        title: 'Отозвать ссылку?',
        message: 'Клиент больше не сможет открыть доску по текущей ссылке.',
        confirmLabel: 'Отозвать',
        variant: 'danger',
    });

    if (!confirmed) {
        return;
    }

    const data = await postShare({ revoke: true });

    if (!data) {
        return;
    }

    sharePanelOpen.value = false;
    shareFeedback.value = 'Ссылка отозвана';
    setStatus('Ссылка для клиента отозвана', 'saved');
}

onMounted(() => {
    document.addEventListener('mousemove', onMouseMove);
    document.addEventListener('mouseup', onMouseUp);
});

onBeforeUnmount(() => {
    document.removeEventListener('mousemove', onMouseMove);
    document.removeEventListener('mouseup', onMouseUp);
});
</script>

<template>
    <CanvasLayout :title="`${funnel.name} — Доска воронки`" variant="funnel-canvas">
        <FunnelWidgetPickerModal
            :open="showWidgetPicker"
            :catalog="f5WidgetCatalog"
            @close="showWidgetPicker = false"
            @select="addBoardWidget"
        />

        <Teleport to="body">
            <div
                v-if="resultPrompt.visible"
                class="fixed inset-0 z-[70] flex items-end justify-center p-4 sm:items-center"
                role="dialog"
                aria-modal="true"
                aria-labelledby="result-prompt-title"
            >
                <button
                    type="button"
                    class="absolute inset-0 bg-black/50"
                    aria-label="Закрыть"
                    @click="cancelResultPrompt"
                />
                <form
                    class="relative w-full max-w-md overflow-hidden rounded-2xl border border-f5-border bg-f5-surface shadow-xl dark:border-white/10 dark:bg-f5-black"
                    @submit.prevent="submitResultPrompt"
                >
                    <div class="space-y-3 px-5 py-4">
                        <h3
                            id="result-prompt-title"
                            class="text-lg font-semibold text-f5-text dark:text-white"
                        >
                            Название результата
                        </h3>
                        <p class="text-sm text-f5-text-muted dark:text-white/70">
                            Укажите текст результата автозадачи для этой связи.
                        </p>
                        <input
                            ref="resultPromptInput"
                            v-model="resultPrompt.value"
                            type="text"
                            class="f5-input"
                            placeholder="Например: Не дозвонились"
                            required
                            maxlength="1000"
                            @keydown.escape.prevent="cancelResultPrompt"
                        >
                    </div>
                    <div class="flex justify-end gap-2 border-t border-f5-border px-5 py-3 dark:border-white/10">
                        <button type="button" class="f5-btn f5-btn--ghost" @click="cancelResultPrompt">
                            Отмена
                        </button>
                        <button
                            type="submit"
                            class="f5-btn f5-btn--primary"
                            :disabled="!(resultPrompt.value || '').trim()"
                        >
                            Создать связь
                        </button>
                    </div>
                </form>
            </div>
        </Teleport>

        <CanvasToolbar
            :back-href="project.urls.canvas"
            back-label="← К доске проекта"
            :title="funnel.name"
            :zoom-label="zoomLabel"
            :status-text="status.text"
            :status-state="status.state"
            @zoom-in="zoomIn"
            @zoom-out="zoomOut"
            @zoom-reset="zoomReset"
        >
            <template #center>
                <div class="funnel-canvas-toolbar">
                    <FunnelBoardSwitcher
                        :funnels="funnels"
                        :active-id="funnel.id"
                        url-key="canvas_url"
                    />
                    <form class="canvas-toolbar__add-form" @submit.prevent="saveFunnelName">
                        <input
                            v-model="funnelName"
                            type="text"
                            class="f5-input !w-44 !py-1.5 text-xs"
                            placeholder="Название воронки"
                            required
                        >
                        <button type="submit" class="f5-btn f5-btn--ghost f5-btn--sm">
                            Сохранить
                        </button>
                    </form>
                    <div v-if="canEdit" class="funnel-canvas-share">
                        <button
                            type="button"
                            class="f5-btn f5-btn--primary f5-btn--sm"
                            :disabled="shareBusy"
                            title="Одна ссылка на все воронки проекта"
                            @click="copyClientLink"
                        >
                            Ссылка для клиента
                        </button>
                        <button
                            v-if="clientShareUrl"
                            type="button"
                            class="f5-btn f5-btn--ghost f5-btn--sm"
                            :disabled="shareBusy"
                            :aria-expanded="sharePanelOpen"
                            @click="sharePanelOpen = !sharePanelOpen"
                        >
                            Настройки
                        </button>
                        <button
                            v-if="clientShareUrl"
                            type="button"
                            class="f5-btn f5-btn--ghost f5-btn--sm"
                            :disabled="shareBusy"
                            title="Сгенерировать новую ссылку — старая перестанет работать"
                            @click="regenerateClientLink"
                        >
                            Обновить
                        </button>
                        <span v-if="shareFeedback" class="funnel-canvas-share__feedback">{{ shareFeedback }}</span>
                    </div>
                    <div class="funnel-canvas-legend" aria-label="Легенда">
                        <span><i class="funnel-dot funnel-dot--blue" />переход</span>
                        <span><i class="funnel-dot funnel-dot--green" />успех</span>
                        <span><i class="funnel-dot funnel-dot--red" />отказ</span>
                        <span><i class="funnel-dot funnel-dot--orange" />остаётся</span>
                    </div>
                </div>
            </template>
        </CanvasToolbar>

        <div
            v-if="canEdit && sharePanelOpen && clientShareUrl"
            class="funnel-canvas-share-panel"
        >
            <div class="funnel-canvas-share-panel__grid">
                <label class="funnel-canvas-share-panel__field">
                    <span>Срок действия</span>
                    <input
                        v-model="shareForm.expires_at"
                        type="datetime-local"
                        class="f5-input !py-1.5 text-xs"
                    >
                    <small v-if="shareForm.expires_at_label" class="text-f5-text-muted">
                        Сейчас: {{ shareForm.expires_at_label }}
                        <template v-if="shareForm.is_expired"> · истекла</template>
                    </small>
                    <small v-else class="text-f5-text-muted">Пусто — без срока</small>
                </label>

                <label class="funnel-canvas-share-panel__field">
                    <span>Пароль</span>
                    <input
                        v-model="shareForm.password"
                        type="password"
                        class="f5-input !py-1.5 text-xs"
                        :placeholder="shareForm.has_password ? 'Новый пароль (оставьте пустым, чтобы не менять)' : 'Не задан'"
                        autocomplete="new-password"
                    >
                    <label
                        v-if="shareForm.has_password"
                        class="funnel-canvas-share-panel__check"
                    >
                        <input v-model="shareForm.clear_password" type="checkbox">
                        Снять пароль
                    </label>
                </label>

                <label class="funnel-canvas-share-panel__check funnel-canvas-share-panel__check--block">
                    <input v-model="shareForm.comments_enabled" type="checkbox">
                    Разрешить комментарии клиенту
                </label>
            </div>

            <div class="funnel-canvas-share-panel__actions">
                <button
                    type="button"
                    class="f5-btn f5-btn--primary f5-btn--sm"
                    :disabled="shareBusy"
                    @click="saveShareSettings"
                >
                    Сохранить настройки
                </button>
                <button
                    type="button"
                    class="f5-btn f5-btn--ghost f5-btn--sm"
                    :disabled="shareBusy"
                    @click="revokeClientLink"
                >
                    Отозвать ссылку
                </button>
                <button
                    type="button"
                    class="f5-btn f5-btn--ghost f5-btn--sm"
                    @click="sharePanelOpen = false"
                >
                    Закрыть
                </button>
            </div>
        </div>

        <div class="funnel-canvas__workspace">
            <FunnelCanvasToolRail
                v-model:tool="tool"
                v-model:show-lose-arrows="showLoseArrows"
                :can-edit="canEdit"
                :can-delete="canDeleteSelection"
                :connect-hint="connectHint"
                @add-widget="openWidgetPicker"
                @add-stage="openCreate"
                @delete="deleteSelection"
            />

            <div
                ref="viewportRef"
                class="canvas-viewport"
                @mousedown="onViewportMouseDown($event, isPanTarget)"
                @wheel="onWheel"
            >
                <div class="canvas-surface funnel-canvas-surface" :style="surfaceStyle">
                    <FunnelCanvasArrows
                        :stages="localStages"
                        :board-widgets="localBoardWidgets"
                        :active-stage-id="activeStageId"
                        :active-widget-id="activeWidgetId"
                        :f5-widget-catalog="f5WidgetCatalog"
                        :editable="canEdit"
                        :show-lose-arrows="showLoseArrows"
                        :preview="connectPreview"
                        @update-outcome-waypoints="onOutcomeWaypointsUpdate"
                        @update-widget-waypoints="onWidgetWaypointsUpdate"
                        @waypoint-drag-start="onWaypointDragStart"
                        @waypoint-drag-end="onWaypointDragEnd"
                    />
                    <FunnelStageCanvasBlock
                        v-for="stage in localStages"
                        :key="stage.id"
                        :stage="stage"
                        :is-dragging="Number(draggingStageId) === Number(stage.id)"
                        :is-active="selectionKind === 'stage' && Number(activeStageId) === Number(stage.id)"
                        :connect-source="connectState?.kind === 'stage' && Number(connectState?.id) === Number(stage.id)"
                        @pointer-down="onStagePointerDown(stage.id, $event)"
                        @select="selectStage(stage.id)"
                    />
                    <FunnelBoardWidgetBlock
                        v-for="widget in localBoardWidgets"
                        :key="widget.id"
                        :widget="widget"
                        :is-dragging="draggingWidgetId === widget.id"
                        :is-active="selectionKind === 'widget' && activeWidgetId === widget.id"
                        :connect-source="connectState?.kind === 'widget' && connectState?.id === widget.id"
                        @pointer-down="onWidgetPointerDown(widget.id, $event)"
                        @select="selectBoardWidget(widget.id)"
                    />
                </div>
            </div>

            <button
                type="button"
                class="funnel-canvas__sidebar-toggle"
                :class="{ 'is-collapsed': sidebarCollapsed }"
                :title="sidebarCollapsed ? 'Показать панель' : 'Скрыть панель'"
                :aria-label="sidebarCollapsed ? 'Показать панель' : 'Скрыть панель'"
                @click="sidebarCollapsed = !sidebarCollapsed"
            >
                {{ sidebarCollapsed ? '‹' : '›' }}
            </button>

            <aside
                class="funnel-canvas__sidebar"
                :class="{ 'is-collapsed': sidebarCollapsed }"
                :aria-hidden="sidebarCollapsed ? 'true' : 'false'"
            >
                <div class="funnel-canvas-sidebar__tabs">
                    <button
                        type="button"
                        class="funnel-canvas-sidebar__tab"
                        :class="{ 'is-active': sidebarMode === 'detail' || sidebarMode === 'edit' || sidebarMode === 'widget' }"
                        @click="sidebarMode = selectionKind === 'widget' ? 'widget' : 'detail'"
                    >
                        {{ selectionKind === 'widget' ? 'Виджет' : 'Этап' }}
                    </button>
                    <button
                        v-if="canEdit"
                        type="button"
                        class="funnel-canvas-sidebar__tab"
                        :class="{ 'is-active': sidebarMode === 'create' }"
                        @click="openCreate"
                    >
                        + Этап
                    </button>
                </div>

                <template v-if="sidebarMode === 'create'">
                    <div class="funnel-canvas-sidebar__header">
                        <h2 class="funnel-canvas-sidebar__title">Новый этап</h2>
                    </div>
                    <form class="canvas-form funnel-canvas-sidebar__form" @submit.prevent="createStage">
                        <div class="canvas-form__group">
                            <label class="f5-label" for="funnel-stage-name">Название</label>
                            <input id="funnel-stage-name" v-model="newStage.name" type="text" class="f5-input" required>
                        </div>
                        <div class="canvas-form__group grid grid-cols-2 gap-2">
                            <div>
                                <label class="f5-label" for="funnel-stage-type">Тип</label>
                                <input id="funnel-stage-type" v-model="newStage.stage_type" type="text" class="f5-input">
                            </div>
                            <div>
                                <label class="f5-label" for="funnel-stage-deadline">Срок</label>
                                <input id="funnel-stage-deadline" v-model="newStage.deadline" type="text" class="f5-input">
                            </div>
                        </div>
                        <div class="canvas-form__group">
                            <label class="f5-label" for="funnel-stage-kind">Роль</label>
                            <select id="funnel-stage-kind" v-model="newStage.kind" class="f5-input">
                                <option value="normal">Обычный</option>
                                <option value="win">Успех</option>
                                <option value="lose">Отказ</option>
                            </select>
                        </div>
                        <div class="canvas-form__group">
                            <label class="f5-label" for="funnel-stage-action">Автозадача</label>
                            <textarea id="funnel-stage-action" v-model="newStage.action" class="f5-input min-h-[96px] resize-y" rows="4" />
                        </div>
                        <div class="canvas-form__group">
                            <FunnelStageWidgetsEditor
                                v-model:widgets="newStage.widgets"
                                :catalog="f5WidgetCatalog"
                            />
                        </div>
                        <div class="canvas-form__group">
                            <FunnelStageOutcomesEditor
                                v-model="newStage.outcomes"
                                :stages="localStages"
                                :board-widgets="localBoardWidgets"
                                :stage-widgets="newStage.widgets"
                                :f5-widget-catalog="f5WidgetCatalog"
                                @update:board-widgets="onBoardWidgetsUpdate"
                            />
                        </div>
                        <div class="canvas-form__group">
                            <label class="f5-label" for="funnel-stage-comment">Комментарий</label>
                            <textarea id="funnel-stage-comment" v-model="newStage.comment" class="f5-input min-h-[72px] resize-y" rows="3" />
                        </div>
                        <button type="submit" class="f5-btn f5-btn--primary w-full">Добавить этап</button>
                    </form>
                </template>

                <template v-else-if="sidebarMode === 'widget' && activeBoardWidget">
                    <div class="funnel-canvas-sidebar__header">
                        <p class="funnel-canvas-sidebar__eyebrow">Виджет на доске</p>
                        <h2 class="funnel-canvas-sidebar__title">
                            {{ activeBoardWidget.name || activeBoardWidget.widget_slug }}
                        </h2>
                    </div>
                    <form class="canvas-form funnel-canvas-sidebar__form" @submit.prevent="saveActiveBoardWidget">
                        <div class="canvas-form__group">
                            <label class="f5-label">Комментарий / сценарий</label>
                            <textarea v-model="editBoardWidget.comment" class="f5-input min-h-[80px] resize-y" rows="3" />
                        </div>
                        <div class="canvas-form__group">
                            <label class="f5-label">Далее (одна исходящая связь)</label>
                            <select
                                class="f5-input"
                                :value="editBoardWidget.next_type && editBoardWidget.next_id != null
                                    ? `${editBoardWidget.next_type}:${editBoardWidget.next_id}`
                                    : ''"
                                @change="onBoardWidgetNextChange($event.target.value)"
                            >
                                <option value="">Не задано</option>
                                <optgroup label="Этапы">
                                    <option
                                        v-for="stage in localStages"
                                        :key="`next-s-${stage.id}`"
                                        :value="`stage:${stage.id}`"
                                    >
                                        Этап {{ stage.number }}: {{ stage.name }}
                                    </option>
                                </optgroup>
                                <optgroup label="Виджеты">
                                    <option
                                        v-for="widget in localBoardWidgets.filter((w) => w.id !== activeBoardWidget.id)"
                                        :key="`next-w-${widget.id}`"
                                        :value="`widget:${widget.id}`"
                                    >
                                        {{ widget.name || widget.widget_slug }}
                                    </option>
                                </optgroup>
                            </select>
                        </div>
                        <div class="flex flex-col gap-2">
                            <button type="submit" class="f5-btn f5-btn--primary w-full">Сохранить</button>
                            <button
                                type="button"
                                class="f5-btn f5-btn--ghost w-full"
                                @click="tool = 'connect'; connectState = { kind: 'widget', id: activeBoardWidget.id }"
                            >
                                Соединить с…
                            </button>
                            <button type="button" class="f5-btn f5-btn--danger w-full" @click="deleteSelection">
                                Удалить с доски
                            </button>
                        </div>
                    </form>
                </template>

                <template v-else-if="sidebarMode === 'edit' && activeStage">
                    <div class="funnel-canvas-sidebar__header">
                        <h2 class="funnel-canvas-sidebar__title">Редактирование</h2>
                    </div>
                    <form class="canvas-form funnel-canvas-sidebar__form" @submit.prevent="saveActiveStage">
                        <div class="canvas-form__group">
                            <label class="f5-label">Название</label>
                            <input v-model="editStage.name" type="text" class="f5-input" required>
                        </div>
                        <div class="canvas-form__group grid grid-cols-2 gap-2">
                            <div>
                                <label class="f5-label">Тип</label>
                                <input v-model="editStage.stage_type" type="text" class="f5-input">
                            </div>
                            <div>
                                <label class="f5-label">Срок</label>
                                <input v-model="editStage.deadline" type="text" class="f5-input">
                            </div>
                        </div>
                        <div class="canvas-form__group">
                            <label class="f5-label">Роль</label>
                            <select v-model="editStage.kind" class="f5-input">
                                <option value="normal">Обычный</option>
                                <option value="win">Успех</option>
                                <option value="lose">Отказ</option>
                            </select>
                        </div>
                        <div class="canvas-form__group">
                            <label class="f5-label">Автозадача</label>
                            <textarea v-model="editStage.action" class="f5-input min-h-[96px] resize-y" rows="4" />
                        </div>
                        <div class="canvas-form__group">
                            <FunnelStageWidgetsEditor
                                v-model:widgets="editStage.widgets"
                                :catalog="f5WidgetCatalog"
                            />
                        </div>
                        <div class="canvas-form__group">
                            <FunnelStageOutcomesEditor
                                v-model="editStage.outcomes"
                                :stages="localStages"
                                :board-widgets="localBoardWidgets"
                                :current-stage-id="activeStage.id"
                                :stage-widgets="editStage.widgets"
                                :f5-widget-catalog="f5WidgetCatalog"
                                @update:board-widgets="onBoardWidgetsUpdate"
                            />
                        </div>
                        <div class="canvas-form__group">
                            <label class="f5-label">Комментарий</label>
                            <textarea v-model="editStage.comment" class="f5-input min-h-[72px] resize-y" rows="3" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <button type="submit" class="f5-btn f5-btn--primary w-full">Сохранить</button>
                            <button type="button" class="f5-btn f5-btn--ghost w-full" @click="sidebarMode = 'detail'">
                                Отмена
                            </button>
                            <button type="button" class="f5-btn f5-btn--danger w-full" @click="deleteActiveStage">
                                Удалить этап
                            </button>
                        </div>
                    </form>
                </template>

                <template v-else>
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

                        <div
                            v-if="outcomesLaunchingWidgets(activeStage).length"
                            class="mt-3 space-y-1 text-xs text-f5-text dark:text-white/80"
                        >
                            <p class="font-medium text-f5-green">Результаты запускают виджеты:</p>
                            <p
                                v-for="(row, index) in outcomesLaunchingWidgets(activeStage)"
                                :key="`outcome-widget-${index}`"
                            >
                                «{{ row.result }}» → {{ widgetNameBySlug(row.widget_slug) }}
                            </p>
                        </div>

                        <div class="funnel-canvas-chips">
                            <span v-if="activeStage.stage_type" class="funnel-canvas-chip">
                                Тип: {{ activeStage.stage_type }}
                            </span>
                            <span v-if="activeStage.deadline" class="funnel-canvas-chip">
                                Срок: {{ activeStage.deadline }}
                            </span>
                            <span v-if="activeStage.kind === 'win'" class="funnel-canvas-chip funnel-canvas-chip--win">
                                Успех
                            </span>
                            <span v-if="activeStage.kind === 'lose'" class="funnel-canvas-chip funnel-canvas-chip--lose">
                                Отказ
                            </span>
                        </div>

                        <div v-if="(activeStage.widgets ?? []).length" class="mt-4">
                            <h3 class="funnel-canvas-sidebar__subtitle">Виджеты этапа</h3>
                            <ul class="mt-2 space-y-2">
                                <li
                                    v-for="widget in activeStage.widgets"
                                    :key="widget.widget_slug"
                                    class="rounded-xl border border-f5-border px-3 py-2 text-sm dark:border-white/10"
                                >
                                    <a
                                        v-if="widget.url"
                                        :href="widget.url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="font-medium text-f5-text underline dark:text-white"
                                    >
                                        {{ widget.name }}
                                    </a>
                                    <span v-else class="font-medium text-f5-text dark:text-white">
                                        {{ widget.name }}
                                    </span>
                                    <p
                                        v-if="widget.comment"
                                        class="mt-1 text-xs text-f5-text-muted dark:text-white/50"
                                    >
                                        {{ widget.comment }}
                                    </p>
                                </li>
                            </ul>
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
                                <div v-if="canEdit" class="mt-2 flex flex-wrap gap-1.5">
                                    <button
                                        type="button"
                                        class="f5-btn f5-btn--ghost f5-btn--sm"
                                        @click="startConnectFromOutcome(index)"
                                    >
                                        Соединить
                                    </button>
                                    <button
                                        v-if="(outcome.path_points ?? []).length"
                                        type="button"
                                        class="f5-btn f5-btn--ghost f5-btn--sm"
                                        @click="resetOutcomePath(index)"
                                    >
                                        Сбросить путь
                                    </button>
                                    <button
                                        type="button"
                                        class="f5-btn f5-btn--danger f5-btn--sm"
                                        @click="deleteOutcomeAt(index)"
                                    >
                                        Удалить
                                    </button>
                                </div>
                            </div>
                        </div>
                        <p v-else class="funnel-canvas-hint">
                            Результатов пока нет — откройте редактирование или режим «Связь».
                        </p>
                        <p v-if="canEdit && (activeStage.outcomes ?? []).length" class="funnel-canvas-hint mt-2">
                            Стрелки обходят блоки сами. Чтобы согнуть вручную: выберите этап → потяните точку на стрелке (двойной клик по линии — добавить точку).
                        </p>

                        <div v-if="canEdit" class="mt-3 flex flex-col gap-2">
                            <button
                                type="button"
                                class="f5-btn f5-btn--ghost w-full"
                                @click="tool = 'connect'; connectState = { kind: 'stage', id: Number(activeStage.id), outcomeIndex: null }"
                            >
                                Соединить этап…
                            </button>
                            <button
                                type="button"
                                class="f5-btn f5-btn--primary w-full"
                                @click="openEdit"
                            >
                                Редактировать этап
                            </button>
                        </div>
                    </div>
                    <div v-else class="funnel-canvas-sidebar__form">
                        <p class="funnel-canvas-hint">
                            Нажмите карточку этапа на доске.
                        </p>
                    </div>
                </template>
            </aside>
        </div>
    </CanvasLayout>
</template>
