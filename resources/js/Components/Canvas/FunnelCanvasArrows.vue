<script setup>
import { computed } from 'vue';
import { resolveOutcomeStyle, resolveStageKind } from '../../utils/funnelStageKind';
import {
    STAGE_SIZE,
    WIDGET_SIZE,
    edgeAnchor,
    nodeRect,
    normalizeWaypoints,
    routeArrow,
} from '../../utils/funnelArrowRouting';

const props = defineProps({
    stages: {
        type: Array,
        default: () => [],
    },
    boardWidgets: {
        type: Array,
        default: () => [],
    },
    activeStageId: {
        type: [Number, String, null],
        default: null,
    },
    activeWidgetId: {
        type: String,
        default: null,
    },
    f5WidgetCatalog: {
        type: Object,
        default: () => ({ widgets: [] }),
    },
    preview: {
        type: Object,
        default: null,
    },
    editable: {
        type: Boolean,
        default: false,
    },
    /** When false, arrows into lose/«отказ» stages show only from the selected stage. */
    showLoseArrows: {
        type: Boolean,
        default: false,
    },
});

const CHIP_WIDTH = 144;
const CHIP_WIDTH_WITH_COMMENT = 220;
const CHIP_COMMENT_MAX_LINES = 4;
const CHIP_CHARS_PER_LINE = 34;

function estimateCommentLines(comment) {
    const text = (comment ?? '').trim();
    if (!text) return 0;

    const paragraphs = text.split(/\n+/);
    let lines = 0;

    paragraphs.forEach((paragraph) => {
        const length = paragraph.trim().length || 1;
        lines += Math.max(1, Math.ceil(length / CHIP_CHARS_PER_LINE));
    });

    return Math.min(CHIP_COMMENT_MAX_LINES, lines);
}

function buildWidgetChip({ name, result, comment, x, yBase, offsetIndex }) {
    const trimmedComment = (comment ?? '').trim();
    const commentLines = estimateCommentLines(trimmedComment);
    const width = trimmedComment ? CHIP_WIDTH_WITH_COMMENT : CHIP_WIDTH;
    const height = trimmedComment
        ? 18 + 6 + (commentLines * 13) + 12
        : 24;
    const stackGap = height + 8;

    return {
        name,
        result: (result ?? '').trim(),
        comment: trimmedComment,
        commentLines,
        width,
        height,
        x,
        y: yBase + (offsetIndex * stackGap) - (height / 2),
    };
}

const emit = defineEmits([
    'update-outcome-waypoints',
    'update-widget-waypoints',
    'waypoint-drag-start',
    'waypoint-drag',
    'waypoint-drag-end',
]);

const COLORS = {
    move: '#2F6FED',
    win: '#1F9D63',
    lose: '#C4473B',
    stay: '#D9822B',
    widget: '#6B5CE7',
};

const MARKERS = {
    move: 'funnel-arrow-blue',
    win: 'funnel-arrow-green',
    lose: 'funnel-arrow-red',
    stay: 'funnel-arrow-orange',
    widget: 'funnel-arrow-violet',
};

function stageNode(stage) {
    return {
        kind: 'stage',
        key: `s:${stage.id}`,
        ref: stage,
        rect: nodeRect(stage, STAGE_SIZE),
    };
}

function widgetNode(widget) {
    return {
        kind: 'widget',
        key: `w:${widget.id}`,
        ref: widget,
        rect: nodeRect(widget, WIDGET_SIZE),
    };
}

function pointNode(x, y) {
    const px = Number(x) || 0;
    const py = Number(y) || 0;

    return {
        kind: 'point',
        key: 'cursor',
        ref: { x: px, y: py },
        rect: {
            x: px - 1,
            y: py - 1,
            w: 2,
            h: 2,
            left: px - 1,
            top: py - 1,
            right: px + 1,
            bottom: py + 1,
            cx: px,
            cy: py,
        },
    };
}

function previewNode(target) {
    if (!target) {
        return null;
    }

    if (target.kind === 'point') {
        return pointNode(target.x, target.y);
    }

    if (target.kind === 'widget') {
        return widgetNode(target.ref);
    }

    return stageNode(target.ref);
}

function pairKey(a, b) {
    return a < b ? `${a}:${b}` : `${b}:${a}`;
}

function parallelLane(fromKey, toKey, mutualPairs) {
    if (!mutualPairs.has(pairKey(fromKey, toKey))) {
        return 0;
    }

    return fromKey < toKey ? 1 : -1;
}

