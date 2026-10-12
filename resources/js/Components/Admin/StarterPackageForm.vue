<script setup>
import { computed } from 'vue';

const props = defineProps({
    form: { type: Object, required: true },
    colorOptions: { type: Array, default: () => [] },
    entityOptions: { type: Array, default: () => [] },
    valueTypeOptions: { type: Array, default: () => [] },
    widgetCatalog: { type: Object, default: () => ({ widgets: [] }) },
});

const widgets = computed(() => props.widgetCatalog?.widgets ?? []);

const funnelNameOptions = computed(() => (
    (props.form.funnels ?? [])
        .map((funnel) => funnel.name?.trim())
        .filter(Boolean)
));

function stageNameOptions(funnelName) {
    const funnel = (props.form.funnels ?? []).find(
        (item) => (item.name ?? '').trim() === (funnelName ?? '').trim()
    );

    if (!funnel) {
        return (props.form.funnels ?? [])
            .flatMap((item) => (item.stages ?? []).map((stage) => stage.name?.trim()))
            .filter(Boolean);
    }

    return (funnel.stages ?? []).map((stage) => stage.name?.trim()).filter(Boolean);
}

function addFunnel() {
    props.form.funnels.push({
        name: '',
        description: '',
        stages: [
            { name: 'Новый лид', action: '', comment: '', stage_type: '', deadline: '', kind: 'normal', outcomes: [], color: '#99ccff' },
            { name: 'Квалификация', action: '', comment: '', stage_type: '', deadline: '', kind: 'normal', outcomes: [], color: '#ffff99' },
            { name: 'Успешно', action: '', comment: '', stage_type: '', deadline: '', kind: 'win', outcomes: [], color: '#99ff99' },
        ],
    });
}

function removeFunnel(index) {
    props.form.funnels.splice(index, 1);
}

function addStage(funnel) {
    funnel.stages.push({
        name: '',
        action: '',
        comment: '',
        stage_type: '',
        deadline: '',
        kind: 'normal',
        outcomes: [],
        color: props.colorOptions[0] ?? '#99ccff',
    });
}

function removeStage(funnel, index) {
    funnel.stages.splice(index, 1);
}

function addSkkField() {
    props.form.skk_fields.push({
        entity_type: 'deals',
        name: '',
        value_type: 'text',
        is_required: false,
        show_in_card: true,
        show_in_list: false,
        show_in_filter: false,
        options_text: '',
        funnel_name: '',
        stage_name: '',
        comment: '',
    });
}

function removeSkkField(index) {
    props.form.skk_fields.splice(index, 1);
}

function addIntegration() {
    props.form.integrations.push({
        widget_slug: '',
        name: '',
        comment: '',
        billing_quantity: null,
    });
}

function removeIntegration(index) {
    props.form.integrations.splice(index, 1);
}

function onWidgetChange(item) {
    const widget = widgets.value.find((entry) => entry.slug === item.widget_slug);
    if (widget) {
        item.name = widget.name;
    }
}

function addWorkStage() {
    props.form.work_stages.push({
        name: '',
        composition: '',
        cost: '',
        duration_days: '',
    });
}

function removeWorkStage(index) {
    props.form.work_stages.splice(index, 1);
}

function needsOptions(valueType) {
    return ['select', 'multiselect'].includes(valueType);
}
</script>

