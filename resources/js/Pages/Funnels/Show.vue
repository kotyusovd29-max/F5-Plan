<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import FunnelStageBlock from '../../Components/Funnels/FunnelStageBlock.vue';
import FunnelNewStageForm from '../../Components/Funnels/FunnelNewStageForm.vue';
import { useSortableList } from '../../composables/useSortableList';
import { useDragScroll } from '../../composables/useDragScroll';
import { confirmDialog } from '../../utils/confirmDialog';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
    funnel: {
        type: Object,
        required: true,
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

const page = usePage();
const pipelineRef = ref(null);
const pipelineTrackRef = ref(null);
const showSettings = ref(false);
const openStageId = ref(null);
const newStageAfter = ref(null);
const canEdit = computed(() => props.project.can_edit !== false);

const stages = ref([...props.funnel.stages]);

watch(
    () => props.funnel.stages,
    (value) => {
        stages.value = [...value];
    },
    { deep: true },
);

const funnelForm = useForm({
    name: props.funnel.name,
    description: props.funnel.description ?? '',
});

watch(
    () => props.funnel,
    (funnel) => {
        funnelForm.name = funnel.name;
        funnelForm.description = funnel.description ?? '';
    },
    { deep: true },
);

const funnelMeta = computed(() => {
    const count = props.funnel.stages_count;
    const label = count === 1 ? 'этап' : count >= 2 && count <= 4 ? 'этапа' : 'этапов';
    let meta = `${count} ${label}`;

    if (props.funnel.description) {
        const text = props.funnel.description;
        meta += ` · ${text.length > 120 ? `${text.slice(0, 120)}…` : text}`;
    }

    return meta;
});

const defaultNewStageColor = computed(() => {
    const index = stages.value.length;
    return props.colorPalette[index % props.colorPalette.length] ?? '#99ccff';
});

useSortableList(pipelineTrackRef, {
    direction: 'horizontal',
    reorderUrl: canEdit.value ? props.funnel.urls.stages_reorder : '',
    onReordered: applyStagesOrder,
});

useDragScroll(pipelineRef);

function applyStagesOrder(orderIds) {
    const byId = new Map(stages.value.map((stage) => [Number(stage.id), stage]));
    const next = [];

    for (const [index, id] of orderIds.entries()) {
        const stage = byId.get(Number(id));

        if (!stage) {
            continue;
        }

        next.push({
            ...stage,
            number: index + 1,
        });
    }

    if (next.length === stages.value.length) {
        stages.value = next;
    }
}

function toggleSettings() {
    if (!canEdit.value) {
        return;
    }

    showSettings.value = !showSettings.value;
}

function toggleStage(stageId) {
    if (!canEdit.value) {
        return;
    }

    openStageId.value = openStageId.value === stageId ? null : stageId;
}

function openNewStage(afterStageId) {
    if (!canEdit.value) {
        return;
    }

    newStageAfter.value = afterStageId;
    openStageId.value = null;
}

function closeNewStage() {
    newStageAfter.value = null;
}

function saveFunnelSettings() {
    funnelForm.put(props.funnel.urls.update, {
        preserveScroll: true,
    });
}

async function deleteFunnel() {
    const confirmed = await confirmDialog({
        title: 'Удалить воронку?',
        message: `Воронка «${props.funnel.name}» и все её этапы будут удалены без возможности восстановления.`,
    });

    if (!confirmed) {
        return;
    }

    router.delete(props.funnel.urls.destroy);
}

const errors = computed(() => page.props.errors ?? {});

watch(
    errors,
    (value) => {
        if (value.name && !value.action && !openStageId.value) {
            showSettings.value = true;
        }

        if (value.name && value.action !== undefined) {
            newStageAfter.value = newStageAfter.value ?? '';
        }
    },
    { immediate: true },
);
</script>

<template>
    <Head :title="`${funnel.name} — ${project.company_name}`" />

    <AppLayout fluid>
        <template #header>
            <div class="min-w-0">
                <h1 class="truncate text-lg font-semibold text-f5-text dark:text-white">
                    {{ funnel.name }}
                </h1>
                <p class="text-sm text-f5-text-muted dark:text-white/50">
                    {{ project.company_name }}
                </p>
            </div>
        </template>

        <div class="funnel-page-root">
            <div class="funnel-page">
                <header class="funnel-page__toolbar">
                    <div class="funnel-page__toolbar-main">
                        <Link :href="project.urls.show" class="funnel-page__back">
                            ← {{ project.company_name }}
                        </Link>
                        <div class="funnel-page__title-wrap">
                            <h2 class="funnel-page__title">{{ funnel.name }}</h2>
                            <p class="funnel-page__meta">{{ funnelMeta }}</p>
                        </div>
                    </div>

                    <div class="funnel-page__toolbar-actions">
                        <button
                            v-if="canEdit"
                            type="button"
                            class="f5-btn f5-btn--ghost f5-btn--sm funnel-page__settings-btn"
                            :class="{ 'is-active': showSettings }"
                            @click="toggleSettings"
                        >
                            Настройки воронки
                        </button>
                        <Link :href="funnel.urls.canvas" class="f5-btn f5-btn--primary f5-btn--sm">
                            Доска
                        </Link>
                    </div>
                </header>

                <section v-show="canEdit && showSettings" class="funnel-page__settings">
                    <div class="funnel-page__settings-inner">
                        <form class="funnel-page__settings-form" @submit.prevent="saveFunnelSettings">
                            <div class="funnel-page__settings-grid">
                                <div>
                                    <label for="funnel_name" class="f5-label">Название воронки</label>
                                    <input
                                        id="funnel_name"
                                        v-model="funnelForm.name"
                                        type="text"
                                        class="f5-input"
                                        :class="{ 'f5-input--error': funnelForm.errors.name }"
                                        required
                                    >
                                    <p v-if="funnelForm.errors.name" class="f5-error">{{ funnelForm.errors.name }}</p>
                                </div>

                                <div>
                                    <label for="funnel_description" class="f5-label">Информация по воронке</label>
                                    <textarea
                                        id="funnel_description"
                                        v-model="funnelForm.description"
                                        class="f5-input min-h-[180px] resize-y"
                                        rows="6"
                                        placeholder="Опишите назначение воронки, тип клиентов, особенности процесса..."
                                    />
                                    <p v-if="funnelForm.errors.description" class="f5-error">
                                        {{ funnelForm.errors.description }}
                                    </p>
                                </div>
                            </div>
                        </form>

                        <div class="funnel-page__settings-actions">
                            <button
                                type="button"
                                class="f5-btn f5-btn--primary f5-btn--sm"
                                :disabled="funnelForm.processing"
                                @click="saveFunnelSettings"
                            >
                                Сохранить воронку
                            </button>
                            <button
                                type="button"
                                class="f5-btn f5-btn--danger f5-btn--sm"
                                @click="deleteFunnel"
                            >
                                Удалить воронку
                            </button>
                        </div>
                    </div>
                </section>

                <section class="funnel-page__pipeline-wrap">
                    <div class="funnel-page__pipeline-head">
                        <div class="funnel-page__pipeline-heading">
                            <h2 class="funnel-page__pipeline-title">Этапы воронки</h2>
                            <span class="funnel-page__pipeline-count">{{ funnel.stages_count }}</span>
                        </div>
                        <p class="funnel-page__pipeline-hint">
                            <template v-if="canEdit">
                                Перетащите этап за ⠿ · нажмите на карточку для редактирования · цвет полосы задаётся в настройках этапа
                            </template>
                            <template v-else>
                                Режим просмотра: изменение этапов недоступно
                            </template>
                        </p>
                    </div>

                    <div ref="pipelineRef" class="funnel-pipeline">
                        <div ref="pipelineTrackRef" class="funnel-pipeline__track">
                        <template v-if="stages.length === 0">
                            <div class="funnel-pipeline__empty">
                                <p class="funnel-pipeline__empty-text">
                                    Этапов пока нет. Добавьте первый этап воронки.
                                </p>
                                <button
                                    v-if="canEdit"
                                    type="button"
                                    class="f5-btn f5-btn--primary"
                                    @click="openNewStage('')"
                                >
                                    Создать этап
                                </button>
                            </div>

                            <FunnelNewStageForm
                                v-if="canEdit && newStageAfter === ''"
                                :store-url="funnel.urls.stages_store"
                                after-stage-id=""
                                :default-color="defaultNewStageColor"
                                :color-palette="colorPalette"
                                :stages="stages"
                                :f5-widget-catalog="f5WidgetCatalog"
                                @cancel="closeNewStage"
                            />
                        </template>

                        <template v-else>
                            <template v-for="stage in stages" :key="stage.id">
                                <div
                                    class="funnel-pipeline__col sortable-item"
                                    :data-sortable-id="stage.id"
                                >
                                    <FunnelStageBlock
                                        :stage="stage"
                                        :stages="stages"
                                        :open="openStageId === stage.id"
                                        :color-palette="colorPalette"
                                        :f5-widget-catalog="f5WidgetCatalog"
                                        @toggle="toggleStage(stage.id)"
                                    />

                                    <button
                                        v-if="canEdit"
                                        type="button"
                                        class="funnel-pipeline__add"
                                        :title="`Добавить этап после «${stage.name}»`"
                                        :aria-label="`Добавить этап после «${stage.name}»`"
                                        @click="openNewStage(stage.id)"
                                    >
                                        +
                                    </button>
                                </div>

                                <FunnelNewStageForm
                                    v-if="canEdit && newStageAfter === stage.id"
                                    :store-url="funnel.urls.stages_store"
                                    :after-stage-id="stage.id"
                                    :default-color="defaultNewStageColor"
                                    :color-palette="colorPalette"
                                    :stages="stages"
                                    :f5-widget-catalog="f5WidgetCatalog"
                                    @cancel="closeNewStage"
                                />
                            </template>
                        </template>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