function pathStay(stage) {
    const c = stageNode(stage).rect;
    const x = c.right - 18;
    const y = c.cy - 8;
    return `M ${x - 16} ${y} A 14 14 0 1 1 ${x - 15} ${y + 1}`;
}

function widgetName(slug, stage) {
    if (!slug) return '';
    const fromStage = (stage?.widgets ?? []).find((row) => row.widget_slug === slug);
    if (fromStage?.name) return fromStage.name;
    return props.f5WidgetCatalog?.widgets?.find((widget) => widget.slug === slug)?.name || slug;
}

function obstacleList(stages, widgets) {
    return [
        ...stages.map((stage) => ({ key: `s:${stage.id}`, rect: nodeRect(stage, STAGE_SIZE) })),
        ...widgets.map((widget) => ({ key: `w:${widget.id}`, rect: nodeRect(widget, WIDGET_SIZE) })),
    ];
}

function routeLine(from, to, style, lane, waypoints, obstacles) {
    return routeArrow({
        fromRect: from.rect,
        toRect: to.rect,
        fromKey: from.key,
        toKey: to.key,
        obstacles,
        waypoints,
        lane,
    });
}

const paths = computed(() => {
    const stages = [...props.stages];
    const widgets = [...(props.boardWidgets ?? [])];
    const stageById = new Map(stages.map((s) => [Number(s.id), s]));
    const widgetById = new Map(widgets.map((w) => [String(w.id), w]));
    const obstacles = obstacleList(stages, widgets);
    const activeStage = props.activeStageId != null ? Number(props.activeStageId) : null;
    const activeWidget = props.activeWidgetId ? String(props.activeWidgetId) : null;
    const focusMode = activeStage != null || activeWidget != null;
    const result = [];
    const seen = new Set();
    const labelOffsets = new Map();

    const directed = new Set();

    function arrowLook({ related = false, style = 'move' } = {}) {
        if (!focusMode) {
            return {
                opacity: style === 'stay' ? 0.85 : 0.92,
                width: style === 'move' ? 2.4 : (style === 'stay' ? 2.2 : 2.6),
            };
        }

        if (related) {
            return {
                opacity: 1,
                width: style === 'move' ? 3.2 : (style === 'stay' ? 3.2 : 3.8),
            };
        }

        return {
            opacity: style === 'stay' ? 0.28 : (style === 'move' ? 0.4 : 0.45),
            width: 2,
        };
    }

    stages.forEach((stage) => {
        (stage.outcomes ?? []).forEach((outcome) => {
            if (outcome.mode === 'stay') return;
            if (outcome.target_type === 'widget' && outcome.target_widget_id) {
                if (widgetById.has(String(outcome.target_widget_id))) {
                    directed.add(`s:${stage.id}->w:${outcome.target_widget_id}`);
                }
                return;
            }
            const targetId = outcome.target_stage_id != null ? Number(outcome.target_stage_id) : null;
            if (targetId != null && stageById.has(targetId)) {
                directed.add(`s:${stage.id}->s:${targetId}`);
            }
        });
    });

    widgets.forEach((widget) => {
        if (widget.next_type === 'stage' && widget.next_id != null && stageById.has(Number(widget.next_id))) {
            directed.add(`w:${widget.id}->s:${widget.next_id}`);
        }
        if (widget.next_type === 'widget' && widget.next_id && widgetById.has(String(widget.next_id))) {
            directed.add(`w:${widget.id}->w:${widget.next_id}`);
        }
    });

    const mutualPairs = new Set();
    directed.forEach((edge) => {
        const [fromKey, toKey] = edge.split('->');
        if (directed.has(`${toKey}->${fromKey}`)) {
            mutualPairs.add(pairKey(fromKey, toKey));
        }
    });

    function pushPath(item) {
        if (seen.has(item.key)) return;
        seen.add(item.key);
        result.push(item);
    }

    stages.forEach((stage) => {
        const stageId = Number(stage.id);
        (stage.outcomes ?? []).forEach((outcome, index) => {
            const targetsWidget = outcome.target_type === 'widget' && outcome.target_widget_id;
            const isStay = !targetsWidget && (
                outcome.mode === 'stay'
                || !outcome.target_stage_id
                || Number(outcome.target_stage_id) === stageId
            );

            if (isStay) {
                const look = arrowLook({ related: activeStage === stageId, style: 'stay' });
                pushPath({
                    key: `stay-${stage.id}-${index}`,
                    d: pathStay(stage),
                    color: COLORS.stay,
                    width: look.width,
                    dash: '5 4',
                    opacity: look.opacity,
                    marker: MARKERS.stay,
                    widget: null,
                    editable: false,
                    waypoints: [],
                    source: null,
                });
                return;
            }

            if (targetsWidget) {
                const targetWidget = widgetById.get(String(outcome.target_widget_id));
                if (!targetWidget) return;

                const related = activeStage === stageId;
                const look = arrowLook({ related, style: 'widget' });
                const from = stageNode(stage);
                const to = widgetNode(targetWidget);
                const lane = parallelLane(from.key, to.key, mutualPairs);
                const line = routeLine(from, to, 'widget', lane, outcome.path_points, obstacles);
                const waypoints = normalizeWaypoints(outcome.path_points);

                pushPath({
                    key: `out-${stage.id}-w-${targetWidget.id}-${index}`,
                    d: line.d,
                    color: COLORS.widget,
                    width: look.width,
                    dash: null,
                    opacity: look.opacity,
                    marker: MARKERS.widget,
                    widget: null,
                    isActive: related,
                    editable: props.editable && activeStage === stageId,
                    waypoints,
                    points: line.points,
                    source: { type: 'outcome', stageId, outcomeIndex: index },
                });
                return;
            }

            const targetId = outcome.target_stage_id != null ? Number(outcome.target_stage_id) : null;
            const target = targetId != null ? stageById.get(targetId) : null;
            if (!target) return;

            const style = resolveOutcomeStyle(outcome, stage, target);
            if (style === 'stay') return;

            const isLoseTarget = style === 'lose' || resolveStageKind(target) === 'lose';
            // Hide clutter into «отказ» unless toggle is on or this stage is selected.
            if (isLoseTarget && !props.showLoseArrows && activeStage !== stageId) {
                return;
            }

            // Highlight only outgoing arrows from the selected stage (not incoming).
            const related = activeStage === stageId;
            const look = arrowLook({ related, style });
            const from = stageNode(stage);
            const to = stageNode(target);
            const lane = parallelLane(from.key, to.key, mutualPairs);
            const line = routeLine(from, to, style, lane, outcome.path_points, obstacles);
            const waypoints = normalizeWaypoints(outcome.path_points);

            let chip = null;
            if (outcome.widget_slug) {
                const offsetKey = `${from.key}->${to.key}`;
                const offsetIndex = labelOffsets.get(offsetKey) ?? 0;
                chip = buildWidgetChip({
                    name: widgetName(outcome.widget_slug, stage),
                    result: outcome.result,
                    comment: outcome.widget_comment,
                    x: line.mid.x,
                    yBase: line.mid.y,
                    offsetIndex,
                });
                labelOffsets.set(offsetKey, offsetIndex + 1);
            }

            pushPath({
                key: `out-${stage.id}-${target.id}-${index}`,
                d: line.d,
                color: COLORS[style],
                width: look.width,
                dash: style === 'lose' ? '7 5' : null,
                opacity: look.opacity,
                marker: MARKERS[style],
                widget: chip,
                isActive: related,
                editable: props.editable && activeStage === stageId,
                waypoints,
                points: line.points,
                source: { type: 'outcome', stageId, outcomeIndex: index },
            });
        });
    });

    widgets.forEach((widget) => {
        const widgetId = String(widget.id);
        let targetNode = null;
        let related = activeWidget === widgetId;
        let targetStage = null;

        if (widget.next_type === 'stage' && widget.next_id != null) {
            const stage = stageById.get(Number(widget.next_id));
            if (stage) {
                targetStage = stage;
                targetNode = stageNode(stage);
            }
        } else if (widget.next_type === 'widget' && widget.next_id) {
            const next = widgetById.get(String(widget.next_id));
            if (next) {
                targetNode = widgetNode(next);
            }
        }

        if (!targetNode) return;

        if (
            targetStage
            && resolveStageKind(targetStage) === 'lose'
            && !props.showLoseArrows
            && activeWidget !== widgetId
        ) {
            return;
        }

        const look = arrowLook({ related, style: 'widget' });
        const from = widgetNode(widget);
        const lane = parallelLane(from.key, targetNode.key, mutualPairs);
        const line = routeLine(from, targetNode, 'widget', lane, widget.path_points, obstacles);
        const waypoints = normalizeWaypoints(widget.path_points);

        pushPath({
            key: `bw-${widget.id}-${targetNode.key}`,
            d: line.d,
            color: COLORS.widget,
            width: look.width,
            dash: null,
            opacity: look.opacity,
            marker: MARKERS.widget,
            widget: buildWidgetChip({
                name: widget.name || widget.widget_slug,
                result: '',
                comment: '',
                x: line.mid.x,
                yBase: line.mid.y,
                offsetIndex: 0,
            }),
            isActive: related,
            editable: props.editable && activeWidget === widgetId,
            waypoints,
            points: line.points,
            source: { type: 'widget', widgetId },
        });
    });

    if (props.preview?.from && props.preview?.to) {
        const from = previewNode(props.preview.from);
        const to = previewNode(props.preview.to);

        if (from && to) {
            let d = '';

            if (to.kind === 'point') {
                const end = { x: to.rect.cx, y: to.rect.cy };
                const start = edgeAnchor(from.rect, end);
                d = `M ${start.x} ${start.y} L ${end.x} ${end.y}`;
            } else {
                d = routeLine(from, to, 'move', 0, null, obstacles).d;
            }

            pushPath({
                key: 'preview-connect',
                d,
                color: '#2F6FED',
                width: 2.6,
                dash: '6 4',
                opacity: 0.9,
                marker: MARKERS.move,
                widget: null,
                isActive: true,
                editable: false,
                waypoints: [],
                source: null,
            });
        }
    }

    return result;
});

