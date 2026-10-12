<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import SkkFieldForm from '../Projects/SkkFieldForm.vue';
import IntegrationWidgetPicker from '../Projects/IntegrationWidgetPicker.vue';
import { submitCanvasForm } from '../../utils/canvasForm';
import { emptySkkField, skkFieldFromApi, skkFieldToPayload } from '../../utils/skkField';
import {
    emptyIntegration,
    integrationFromApi,
    integrationToPayload,
} from '../../utils/integrationWidget';

const props = defineProps({
    block: {
        type: Object,
        required: true,
    },
    project: {
        type: Object,
        required: true,
    },
    funnelStages: {
        type: Array,
        default: () => [],
    },
    widgetCatalog: {
        type: Object,
        default: () => ({ categories: {}, widgets: [] }),
    },
    isDragging: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['drag-start', 'wheel']);

const formErrors = reactive({});
const infoForm = reactive({
    company_name: props.project.company_name,
    business_sphere: props.project.business_sphere ?? '',
    employees_count: props.project.employees_count ?? '',
    client_tasks: props.project.client_tasks ?? '',
});
const skkEdits = reactive({});
const newSkkField = reactive(emptySkkField());
const integrationEdits = reactive({});
const newIntegration = reactive(emptyIntegration());

const editingSkkId = ref(null);
const editingIntegrationId = ref(null);
const showSkkAdd = ref(false);
const showIntegrationAdd = ref(false);

const typeLabel = computed(() => {
    switch (props.block.block_type) {
        case 'info':
            return 'Проект';
        case 'skk':
            return 'Поля';
        case 'integrations':
            return 'Виджеты';
        case 'funnel':
            return 'Воронка';
        default:
            return '';
    }
});

const countBadge = computed(() => {
    if (props.block.block_type === 'skk') {
        const n = props.block.skk_fields?.length ?? 0;
        return `${n} ${pluralRu(n, 'поле', 'поля', 'полей')}`;
    }
    if (props.block.block_type === 'integrations') {
        const n = props.block.integrations?.length ?? 0;
        return `${n} ${pluralRu(n, 'виджет', 'виджета', 'виджетов')}`;
    }
    if (props.block.block_type === 'funnel') {
        const n = props.block.funnel?.stages?.length ?? 0;
        return `${n} ${pluralRu(n, 'этап', 'этапа', 'этапов')}`;
    }
    return null;
});

watch(
    () => props.project,
    (project) => {
        infoForm.company_name = project.company_name;
        infoForm.business_sphere = project.business_sphere ?? '';
        infoForm.employees_count = project.employees_count ?? '';
        infoForm.client_tasks = project.client_tasks ?? '';
    },
    { deep: true },
);

watch(
    () => props.block.skk_fields,
    (fields) => {
        (fields ?? []).forEach((field) => {
            skkEdits[field.id] = skkFieldFromApi(field);
        });
    },
    { immediate: true, deep: true },
);

watch(
    () => props.block.entity_type,
    (entityType) => {
        if (entityType) {
            newSkkField.entity_type = entityType;
        }
    },
    { immediate: true },
);

watch(
    () => props.block.integrations,
    (integrations) => {
        (integrations ?? []).forEach((integration) => {
            integrationEdits[integration.id] = integrationFromApi(integration);
        });
    },
    { immediate: true, deep: true },
);

function pluralRu(n, one, few, many) {
    const abs = Math.abs(n) % 100;
    const last = abs % 10;
    if (abs > 10 && abs < 20) return many;
    if (last > 1 && last < 5) return few;
    if (last === 1) return one;
    return many;
}

function truncate(text, max = 120) {
    const value = String(text || '').trim();
    if (value.length <= max) return value;
    return `${value.slice(0, max - 1)}…`;
}

function formatIntegrationMeta(integration) {
    const parts = [];
    if (integration.comment) {
        parts.push(truncate(integration.comment, 90));
    }
    if (integration.cost != null) {
        parts.push(`${integration.cost} ₽`);
    }
    if (integration.billing_quantity != null) {
        parts.push(`×${integration.billing_quantity}`);
    }
    return parts.join(' · ');
}

async function saveForm(url, fields, method = 'PUT', options = {}) {
    const result = await submitCanvasForm({
        url,
        fields,
        method,
        confirmMessage: options.confirm,
        onStatus: () => {},
        reloadOnly: ['project', 'blocks'],
    });

    Object.keys(formErrors).forEach((key) => delete formErrors[key]);

    if (result.errors) {
        Object.assign(formErrors, result.errors);
        return false;
    }

    if (result.ok) {
        editingSkkId.value = null;
        editingIntegrationId.value = null;
        if (options.closeAdd) {
            showSkkAdd.value = false;
            showIntegrationAdd.value = false;
        }
    }

    return result.ok;
}

async function saveSkkField(field) {
    if (!skkEdits[field.id]) return;
    await saveForm(field.update_url, skkFieldToPayload(skkEdits[field.id]));
}

async function addSkkField() {
    const ok = await saveForm(props.project.urls.skk_fields_store, {
        entity_type: props.block.entity_type,
        ...skkFieldToPayload(newSkkField),
    }, 'POST', { closeAdd: true });

    if (ok) {
        Object.assign(newSkkField, emptySkkField());
        if (props.block.entity_type) {
            newSkkField.entity_type = props.block.entity_type;
        }
    }
}

async function saveIntegration(integration) {
    if (!integrationEdits[integration.id]) return;
    await saveForm(
        integration.update_url,
        integrationToPayload(integrationEdits[integration.id]),
    );
}

async function addIntegration() {
    const ok = await saveForm(
        props.project.urls.integrations_store,
        integrationToPayload(newIntegration),
        'POST',
        { closeAdd: true },
    );

    if (ok) {
        Object.assign(newIntegration, emptyIntegration());
    }
}

function cancelIntegrationAdd() {
    Object.assign(newIntegration, emptyIntegration());
    Object.keys(formErrors).forEach((key) => delete formErrors[key]);
    showIntegrationAdd.value = false;
}

function toggleSkkEdit(id) {
    editingSkkId.value = editingSkkId.value === id ? null : id;
}

function toggleIntegrationEdit(id) {
    editingIntegrationId.value = editingIntegrationId.value === id ? null : id;
}

function onDragStart(event) {
    emit('drag-start', event);
}

function onWheel(event) {
    event.stopPropagation();
}
</script>

<template>
    <article
        class="canvas-block canvas-block--editable"
        :class="[
            `canvas-block--${block.block_type}`,
            {
                'is-dragging': isDragging,
                'canvas-block--funnel-preview': block.block_type === 'funnel',
            },
        ]"
        :style="{
            left: `${block.pos_x}px`,
            top: `${block.pos_y}px`,
            width: `${block.width}px`,
            zIndex: block.z_index,
        }"
        @wheel="onWheel"
    >
        <header class="canvas-block__header" @mousedown="onDragStart">
            <div class="canvas-block__header-main">
                <span class="canvas-block__type">{{ typeLabel }}</span>
                <span class="canvas-block__title">{{ block.title }}</span>
            </div>
            <div class="canvas-block__header-aside">
                <span v-if="countBadge" class="canvas-block__count">{{ countBadge }}</span>
                <span class="canvas-block__drag-icon" aria-hidden="true">⠿</span>
            </div>
        </header>

        <div class="canvas-block__body">
            <template v-if="block.block_type === 'info'">
                <form class="canvas-form" @submit.prevent="saveForm(project.urls.update, { ...infoForm })">
                    <div class="canvas-form__group">
                        <label class="f5-label">Название компании</label>
                        <input
                            v-model="infoForm.company_name"
                            name="company_name"
                            type="text"
                            class="f5-input"
                            :class="{ 'f5-input--error': formErrors.company_name }"
                            required
                        >
                        <p v-if="formErrors.company_name" class="f5-error">{{ formErrors.company_name[0] }}</p>
                    </div>
                    <div class="canvas-form__row">
                        <div class="canvas-form__group">
                            <label class="f5-label">Сфера бизнеса</label>
                            <input
                                v-model="infoForm.business_sphere"
                                name="business_sphere"
                                type="text"
                                class="f5-input"
                                placeholder="Производство, услуги…"
                            >
                        </div>
                        <div class="canvas-form__group">
                            <label class="f5-label">Сотрудники</label>
                            <input
                                v-model="infoForm.employees_count"
                                name="employees_count"
                                type="number"
                                class="f5-input"
                                min="1"
                            >
                        </div>
                    </div>
                    <div class="canvas-form__group">
                        <label class="f5-label">Задачи клиента</label>
                        <textarea
                            v-model="infoForm.client_tasks"
                            name="client_tasks"
                            class="f5-input canvas-form__tasks"
                            rows="5"
                        />
                    </div>
                    <button type="submit" class="f5-btn f5-btn--primary f5-btn--sm w-full">Сохранить</button>
                </form>
            </template>

            <template v-else-if="block.block_type === 'skk'">
                <div class="canvas-rows">
                    <p v-if="!block.skk_fields?.length" class="canvas-block__empty">Поля не добавлены</p>
                    <div
                        v-for="field in block.skk_fields"
                        :key="field.id"
                        class="canvas-row"
                        :class="{ 'is-editing': editingSkkId === field.id }"
                    >
                        <div class="canvas-row__main">
                            <div class="canvas-row__top">
                                <strong class="canvas-row__title">{{ field.name }}</strong>
                                <span
                                    v-if="field.is_required"
                                    class="canvas-row__chip canvas-row__chip--required"
                                >обяз.</span>
                                <span class="canvas-row__chip">{{ field.value_type_label || field.value_type }}</span>
                            </div>
                            <p v-if="field.summary" class="canvas-row__meta">{{ field.summary }}</p>
                        </div>
                        <button
                            type="button"
                            class="canvas-row__action"
                            @click="toggleSkkEdit(field.id)"
                        >
                            {{ editingSkkId === field.id ? 'Скрыть' : 'Изменить' }}
                        </button>
                        <div v-if="editingSkkId === field.id && skkEdits[field.id]" class="canvas-row__editor">
                            <form class="canvas-form" @submit.prevent="saveSkkField(field)">
                                <SkkFieldForm
                                    v-model="skkEdits[field.id]"
                                    :funnel-stages="funnelStages"
                                    :errors="formErrors"
                                    compact
                                />
                                <div class="canvas-row__editor-actions">
                                    <button type="submit" class="f5-btn f5-btn--primary f5-btn--sm">Сохранить</button>
                                    <button
                                        type="button"
                                        class="f5-btn f5-btn--danger f5-btn--sm"
                                        @click="saveForm(field.destroy_url, {}, 'DELETE', { confirm: `Удалить поле «${field.name}»?` })"
                                    >
                                        Удалить
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="canvas-add">
                    <button
                        type="button"
                        class="f5-btn f5-btn--ghost f5-btn--sm w-full"
                        @click="showSkkAdd = !showSkkAdd"
                    >
                        {{ showSkkAdd ? 'Скрыть форму' : '+ Добавить поле' }}
                    </button>
                    <form
                        v-if="showSkkAdd"
                        class="canvas-form canvas-add-form"
                        @submit.prevent="addSkkField"
                    >
                        <SkkFieldForm
                            v-model="newSkkField"
                            :funnel-stages="funnelStages"
                            :errors="formErrors"
                            compact
                        />
                        <button type="submit" class="f5-btn f5-btn--primary f5-btn--sm w-full">Добавить поле</button>
                    </form>
                </div>
            </template>

            <template v-else-if="block.block_type === 'integrations'">
                <div class="canvas-rows">
                    <p v-if="!block.integrations?.length" class="canvas-block__empty">Интеграции не добавлены</p>
                    <div
                        v-for="integration in block.integrations"
                        :key="integration.id"
                        class="canvas-row"
                        :class="{ 'is-editing': editingIntegrationId === integration.id }"
                    >
                        <div class="canvas-row__main">
                            <div class="canvas-row__top">
                                <strong class="canvas-row__title">{{ integration.name }}</strong>
                                <span
                                    v-if="integration.is_custom"
                                    class="canvas-row__chip"
                                >своё</span>
                            </div>
                            <p v-if="formatIntegrationMeta(integration)" class="canvas-row__meta">
                                {{ formatIntegrationMeta(integration) }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="canvas-row__action"
                            @click="toggleIntegrationEdit(integration.id)"
                        >
                            {{ editingIntegrationId === integration.id ? 'Скрыть' : 'Изменить' }}
                        </button>
                        <div
                            v-if="editingIntegrationId === integration.id && integrationEdits[integration.id]"
                            class="canvas-row__editor"
                        >
                            <form class="canvas-form" @submit.prevent="saveIntegration(integration)">
                                <IntegrationWidgetPicker
                                    v-model="integrationEdits[integration.id]"
                                    :catalog="widgetCatalog"
                                    :errors="formErrors"
                                    compact
                                />
                                <div class="canvas-row__editor-actions">
                                    <button type="submit" class="f5-btn f5-btn--primary f5-btn--sm">Сохранить</button>
                                    <button
                                        type="button"
                                        class="f5-btn f5-btn--danger f5-btn--sm"
                                        @click="saveForm(integration.destroy_url, {}, 'DELETE', { confirm: `Удалить «${integration.name}»?` })"
                                    >
                                        Удалить
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="canvas-add">
                    <button
                        type="button"
                        class="f5-btn f5-btn--ghost f5-btn--sm w-full"
                        @click="showIntegrationAdd = !showIntegrationAdd"
                    >
                        {{ showIntegrationAdd ? 'Скрыть форму' : '+ Добавить виджет' }}
                    </button>
                    <form
                        v-if="showIntegrationAdd"
                        class="canvas-form canvas-add-form"
                        @submit.prevent="addIntegration"
                    >
                        <IntegrationWidgetPicker
                            v-model="newIntegration"
                            :catalog="widgetCatalog"
                            :errors="formErrors"
                            compact
                        />
                        <div class="flex flex-col gap-2">
                            <button type="submit" class="f5-btn f5-btn--primary f5-btn--sm w-full">Добавить</button>
                            <button
                                type="button"
                                class="f5-btn f5-btn--ghost f5-btn--sm w-full"
                                @click="cancelIntegrationAdd"
                            >
                                Отмена
                            </button>
                        </div>
                    </form>
                </div>
            </template>

            <template v-else-if="block.block_type === 'funnel' && block.funnel">
                <p v-if="block.funnel.description" class="canvas-block__text">
                    {{ truncate(block.funnel.description, 200) }}
                </p>
                <p v-if="!block.funnel.stages?.length" class="canvas-block__empty">Этапы не добавлены</p>
                <ol v-else class="canvas-funnel-stages">
                    <li
                        v-for="stage in block.funnel.stages"
                        :key="stage.number"
                        class="canvas-funnel-stages__item"
                        :class="`is-${stage.kind || 'normal'}`"
                    >
                        <span class="canvas-funnel-stages__number">{{ stage.number }}</span>
                        <div class="canvas-funnel-stages__body">
                            <div class="canvas-funnel-stages__name">{{ stage.name }}</div>
                            <p v-if="stage.action" class="canvas-funnel-stages__action">
                                {{ truncate(stage.action, 110) }}
                            </p>
                            <div v-if="stage.deadline" class="canvas-funnel-stages__sla">
                                SLA: {{ stage.deadline }}
                            </div>
                        </div>
                    </li>
                </ol>
                <Link :href="block.funnel.canvas_url" class="f5-btn f5-btn--primary f5-btn--sm w-full">
                    Открыть доску воронки
                </Link>
            </template>
        </div>
    </article>
</template>
