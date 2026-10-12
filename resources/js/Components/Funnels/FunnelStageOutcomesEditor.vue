<script setup>
import { computed } from 'vue';
import { resolveStageKind } from '../../utils/funnelStageKind';

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
    stages: {
        type: Array,
        default: () => [],
    },
    boardWidgets: {
        type: Array,
        default: () => [],
    },
    currentStageId: {
        type: [Number, String, null],
        default: null,
    },
    useSortOrder: {
        type: Boolean,
        default: false,
    },
    stageWidgets: {
        type: Array,
        default: () => [],
    },
    f5WidgetCatalog: {
        type: Object,
        default: () => ({ widgets: [] }),
    },
});

const emit = defineEmits(['update:modelValue', 'update:boardWidgets']);

const stageOptions = computed(() => props.stages.map((stage, index) => ({
    id: stage.id ?? null,
    sort_order: stage.sort_order ?? index + 1,
    number: stage.number ?? index + 1,
    name: stage.name,
    kind: stage.kind || 'normal',
})));

const catalogWidgets = computed(() => props.f5WidgetCatalog?.widgets ?? []);

const stageWidgetSlugs = computed(() => new Set(
    (props.stageWidgets ?? []).map((row) => row.widget_slug).filter(Boolean),
));

const widgetOptions = computed(() => {
    const catalog = catalogWidgets.value;
    const attached = props.stageWidgets ?? [];
    const options = [];
    const seen = new Set();

    attached.forEach((row) => {
        if (!row?.widget_slug || seen.has(row.widget_slug)) return;
        seen.add(row.widget_slug);
        options.push({
            slug: row.widget_slug,
            name: row.name || catalog.find((w) => w.slug === row.widget_slug)?.name || row.widget_slug,
            fromStage: true,
        });
    });

    catalog.forEach((widget) => {
        if (!widget?.slug || seen.has(widget.slug)) return;
        seen.add(widget.slug);
        options.push({ slug: widget.slug, name: widget.name, fromStage: false });
    });

    return options;
});

function rows() {
    return Array.isArray(props.modelValue) ? props.modelValue : [];
}

function update(next) {
    emit('update:modelValue', next);
}

function addRow() {
    update([
        ...rows(),
        {
            result: '',
            mode: 'move',
            target_type: null,
            target_stage_id: null,
            target_sort_order: null,
            target_widget_id: null,
            label: '',
            widget_slug: null,
            widget_comment: '',
            path_points: [],
        },
    ]);
}

function removeRow(index) {
    update(rows().filter((_, i) => i !== index));
}

function patchRow(index, patch) {
    update(rows().map((row, i) => (i === index ? { ...row, ...patch } : row)));
}

function modeFromStage(stage) {
    const kind = resolveStageKind(stage);
    if (kind === 'win') return 'win';
    if (kind === 'lose') return 'lose';
    return 'move';
}

function destinationValue(row) {
    if (row.target_type === 'widget' && row.target_widget_id) {
        return `w:${row.target_widget_id}`;
    }

    if (row.mode === 'stay' || (!row.target_stage_id && !row.target_sort_order && row.target_type !== 'stage')) {
        return 'stay';
    }

    if (props.useSortOrder) {
        return row.target_sort_order != null ? `s:${row.target_sort_order}` : 'stay';
    }

    return row.target_stage_id != null ? `s:${row.target_stage_id}` : 'stay';
}

function onDestinationChange(index, raw) {
    if (raw === 'stay' || raw === '') {
        patchRow(index, {
            mode: 'stay',
            target_type: null,
            target_stage_id: props.currentStageId != null ? Number(props.currentStageId) : null,
            target_sort_order: null,
            target_widget_id: null,
        });
        return;
    }

    if (raw.startsWith('w:')) {
        patchRow(index, {
            mode: 'move',
            target_type: 'widget',
            target_widget_id: raw.slice(2),
            target_stage_id: null,
            target_sort_order: null,
            widget_slug: null,
            widget_comment: '',
        });
        return;
    }

    const value = Number(raw.replace(/^s:/, ''));
    const stage = stageOptions.value.find((item) => (
        props.useSortOrder
            ? Number(item.sort_order) === value
            : Number(item.id) === value
    ));

    patchRow(index, {
        mode: modeFromStage(stage),
        target_type: 'stage',
        target_stage_id: props.useSortOrder ? null : value,
        target_sort_order: props.useSortOrder ? value : null,
        target_widget_id: null,
    });
}

function modeClass(mode) {
    if (mode === 'stay') return 'outcome-box--stay';
    if (mode === 'lose') return 'outcome-box--lose';
    if (mode === 'win' || mode === 'move') return 'outcome-box--move';
    return '';
}

function boardWidgetForTarget(row) {
    if (row?.target_type !== 'widget' || !row.target_widget_id) {
        return null;
    }

    return (props.boardWidgets ?? []).find((widget) => String(widget.id) === String(row.target_widget_id)) ?? null;
}

function boardWidgetCommentValue(row) {
    return boardWidgetForTarget(row)?.comment ?? '';
}

function onBoardWidgetCommentInput(row, comment) {
    const widget = boardWidgetForTarget(row);
    if (!widget) return;

    emit('update:boardWidgets', (props.boardWidgets ?? []).map((item) => (
        String(item.id) === String(widget.id) ? { ...item, comment } : item
    )));
}

function onLaunchWidgetChange(index, slug) {
    patchRow(index, {
        widget_slug: slug || null,
        widget_comment: slug ? (rows()[index]?.widget_comment ?? '') : '',
    });
}
</script>