function emitWaypoints(source, waypoints) {
    const normalized = normalizeWaypoints(waypoints);

    if (source?.type === 'outcome') {
        emit('update-outcome-waypoints', {
            stageId: source.stageId,
            outcomeIndex: source.outcomeIndex,
            pathPoints: normalized,
        });
        return;
    }

    if (source?.type === 'widget') {
        emit('update-widget-waypoints', {
            widgetId: source.widgetId,
            pathPoints: normalized,
        });
    }
}

function onPathDblClick(line, event) {
    if (!line.editable || !line.source) return;
    event.preventDefault();
    event.stopPropagation();

    const svg = event.currentTarget.ownerSVGElement;
    if (!svg) return;

    const point = svg.createSVGPoint();
    point.x = event.clientX;
    point.y = event.clientY;
    const cursor = point.matrixTransform(svg.getScreenCTM().inverse());

    const next = [...(line.waypoints ?? []), { x: Math.round(cursor.x), y: Math.round(cursor.y) }];
    emitWaypoints(line.source, next);
}

function onHandlePointerDown(line, index, event) {
    if (!line.editable || !line.source) return;
    event.preventDefault();
    event.stopPropagation();

    const svg = event.currentTarget.ownerSVGElement;
    if (!svg) return;

    const startPoints = [...(line.waypoints ?? [])];
    emit('waypoint-drag-start');

    function toLocal(clientX, clientY) {
        const point = svg.createSVGPoint();
        point.x = clientX;
        point.y = clientY;
        const local = point.matrixTransform(svg.getScreenCTM().inverse());
        return {
            x: Math.max(0, Math.round(local.x)),
            y: Math.max(0, Math.round(local.y)),
        };
    }

    function onMove(moveEvent) {
        const next = startPoints.map((point, pointIndex) => (
            pointIndex === index ? toLocal(moveEvent.clientX, moveEvent.clientY) : point
        ));
        emit('waypoint-drag');
        emitWaypoints(line.source, next);
    }

    function onUp() {
        window.removeEventListener('pointermove', onMove);
        window.removeEventListener('pointerup', onUp);
        emit('waypoint-drag-end');
    }

    window.addEventListener('pointermove', onMove);
    window.addEventListener('pointerup', onUp);
}