<template>
    <div class="space-y-8">
        <section class="space-y-4">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-f5-text-muted dark:text-white/45">
                О пакете
            </h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="f5-label">Название пакета</label>
                    <input v-model="form.name" type="text" class="f5-input" required placeholder="B2B продажи · старт">
                    <p v-if="form.errors.name" class="f5-error">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="f5-label">Сфера бизнеса</label>
                    <input v-model="form.business_sphere" type="text" class="f5-input" required placeholder="B2B-услуги">
                    <p v-if="form.errors.business_sphere" class="f5-error">{{ form.errors.business_sphere }}</p>
                </div>
                <div>
                    <label class="f5-label">Порядок</label>
                    <input v-model.number="form.sort_order" type="number" min="0" class="f5-input">
                </div>
                <div class="sm:col-span-2">
                    <label class="f5-label">Описание</label>
                    <textarea v-model="form.description" class="f5-input" rows="2" placeholder="Что входит и для каких клиентов" />
                </div>
                <label class="flex items-center gap-2 text-sm text-f5-text dark:text-white">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-f5-border">
                    Активен (виден при создании проекта)
                </label>
            </div>
        </section>

        <section class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-f5-text-muted dark:text-white/45">
                    Воронки
                </h3>
                <button type="button" class="f5-btn f5-btn--ghost f5-btn--sm" @click="addFunnel">
                    Добавить воронку
                </button>
            </div>

            <p
                v-if="form.funnels.length === 0"
                class="rounded-xl border border-dashed border-f5-border px-4 py-6 text-sm text-f5-text-muted dark:border-white/10 dark:text-white/45"
            >
                Пока без воронок — можно добавить позже.
            </p>

            <div
                v-for="(funnel, funnelIndex) in form.funnels"
                :key="funnelIndex"
                class="space-y-4 rounded-2xl border border-f5-border p-4 dark:border-white/10"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1 space-y-3">
                        <div>
                            <label class="f5-label">Воронка {{ funnelIndex + 1 }}</label>
                            <input v-model="funnel.name" type="text" class="f5-input" required>
                        </div>
                        <div>
                            <label class="f5-label">Описание</label>
                            <input v-model="funnel.description" type="text" class="f5-input">
                        </div>
                    </div>
                    <button type="button" class="mt-6 f5-btn f5-btn--danger f5-btn--sm" @click="removeFunnel(funnelIndex)">
                        ×
                    </button>
                </div>

                <div class="space-y-3 border-t border-f5-border pt-3 dark:border-white/10">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wide text-f5-text-muted dark:text-white/40">Этапы</p>
                        <button type="button" class="f5-btn f5-btn--ghost f5-btn--sm" @click="addStage(funnel)">
                            Этап
                        </button>
                    </div>
                    <div
                        v-for="(stage, stageIndex) in funnel.stages"
                        :key="stageIndex"
                        class="space-y-2 rounded-xl bg-f5-surface-2 p-3 dark:bg-white/5"
                    >
                        <div class="grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
                            <input v-model="stage.name" type="text" class="f5-input" placeholder="Название этапа" required>
                            <input v-model="stage.action" type="text" class="f5-input" placeholder="Автозадача">
                            <div class="flex items-center gap-2">
                                <select v-model="stage.color" class="f5-input">
                                    <option v-for="color in colorOptions" :key="color" :value="color">{{ color }}</option>
                                </select>
                                <button
                                    type="button"
                                    class="f5-btn f5-btn--danger f5-btn--sm"
                                    :disabled="funnel.stages.length <= 1"
                                    @click="removeStage(funnel, stageIndex)"
                                >
                                    ×
                                </button>
                            </div>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-3">
                            <input v-model="stage.stage_type" type="text" class="f5-input" placeholder="Тип">
                            <input v-model="stage.deadline" type="text" class="f5-input" placeholder="Срок">
                            <select v-model="stage.kind" class="f5-input">
                                <option value="normal">Обычный</option>
                                <option value="win">Успех</option>
                                <option value="lose">Отказ</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-f5-text-muted dark:text-white/45">
                    Поля СКК
                </h3>
                <button type="button" class="f5-btn f5-btn--ghost f5-btn--sm" @click="addSkkField">
                    Добавить поле
                </button>
            </div>

            <div
                v-for="(field, index) in form.skk_fields"
                :key="index"
                class="space-y-3 rounded-2xl border border-f5-border p-4 dark:border-white/10"
            >
                <div class="flex justify-between gap-3">
                    <p class="text-sm font-medium text-f5-text dark:text-white">Поле {{ index + 1 }}</p>
                    <button type="button" class="f5-btn f5-btn--danger f5-btn--sm" @click="removeSkkField(index)">×</button>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="f5-label">Сущность</label>
                        <select v-model="field.entity_type" class="f5-input">
                            <option v-for="option in entityOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="f5-label">Тип</label>
                        <select v-model="field.value_type" class="f5-input">
                            <option v-for="option in valueTypeOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="f5-label">Название</label>
                        <input v-model="field.name" type="text" class="f5-input" required>
                    </div>
                    <div v-if="needsOptions(field.value_type)" class="sm:col-span-2">
                        <label class="f5-label">Варианты (по строке)</label>
                        <textarea v-model="field.options_text" class="f5-input" rows="3" />
                    </div>
                    <div>
                        <label class="f5-label">Воронка (опц.)</label>
                        <input v-model="field.funnel_name" type="text" class="f5-input" list="package-funnel-names" placeholder="Имя воронки из пакета">
                    </div>
                    <div>
                        <label class="f5-label">Этап (опц.)</label>
                        <input
                            v-model="field.stage_name"
                            type="text"
                            class="f5-input"
                            :list="`package-stage-names-${index}`"
                            placeholder="Имя этапа"
                        >
                        <datalist :id="`package-stage-names-${index}`">
                            <option v-for="name in stageNameOptions(field.funnel_name)" :key="name" :value="name" />
                        </datalist>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="f5-label">Комментарий</label>
                        <textarea v-model="field.comment" class="f5-input" rows="2" />
                    </div>
                </div>
                <div class="flex flex-wrap gap-4 text-sm">
                    <label class="flex items-center gap-2"><input v-model="field.is_required" type="checkbox" class="rounded"> Обязательное</label>
                    <label class="flex items-center gap-2"><input v-model="field.show_in_card" type="checkbox" class="rounded"> В карточке</label>
                    <label class="flex items-center gap-2"><input v-model="field.show_in_list" type="checkbox" class="rounded"> В списке</label>
                    <label class="flex items-center gap-2"><input v-model="field.show_in_filter" type="checkbox" class="rounded"> В фильтре</label>
                </div>
            </div>
            <datalist id="package-funnel-names">
                <option v-for="name in funnelNameOptions" :key="name" :value="name" />
            </datalist>
        </section>

        <section class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-f5-text-muted dark:text-white/45">
                    Виджеты
                </h3>
                <button type="button" class="f5-btn f5-btn--ghost f5-btn--sm" @click="addIntegration">
                    Добавить виджет
                </button>
            </div>

            <div
                v-for="(item, index) in form.integrations"
                :key="index"
                class="grid gap-3 rounded-2xl border border-f5-border p-4 sm:grid-cols-[1fr_auto] dark:border-white/10"
            >
                <div class="space-y-3">
                    <div>
                        <label class="f5-label">Виджет из каталога</label>
                        <select v-model="item.widget_slug" class="f5-input" @change="onWidgetChange(item)">
                            <option value="">Свободное название</option>
                            <option v-for="widget in widgets" :key="widget.slug" :value="widget.slug">
                                {{ widget.name }}
                            </option>
                        </select>
                    </div>
                    <div v-if="!item.widget_slug">
                        <label class="f5-label">Название</label>
                        <input v-model="item.name" type="text" class="f5-input">
                    </div>
                    <div>
                        <label class="f5-label">Комментарий / сценарий</label>
                        <textarea v-model="item.comment" class="f5-input" rows="2" />
                    </div>
                    <div class="max-w-xs">
                        <label class="f5-label">Кол-во (биллинг)</label>
                        <input v-model.number="item.billing_quantity" type="number" min="1" class="f5-input">
                    </div>
                </div>
                <button type="button" class="f5-btn f5-btn--danger f5-btn--sm self-start" @click="removeIntegration(index)">
                    ×
                </button>
            </div>
        </section>

        <section class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-f5-text-muted dark:text-white/45">
                    Этапы работ
                </h3>
                <button type="button" class="f5-btn f5-btn--ghost f5-btn--sm" @click="addWorkStage">
                    Добавить этап
                </button>
            </div>

            <div
                v-for="(stage, index) in form.work_stages"
                :key="index"
                class="grid gap-3 rounded-2xl border border-f5-border p-4 sm:grid-cols-[1fr_auto] dark:border-white/10"
            >
                <div class="space-y-3">
                    <div>
                        <label class="f5-label">Название</label>
                        <input v-model="stage.name" type="text" class="f5-input" required>
                    </div>
                    <div>
                        <label class="f5-label">Состав работ</label>
                        <textarea v-model="stage.composition" class="f5-input" rows="2" />
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="f5-label">Срок, раб. дн.</label>
                            <input v-model="stage.duration_days" type="number" min="1" step="1" class="f5-input">
                        </div>
                        <div>
                            <label class="f5-label">Стоимость</label>
                            <input v-model="stage.cost" type="number" min="0" step="0.01" class="f5-input">
                        </div>
                    </div>
                </div>
                <button type="button" class="f5-btn f5-btn--danger f5-btn--sm self-start" @click="removeWorkStage(index)">
                    ×
                </button>
            </div>
        </section>
    </div>
</template>
