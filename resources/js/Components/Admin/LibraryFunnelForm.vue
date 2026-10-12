<script setup>
import FunnelStageOutcomesEditor from '../Funnels/FunnelStageOutcomesEditor.vue';

defineProps({
    form: {
        type: Object,
        required: true,
    },
    colorOptions: {
        type: Array,
        default: () => [],
    },
    f5WidgetCatalog: {
        type: Object,
        default: () => ({ widgets: [] }),
    },
});

function addStage(form) {
    form.stages.push({
        name: '',
        action: '',
        comment: '',
        stage_type: '',
        deadline: '',
        kind: 'normal',
        outcomes: [],
        color: '#99ccff',
    });
}

function removeStage(form, index) {
    form.stages.splice(index, 1);
}

function stagesForOutcomes(form) {
    return form.stages.map((stage, index) => ({
        name: stage.name || `Этап ${index + 1}`,
        number: index + 1,
        sort_order: index + 1,
    }));
}
</script>

<template>
    <div class="space-y-5">
        <div>
            <label class="f5-label">Название</label>
            <input v-model="form.name" type="text" class="f5-input" required>
            <p v-if="form.errors.name" class="f5-error">{{ form.errors.name }}</p>
        </div>

        <div>
            <label class="f5-label">Описание</label>
            <textarea v-model="form.description" class="f5-input" rows="2" />
        </div>

        <div>
            <label class="f5-label">Порядок</label>
            <input v-model.number="form.sort_order" type="number" min="0" class="f5-input max-w-xs">
        </div>

        <div class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-f5-text dark:text-white">Этапы</h3>
                <button type="button" class="f5-btn f5-btn--ghost f5-btn--sm" @click="addStage(form)">
                    Добавить этап
                </button>
            </div>

            <div
                v-for="(stage, index) in form.stages"
                :key="index"
                class="space-y-3 rounded-xl border border-f5-border p-4 dark:border-white/10"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1">
                        <label class="f5-label">Этап {{ index + 1 }}</label>
                        <input v-model="stage.name" type="text" class="f5-input" required>
                    </div>
                    <button
                        type="button"
                        class="mt-6 f5-btn f5-btn--danger f5-btn--sm"
                        :disabled="form.stages.length <= 1"
                        @click="removeStage(form, index)"
                    >
                        ×
                    </button>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="f5-label">Автозадача</label>
                        <input v-model="stage.action" type="text" class="f5-input">
                    </div>
                    <div>
                        <label class="f5-label">Цвет</label>
                        <select v-model="stage.color" class="f5-input">
                            <option v-for="color in colorOptions" :key="color" :value="color">
                                {{ color }}
                            </option>
                        </select>
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="f5-label">Тип</label>
                        <input v-model="stage.stage_type" type="text" class="f5-input" placeholder="Квалификация">
                    </div>
                    <div>
                        <label class="f5-label">Срок</label>
                        <input v-model="stage.deadline" type="text" class="f5-input" placeholder="2 рабочих дня">
                    </div>
                    <div>
                        <label class="f5-label">Роль</label>
                        <select v-model="stage.kind" class="f5-input">
                            <option value="normal">Обычный</option>
                            <option value="win">Успех</option>
                            <option value="lose">Отказ</option>
                        </select>
                    </div>
                </div>
                <FunnelStageOutcomesEditor
                    v-model="stage.outcomes"
                    :stages="stagesForOutcomes(form)"
                    :f5-widget-catalog="f5WidgetCatalog"
                    use-sort-order
                />
                <div>
                    <label class="f5-label">Комментарий</label>
                    <textarea v-model="stage.comment" class="f5-input" rows="2" />
                </div>
            </div>
        </div>
    </div>
</template>