function onHandleDblClick(line, index, event) {
    if (!line.editable || !line.source) return;
    event.preventDefault();
    event.stopPropagation();

    const next = (line.waypoints ?? []).filter((_, pointIndex) => pointIndex !== index);
    emitWaypoints(line.source, next);
}

function resetWaypoints(line, event) {
    if (!line.editable || !line.source) return;
    event.preventDefault();
    event.stopPropagation();
    emitWaypoints(line.source, []);
}

function onGhostPointerDown(line, event) {
    if (!line.editable || !line.source || !line.points?.length) return;

    const mid = line.points[Math.floor(line.points.length / 2)];
    const seeded = { ...line, waypoints: [{ x: mid.x, y: mid.y }] };
    emitWaypoints(line.source, seeded.waypoints);
    onHandlePointerDown(seeded, 0, event);
}
</script>

<template>
    <svg
        class="funnel-canvas__arrows"
        :class="{ 'is-editable': editable }"
        aria-hidden="true"
    >
        <defs>
            <marker id="funnel-arrow-blue" markerWidth="10" markerHeight="10" refX="8" refY="3" orient="auto">
                <path d="M0,0 L8,3 L0,6 Z" fill="#2F6FED" />
            </marker>
            <marker id="funnel-arrow-green" markerWidth="10" markerHeight="10" refX="8" refY="3" orient="auto">
                <path d="M0,0 L8,3 L0,6 Z" fill="#1F9D63" />
            </marker>
            <marker id="funnel-arrow-red" markerWidth="10" markerHeight="10" refX="8" refY="3" orient="auto">
                <path d="M0,0 L8,3 L0,6 Z" fill="#C4473B" />
            </marker>
            <marker id="funnel-arrow-orange" markerWidth="10" markerHeight="10" refX="8" refY="3" orient="auto">
                <path d="M0,0 L8,3 L0,6 Z" fill="#D9822B" />
            </marker>
            <marker id="funnel-arrow-violet" markerWidth="10" markerHeight="10" refX="8" refY="3" orient="auto">
                <path d="M0,0 L8,3 L0,6 Z" fill="#6B5CE7" />
            </marker>
            <filter id="funnel-widget-chip-shadow" x="-20%" y="-40%" width="140%" height="180%">
                <feDropShadow dx="0" dy="1" stdDeviation="1.5" flood-opacity="0.18" />
            </filter>
        </defs>

        <g
            v-for="line in paths"
            :key="line.key"
            class="funnel-canvas__arrow-group"
        >
            <path
                v-if="line.editable"
                class="funnel-canvas__arrow-hit"
                :d="line.d"
                fill="none"
                stroke="transparent"
                stroke-width="14"
                stroke-linecap="round"
                @dblclick="onPathDblClick(line, $event)"
                @contextmenu="resetWaypoints(line, $event)"
            >
                <title>Двойной клик — добавить точку. ПКМ — сбросить маршрут.</title>
            </path>
            <path
                :d="line.d"
                fill="none"
                :stroke="line.color"
                :stroke-width="line.width"
                :stroke-dasharray="line.dash || undefined"
                :opacity="line.opacity"
                stroke-linecap="round"
                stroke-linejoin="round"
                :marker-end="`url(#${line.marker})`"
                pointer-events="none"
            />
            <g v-if="line.editable">
                <circle
                    v-for="(point, index) in line.waypoints"
                    :key="`${line.key}-wp-${index}`"
                    class="funnel-canvas__waypoint"
                    :cx="point.x"
                    :cy="point.y"
                    r="6"
                    :fill="line.color"
                    stroke="#fff"
                    stroke-width="2"
                    @pointerdown="onHandlePointerDown(line, index, $event)"
                    @dblclick="onHandleDblClick(line, index, $event)"
                >
                    <title>Тяните точку. Двойной клик — удалить.</title>
                </circle>
                <circle
                    v-if="!line.waypoints.length && line.points?.length >= 2"
                    class="funnel-canvas__waypoint funnel-canvas__waypoint--ghost"
                    :cx="line.points[Math.floor(line.points.length / 2)].x"
                    :cy="line.points[Math.floor(line.points.length / 2)].y"
                    r="5"
                    fill="#fff"
                    :stroke="line.color"
                    stroke-width="2"
                    stroke-dasharray="3 2"
                    @pointerdown="onGhostPointerDown(line, $event)"
                >
                    <title>Потяните, чтобы задать изгиб вручную</title>
                </circle>
            </g>
        </g>

        <g
            v-for="line in paths.filter((item) => item.widget)"
            :key="`${line.key}-widget`"
            class="funnel-canvas__widget-chip"
            :class="{ 'has-comment': Boolean(line.widget.comment) }"
            :opacity="line.isActive ? 1 : 0.88"
            :transform="`translate(${line.widget.x}, ${line.widget.y})`"
            filter="url(#funnel-widget-chip-shadow)"
        >
            <foreignObject
                :x="-(line.widget.width ?? CHIP_WIDTH) / 2"
                y="0"
                :width="line.widget.width ?? CHIP_WIDTH"
                :height="line.widget.height ?? 24"
            >
                <div
                    xmlns="http://www.w3.org/1999/xhtml"
                    class="funnel-canvas__widget-chip-box"
                    :class="{ 'has-comment': Boolean(line.widget.comment) }"
                    :style="{ borderColor: line.color }"
                >
                    <div class="funnel-canvas__widget-chip-name">
                        {{ line.widget.name }}
                    </div>
                    <div
                        v-if="line.widget.comment"
                        class="funnel-canvas__widget-chip-comment"
                    >
                        {{ line.widget.comment }}
                    </div>
                </div>
            </foreignObject>
            <title>
                {{ [
                    line.widget.result ? `${line.widget.result} → ${line.widget.name}` : line.widget.name,
                    line.widget.comment || null,
                ].filter(Boolean).join('\n') }}
            </title>
        </g>
    </svg>
</template>