<template>
    <div class="outcomes-editor">
        <div class="mb-2 flex items-center justify-between gap-2">
            <p class="f5-label !mb-0">Результаты</p>
            <button type="button" class="f5-btn f5-btn--ghost f5-btn--sm" @click="addRow">
                + Результат
            </button>
        </div>

        <p v-if="rows().length === 0" class="text-xs text-f5-text-muted dark:text-white/50">
            Добавьте результат задачи и куда после него попадёт сделка (этап или виджет на доске).
        </p>

        <div v-else class="space-y-3">
            <div
                v-for="(row, index) in rows()"
                :key="index"
                class="outcome-box rounded-xl border border-f5-border p-3 dark:border-white/10"
                :class="modeClass(row.mode)"
            >
                <div class="mb-2">
                    <label class="f5-label">Результат {{ index + 1 }}</label>
                    <input
                        type="text"
                        class="f5-input"
                        :value="row.result"
                        placeholder="Например: Ответили, интерес есть"
                        @input="patchRow(index, { result: $event.target.value })"
                    >
                </div>

                <div class="mb-2">
                    <label class="f5-label">Куда попадёт сделка</label>
                    <select
                        class="f5-input"
                        :value="destinationValue(row)"
                        @change="onDestinationChange(index, $event.target.value)"
                    >
                        <option value="stay">Остаётся</option>
                        <optgroup label="Этапы">
                            <option
                                v-for="stage in stageOptions"
                                :key="`${stage.id ?? 'i'}-${stage.sort_order}`"
                                :value="`s:${useSortOrder ? stage.sort_order : stage.id}`"
                                :disabled="!useSortOrder && currentStageId && Number(stage.id) === Number(currentStageId)"
                            >
                                Этап {{ stage.number }}: {{ stage.name }}
                            </option>
                        </optgroup>
                        <optgroup v-if="(boardWidgets ?? []).length" label="Виджеты на доске">
                            <option
                                v-for="widget in boardWidgets"
                                :key="widget.id"
                                :value="`w:${widget.id}`"
                            >
                                {{ widget.name || widget.widget_slug }}
                            </option>
                        </optgroup>
                    </select>
                </div>

                <div class="mb-2">
                    <label class="f5-label">Комментарий к стрелке</label>
                    <input
                        type="text"
                        class="f5-input"
                        :value="row.label ?? ''"
                        placeholder="Что будет после выполнения этого результата"
                        @input="patchRow(index, { label: $event.target.value })"
                    >
                </div>

                <div v-if="row.target_type !== 'widget'" class="mb-2">
                    <label class="f5-label">Запускает виджет</label>
                    <select
                        class="f5-input"
                        :value="row.widget_slug || ''"
                        :disabled="!widgetOptions.length"
                        @change="onLaunchWidgetChange(index, $event.target.value)"
                    >
                        <option value="">Не запускает виджет</option>
                        <template v-if="stageWidgetSlugs.size">
                            <optgroup label="Виджеты этапа">
                                <option
                                    v-for="widget in widgetOptions.filter((item) => item.fromStage)"
                                    :key="`outcome-stage-widget-${index}-${widget.slug}`"
                                    :value="widget.slug"
                                >
                                    {{ widget.name }}
                                </option>
                            </optgroup>
                            <optgroup
                                v-if="widgetOptions.some((item) => !item.fromStage)"
                                label="Все виджеты F5"
                            >
                                <option
                                    v-for="widget in widgetOptions.filter((item) => !item.fromStage)"
                                    :key="`outcome-catalog-widget-${index}-${widget.slug}`"
                                    :value="widget.slug"
                                >
                                    {{ widget.name }}
                                </option>
                            </optgroup>
                        </template>
                        <template v-else>
                            <option
                                v-for="widget in widgetOptions"
                                :key="`outcome-catalog-widget-${index}-${widget.slug}`"
                                :value="widget.slug"
                            >
                                {{ widget.name }}
                            </option>
                        </template>
                    </select>
                </div>
                <p
                    v-else
                    class="mb-2 text-xs text-f5-text-muted dark:text-white/45"
                >
                    Цель — виджет на доске; стрелка ведёт к этому узлу.
                </p>

                <div v-if="row.widget_slug" class="mb-2">
                    <label class="f5-label">Комментарий к виджету</label>
                    <textarea
                        class="f5-input min-h-[110px] resize-y text-sm"
                        rows="4"
                        :value="row.widget_comment ?? ''"
                        placeholder="Сценарий использования — отображается на доске под названием виджета"
                        @input="patchRow(index, { widget_comment: $event.target.value })"
                    />
                </div>

                <div v-else-if="row.target_type === 'widget' && row.target_widget_id" class="mb-2">
                    <label class="f5-label">Комментарий к виджету</label>
                    <textarea
                        class="f5-input min-h-[110px] resize-y text-sm"
                        rows="4"
                        :value="boardWidgetCommentValue(row)"
                        placeholder="Сценарий использования — отображается на доске под виджетом"
                        @input="onBoardWidgetCommentInput(row, $event.target.value)"
                    />
                </div>

                <button type="button" class="f5-btn f5-btn--danger f5-btn--sm" @click="removeRow(index)">
                    Удалить результат
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.outcome-box--move {
    background: rgba(31, 157, 99, 0.06);
    border-color: rgba(31, 157, 99, 0.35);
}

.outcome-box--stay {
    background: rgba(217, 130, 43, 0.08);
    border-color: rgba(217, 130, 43, 0.4);
}

.outcome-box--lose {
    background: rgba(196, 71, 59, 0.07);
    border-color: rgba(196, 71, 59, 0.35);
}
</style>
