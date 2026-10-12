<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import FunnelStageColorPicker from './FunnelStageColorPicker.vue';
import FunnelStageOutcomesEditor from './FunnelStageOutcomesEditor.vue';
import FunnelStageWidgetsEditor from './FunnelStageWidgetsEditor.vue';

const props = defineProps({
    storeUrl: {
        type: String,
        required: true,
    },
    afterStageId: {
        type: [Number, String, null],
        default: null,
    },
    defaultColor: {
        type: String,
        required: true,
    },
    colorPalette: {
        type: Array,
        default: () => [],
    },
    stages: {
        type: Array,
        default: () => [],
    },
    f5WidgetCatalog: {
        type: Object,
        default: () => ({ widgets: [], categories: {} }),
    },
});

const emit = defineEmits(['cancel']);

const form = useForm({
    name: '',
    color: props.defaultColor,
    action: '',
    comment: '',
    stage_type: '',
    deadline: '',
    kind: 'normal',
    outcomes: [],
    widgets: [],
    after_stage_id: props.afterStageId ?? '',
});

watch(
    () => props.afterStageId,
    (value) => {
        form.after_stage_id = value ?? '';
    },
);

watch(
    () => props.defaultColor,
    (value) => {
        if (!form.name) {
            form.color = value;
        }
    },
);

function resetForm() {
    form.reset();
    form.color = props.defaultColor;
    form.kind = 'normal';
    form.outcomes = [];
    form.widgets = [];
    form.after_stage_id = props.afterStageId ?? '';
}

function submit() {
    form.post(props.storeUrl, {
        preserveScroll: true,
        onSuccess: () => {
            resetForm();
            emit('cancel');
        },
    });
}

function cancel() {
    resetForm();
    emit('cancel');
}
</script>

<template>
    <div class="stage-form-new">
        <form class="stage-form-new__inner" @submit.prevent="submit">
            <p class="stage-form-new__title">Новый этап</p>

            <div class="mb-3">
                <label for="new_stage_name" class="f5-label">Название этапа</label>
                <input
                    id="new_stage_name"
                    v-model="form.name"
                    type="text"
                    class="f5-input"
                    :class="{ 'f5-input--error': form.errors.name }"
                    placeholder="Например: Новая заявка"
                    required
                >
                <p v-if="form.errors.name" class="f5-error">{{ form.errors.name }}</p>
            </div>

            <div class="mb-3 grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="new_stage_type" class="f5-label">Тип</label>
                    <input
                        id="new_stage_type"
                        v-model="form.stage_type"
                        type="text"
                        class="f5-input"
                        placeholder="Квалификация"
                    >
                </div>
                <div>
                    <label for="new_stage_deadline" class="f5-label">Срок</label>
                    <input
                        id="new_stage_deadline"
                        v-model="form.deadline"
                        type="text"
                        class="f5-input"
                        placeholder="2 рабочих дня"
                    >
                </div>
            </div>

            <div class="mb-3">
                <label for="new_stage_kind" class="f5-label">Роль на доске</label>
                <select id="new_stage_kind" v-model="form.kind" class="f5-input">
                    <option value="normal">Обычный этап</option>
                    <option value="win">Успех</option>
                    <option value="lose">Отказ</option>
                </select>
            </div>

            <FunnelStageColorPicker
                v-model="form.color"
                :palette="colorPalette"
                input-id="new_stage_color"
            />

            <div class="mb-3">
                <label for="new_stage_action" class="f5-label">Автозадача</label>
                <textarea
                    id="new_stage_action"
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
                    :stage-widgets="form.widgets"
                    :f5-widget-catalog="f5WidgetCatalog"
                />
            </div>

            <div class="mb-3">
                <label for="new_stage_comment" class="f5-label">Комментарий к этапу</label>
                <textarea
                    id="new_stage_comment"
                    v-model="form.comment"
                    class="f5-input min-h-[96px] resize-y"
                    rows="3"
                    placeholder="Дополнительные заметки, инструменты, ответственные..."
                />
            </div>

            <div class="stage-form-new__actions">
                <button type="submit" class="f5-btn f5-btn--primary f5-btn--sm" :disabled="form.processing">
                    Сохранить этап
                </button>
                <button type="button" class="f5-btn f5-btn--ghost f5-btn--sm" @click="cancel">
                    Отмена
                </button>
            </div>
        </form>
    </div>
</template>
