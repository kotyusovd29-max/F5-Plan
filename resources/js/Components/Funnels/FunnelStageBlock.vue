<script setup>
import { computed, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import FunnelStageColorPicker from './FunnelStageColorPicker.vue';
import FunnelStageOutcomesEditor from './FunnelStageOutcomesEditor.vue';
import FunnelStageWidgetsEditor from './FunnelStageWidgetsEditor.vue';
import { confirmDialog } from '../../utils/confirmDialog';

const props = defineProps({
    stage: {
        type: Object,
        required: true,
    },
    stages: {
        type: Array,
        default: () => [],
    },
    open: {
        type: Boolean,
        default: false,
    },
    colorPalette: {
        type: Array,
        default: () => [],
    },
    f5WidgetCatalog: {
        type: Object,
        default: () => ({ widgets: [], categories: {} }),
    },
});

const emit = defineEmits(['toggle']);

function mapWidgets(stageWidgets) {
    return Array.isArray(stageWidgets)
        ? stageWidgets.map((row) => ({
            widget_slug: row.widget_slug,
            comment: row.comment ?? '',
        }))
        : [];
}

const form = useForm({
    name: props.stage.name,
    color: props.stage.color,
    action: props.stage.action ?? '',
    comment: props.stage.comment ?? '',
    stage_type: props.stage.stage_type ?? '',
    deadline: props.stage.deadline ?? '',
    kind: props.stage.kind || 'normal',
    outcomes: Array.isArray(props.stage.outcomes) ? props.stage.outcomes : [],
    widgets: mapWidgets(props.stage.widgets),
});

watch(
    () => props.stage,
    (stage) => {
        form.name = stage.name;
        form.color = stage.color;
        form.action = stage.action ?? '';
        form.comment = stage.comment ?? '';
        form.stage_type = stage.stage_type ?? '';
        form.deadline = stage.deadline ?? '';
        form.kind = stage.kind || 'normal';
        form.outcomes = Array.isArray(stage.outcomes) ? stage.outcomes : [];
        form.widgets = mapWidgets(stage.widgets);
    },
    { deep: true },
);

const actionText = computed(() => (props.stage.action ?? '').trim());
const barColor = computed(() => props.stage.bar_color);
const metaText = computed(() => {
    const parts = [];
    if ((props.stage.stage_type ?? '').trim()) parts.push(props.stage.stage_type.trim());
    if ((props.stage.deadline ?? '').trim()) parts.push(props.stage.deadline.trim());
    const widgetsCount = Array.isArray(props.stage.widgets) ? props.stage.widgets.length : 0;
    if (widgetsCount > 0) parts.push(`${widgetsCount} виджет.`);
    return parts.join(' · ');
});
const outcomesCount = computed(() => (Array.isArray(props.stage.outcomes) ? props.stage.outcomes.length : 0));
const outcomeWidgetsCount = computed(() => (
    (props.stage.outcomes ?? []).filter((row) => row?.widget_slug).length
));

function toggle() {
    emit('toggle');
}

function saveStage() {
    form.put(props.stage.urls.update, {
        preserveScroll: true,
    });
}

async function deleteStage() {
    const confirmed = await confirmDialog({
        title: 'Удалить этап?',
        message: `Этап «${props.stage.name}» будет удалён без возможности восстановления.`,
    });

    if (!confirmed) {
        return;
    }

    router.delete(props.stage.urls.destroy, { preserveScroll: true });
}
</script>

<template>
    <article
        class="stage-block"
        :class="{
            'stage-block--open': open,
            'stage-block--win': stage.kind === 'win',
            'stage-block--lose': stage.kind === 'lose',
        }"
        :style="{ '--stage-color': barColor }"
    >
        <div class="stage-block__stack">
            <div class="stage-block__card">
                <div
                    class="stage-block__accent"
                    :style="{ backgroundColor: barColor }"
                    aria-hidden="true"
                />

                <div class="stage-block__head">
                    <span
                        class="sortable-handle sortable-handle--stage"
                        title="Перетащить"
                        aria-label="Перетащить"
                    >⠿</span>
                    <button type="button" class="stage-block__toggle" @click="toggle">
                        <div class="stage-block__toggle-top">
                            <span class="stage-block__number">Этап {{ stage.number }}</span>
                            <span class="stage-block__icon" aria-hidden="true">▼</span>
                        </div>
                        <span class="stage-block__name">{{ stage.name }}</span>
                        <span v-if="metaText" class="stage-block__meta">{{ metaText }}</span>
                    </button>
                </div>

                <button type="button" class="stage-block__action" tabindex="0" @click="toggle">
                    <span class="stage-block__action-label">Автозадача</span>
                    <span
                        v-if="actionText"
                        class="stage-block__action-text"
                    >{{ actionText }}</span>
                    <span
                        v-else
                        class="stage-block__action-text stage-block__action-text--empty"
                    >Не указано — нажмите, чтобы добавить</span>
                    <div
                        v-if="outcomesCount > 0 || outcomeWidgetsCount > 0"
                        class="stage-block__chips"
                    >
                        <span v-if="outcomesCount > 0" class="stage-block__chip">
                            {{ outcomesCount }} {{ outcomesCount === 1 ? 'результат' : outcomesCount < 5 ? 'результата' : 'результатов' }}
                        </span>
                        <span
                            v-if="outcomeWidgetsCount > 0"
                            class="stage-block__chip"
                        >
                            {{ outcomeWidgetsCount }} виджет.
                        </span>
                    </div>
                </button>
            </div>
        </div>

        <div v-show="open" class="stage-block__edit">
            <form @submit.prevent="saveStage">
                <div class="mb-3">
                    <label :for="`stage_name_${stage.id}`" class="f5-label">Название этапа</label>
                    <input
                        :id="`stage_name_${stage.id}`"
                        v-model="form.name"
                        type="text"
                        class="f5-input"
                        :class="{ 'f5-input--error': form.errors.name }"
                        required
                    >
                    <p v-if="form.errors.name" class="f5-error">{{ form.errors.name }}</p>
                </div>

                <div class="mb-3 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label :for="`stage_type_${stage.id}`" class="f5-label">Тип</label>
                        <input
                            :id="`stage_type_${stage.id}`"
                            v-model="form.stage_type"
                            type="text"
                            class="f5-input"
                            placeholder="Например: Квалификация"
                        >
                    </div>
                    <div>
                        <label :for="`stage_deadline_${stage.id}`" class="f5-label">Срок</label>
                        <input
                            :id="`stage_deadline_${stage.id}`"
                            v-model="form.deadline"
                            type="text"
                            class="f5-input"
                            placeholder="Например: 2 рабочих дня"
                        >
                    </div>
                </div>

                <div class="mb-3">
                    <label :for="`stage_kind_${stage.id}`" class="f5-label">Роль на доске</label>
                    <select :id="`stage_kind_${stage.id}`" v-model="form.kind" class="f5-input">
                        <option value="normal">Обычный этап</option>
                        <option value="win">Успех</option>
                        <option value="lose">Отказ</option>
                    </select>
                </div>

                <FunnelStageColorPicker
                    v-model="form.color"
                    :palette="colorPalette"
                    :input-id="`stage_color_${stage.id}`"
                />

                <div class="mb-3">
                    <label :for="`stage_action_${stage.id}`" class="f5-label">Автозадача</label>
                    <textarea
                        :id="`stage_action_${stage.id}`"
                        v-model="form.action"
                        class="f5-input min-h-[96px] resize-y"
                        rows="3"
                        placeholder="Что сделать на этом этапе..."
                    />
                </div>

                <div class="mb-3">
                    <FunnelStageWidgetsEditor
                        v-model:widgets="form.widgets"
                        :catalog="f5WidgetCatalog"
                    />
                </div>

                <div class="mb-3">
                    <FunnelStageOutcomesEditor
                        v-model="form.outcomes"
                        :stages="stages"
                        :current-stage-id="stage.id"
                        :stage-widgets="form.widgets"
                        :f5-widget-catalog="f5WidgetCatalog"
                    />
                </div>

                <div class="mb-3">
                    <label :for="`stage_comment_${stage.id}`" class="f5-label">Комментарий к этапу</label>
                    <textarea
                        :id="`stage_comment_${stage.id}`"
                        v-model="form.comment"
                        class="f5-input min-h-[96px] resize-y"
                        rows="3"
                        placeholder="Дополнительные заметки, инструменты, ответственные..."
                    />
                </div>

                <div class="stage-block__actions">
                    <button type="submit" class="f5-btn f5-btn--primary f5-btn--sm" :disabled="form.processing">
                        Сохранить этап
                    </button>
                </div>
            </form>

            <form class="stage-block__delete-form" @submit.prevent="deleteStage">
                <button type="submit" class="f5-btn f5-btn--danger f5-btn--sm">
                    Удалить этап
                </button>
            </form>
        </div>
    </article>
</template>
