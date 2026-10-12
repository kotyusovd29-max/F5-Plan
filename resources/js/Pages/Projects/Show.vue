<script setup>
import { computed, provide, ref } from 'vue';
import { confirmDialog } from '../../utils/confirmDialog';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import ProjectNav from '../../Components/Projects/ProjectNav.vue';
import CompanyInfoCard from '../../Components/Projects/CompanyInfoCard.vue';
import WorkspaceCard from '../../Components/Projects/WorkspaceCard.vue';
import ProjectSectionsNav from '../../Components/Projects/ProjectSectionsNav.vue';
import SectionEmptyState from '../../Components/Projects/SectionEmptyState.vue';
import SkkSection from '../../Components/Projects/SkkSection.vue';
import IntegrationsPanel from '../../Components/Projects/IntegrationsPanel.vue';
import AmocrmLicensePanel from '../../Components/Projects/AmocrmLicensePanel.vue';
import WorkStagesPanel from '../../Components/Projects/WorkStagesPanel.vue';
import ImplementationPackageCard from '../../Components/Projects/ImplementationPackageCard.vue';
import DragTrash from '../../Components/Projects/DragTrash.vue';
import AiAssistantCard from '../../Components/Projects/AiAssistantCard.vue';
import AmocrmPanel from '../../Components/Projects/AmocrmPanel.vue';
import ProjectAccessCard from '../../Components/Projects/ProjectAccessCard.vue';
import ProjectLifecycleCard from '../../Components/Projects/ProjectLifecycleCard.vue';
import LibraryApplyModal from '../../Components/Projects/LibraryApplyModal.vue';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
    editorOptions: {
        type: Array,
        default: () => [],
    },
    amocrm: {
        type: Object,
        default: () => ({
            connected: false,
            urls: {},
        }),
    },
    briefDocuments: {
        type: Array,
        default: () => [],
    },
    aiPrompts: {
        type: Object,
        default: () => ({ analysis: null, actions: [] }),
    },
    skkSectionLabel: {
        type: String,
        default: 'Поля',
    },
    funnelStages: {
        type: Array,
        default: () => [],
    },
    skkSections: {
        type: Array,
        default: () => [],
    },
    integrations: {
        type: Array,
        default: () => [],
    },
    workStages: {
        type: Array,
        default: () => [],
    },
    f5WidgetCatalog: {
        type: Object,
        default: () => ({ categories: {}, widgets: [] }),
    },
    amocrmLicenses: {
        type: Object,
        default: () => ({
            tariffs: [],
            source_url: 'https://www.amocrm.ru/buy/',
            source_note: '',
        }),
    },
    funnels: {
        type: Array,
        default: () => [],
    },
    library: {
        type: Object,
        default: () => ({ funnels: [], skk_fields: [] }),
    },
});

const canEdit = computed(() => props.project.can_edit !== false);
provide('projectCanEdit', canEdit);

const integrationsPanel = ref(null);
const workStagesPanel = ref(null);
const integrationsCard = ref(null);
const workStagesCard = ref(null);
const funnelsCard = ref(null);
const showFunnelAdd = ref(false);
const showFunnelLibrary = ref(false);
const showSkkLibrary = ref(false);
const skkLibraryEntityType = ref(null);

const projectForm = useForm({
    company_name: props.project.company_name,
    website: props.project.website ?? '',
    business_sphere: props.project.business_sphere ?? '',
    current_services: props.project.current_services ?? '',
    employees_count: props.project.employees_count ?? '',
    client_tasks: props.project.client_tasks ?? '',
    amocrm_deal_url: props.project.amocrm_deal_url ?? '',
});

const funnelForm = useForm({
    name: '',
});

const skkFieldsCount = computed(() => (
    props.skkSections.reduce((total, section) => total + (section.fields?.length ?? 0), 0)
));

const integrationsCount = computed(() => props.integrations.length);
const workStagesCount = computed(() => props.workStages.length);
const funnelsCount = computed(() => props.funnels.length);

const sectionStorage = (key) => `f5plan:project-section:${props.project.id}:${key}`;

const projectSections = computed(() => [
    { id: 'project-company', label: 'Компания', count: null, filled: true },
    { id: 'project-funnels', label: 'Воронки', count: funnelsCount.value, filled: funnelsCount.value > 0 },
    { id: 'project-skk', label: props.skkSectionLabel, count: skkFieldsCount.value, filled: skkFieldsCount.value > 0 },
    { id: 'project-integrations', label: 'Виджеты', count: integrationsCount.value, filled: integrationsCount.value > 0 },
    { id: 'project-license', label: 'Лицензия', count: null, filled: Boolean(props.project.amocrm_license_tariff) },
    { id: 'project-package', label: 'Пакет', count: null, filled: workStagesCount.value > 0 || integrationsCount.value > 0 },
    { id: 'project-work-stages', label: 'Этапы', count: workStagesCount.value, filled: workStagesCount.value > 0 },
]);

const readinessBlocks = computed(() => [
    { id: 'project-funnels', label: 'Воронки', count: funnelsCount.value, filled: funnelsCount.value > 0 },
    { id: 'project-skk', label: props.skkSectionLabel, count: skkFieldsCount.value, filled: skkFieldsCount.value > 0 },
    { id: 'project-integrations', label: 'Виджеты', count: integrationsCount.value, filled: integrationsCount.value > 0 },
    { id: 'project-license', label: 'Лицензия', count: null, filled: Boolean(props.project.amocrm_license_tariff) },
    { id: 'project-work-stages', label: 'Этапы', count: workStagesCount.value, filled: workStagesCount.value > 0 },
]);

const skkLibraryItems = computed(() => {
    const fields = props.library?.skk_fields ?? [];

    if (!skkLibraryEntityType.value) {
        return fields;
    }

    return fields.filter((field) => field.entity_type === skkLibraryEntityType.value);
});

function openSkkLibrary(entityType = null) {
    skkLibraryEntityType.value = entityType;
    showSkkLibrary.value = true;
}

function scrollToSection(id) {
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function startAddFunnel() {
    funnelsCard.value?.open?.();
    showFunnelAdd.value = true;
    scrollToSection('project-funnels');
}

function startAddIntegration() {
    integrationsCard.value?.open?.();
    integrationsPanel.value?.openAdd?.();
    scrollToSection('project-integrations');
}

function startAddWorkStage() {
    workStagesCard.value?.open?.();
    workStagesPanel.value?.openAdd?.();
    scrollToSection('project-work-stages');
}

function saveProject(onSuccess) {
    if (!canEdit.value) {
        return;
    }

    projectForm.put(props.project.urls.update, {
        preserveScroll: true,
        onSuccess: () => onSuccess?.(),
    });
}

function submitFunnel() {
    if (!canEdit.value) {
        return;
    }

    funnelForm.post(props.project.urls.funnels_store, {
        preserveScroll: true,
        onSuccess: () => {
            funnelForm.reset();
            showFunnelAdd.value = false;
        },
    });
}

async function deleteFunnel(funnel) {
    if (!canEdit.value) {
        return;
    }

    const confirmed = await confirmDialog({
        title: 'Удалить воронку?',
        message: `Воронка «${funnel.name}» и все её этапы будут удалены без возможности восстановления.`,
    });

    if (!confirmed) {
        return;
    }

    router.delete(funnel.destroy_url, { preserveScroll: true });
}

async function deleteAllFunnels() {
    if (!canEdit.value) {
        return;
    }

    const confirmed = await confirmDialog({
        title: 'Удалить все воронки?',
        message: `Будут удалены все воронки проекта (${funnelsCount.value} шт.) вместе с этапами. Это действие нельзя отменить.`,
    });

    if (!confirmed) {
        return;
    }

    router.delete(props.project.urls.funnels_bulk_destroy, { preserveScroll: true });
}

async function deleteAllIntegrations() {
    if (!canEdit.value) {
        return;
    }

    const confirmed = await confirmDialog({
        title: 'Удалить все интеграции?',
        message: `Будут удалены все интеграции и виджеты (${integrationsCount.value} шт.). Это действие нельзя отменить.`,
    });

    if (!confirmed) {
        return;
    }

    router.delete(props.project.urls.integrations_bulk_destroy, { preserveScroll: true });
}

async function deleteAllWorkStages() {
    if (!canEdit.value) {
        return;
    }

    const confirmed = await confirmDialog({
        title: 'Удалить все этапы работ?',
        message: `Будут удалены все этапы работ (${workStagesCount.value} шт.). Это действие нельзя отменить.`,
    });

    if (!confirmed) {
        return;
    }

    router.delete(props.project.urls.work_stages_bulk_destroy, { preserveScroll: true });
}
</script>

<template>
    <Head :title="project.company_name" />

    <AppLayout>
        <template #header>
            <div>
                <h1 class="text-xl font-bold text-f5-text dark:text-white">
                    {{ project.company_name }}
                </h1>
                <p class="mt-1 text-sm text-f5-text-muted dark:text-white/50">
                    <Link :href="project.urls.home" class="hover:text-f5-text dark:hover:text-white">← К проектам</Link>
                    <span> · {{ project.author }}</span>
                    <span v-if="!canEdit"> · Только просмотр</span>
                </p>
            </div>
        </template>

        <div class="mx-auto max-w-7xl">
            <div
                v-if="project.is_training"
                class="mb-4 rounded-xl border border-f5-green/40 bg-f5-green/10 px-4 py-3 text-sm text-f5-text dark:text-white"
            >
                Учебный проект для обучения. Доступен всем для просмотра; редактировать может только администратор.
                <Link
                    href="/knowledge"
                    class="ml-1 font-medium underline"
                >
                    Вернуться в базу знаний
                </Link>
            </div>
            <div
                v-else-if="!canEdit"
                class="mb-4 rounded-xl border border-f5-yellow/40 bg-f5-yellow/10 px-4 py-3 text-sm text-f5-text dark:text-white"
            >
                Режим просмотра: изменять проект могут ответственный менеджер, его РОП и назначенные сотрудники.
            </div>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] xl:grid-cols-[minmax(0,1fr)_22.5rem]">
                <div class="order-2 min-w-0 space-y-4 lg:order-1">
                    <ProjectSectionsNav :sections="projectSections">
                        <template #leading>
                            <ProjectNav
                                :project-id="project.id"
                                :urls="project.urls"
                                current="project"
                                :can-edit="canEdit"
                                compact
                            />
                        </template>
                    </ProjectSectionsNav>

                    <div id="project-company" class="scroll-mt-40">
                        <CompanyInfoCard :project="project" :form="projectForm" :can-edit="canEdit" @save="saveProject" />
                    </div>

                    <WorkspaceCard
                        ref="funnelsCard"
                        section-id="project-funnels"
                        :count="funnelsCount"
                        collapsible
                        :default-open="true"
                        :storage-key="sectionStorage('funnels')"
                        primary
                    >
                        <template #title>Воронки</template>
                        <template #subtitle>Этапы продаж и бизнес-процессы</template>
                        <template #actions>
                            <button
                                v-if="canEdit && funnelsCount > 0"
                                type="button"
                                class="f5-btn f5-btn--danger f5-btn--sm"
                                @click="deleteAllFunnels"
                            >
                                Удалить все
                            </button>
                            <button
                                v-if="canEdit && (library?.funnels?.length ?? 0) > 0"
                                type="button"
                                class="f5-btn f5-btn--ghost f5-btn--sm"
                                @click="showFunnelLibrary = true"
                            >
                                Из библиотеки
                            </button>
                            <button
                                v-if="canEdit"
                                type="button"
                                class="f5-btn f5-btn--primary f5-btn--sm"
                                @click="showFunnelAdd = !showFunnelAdd; funnelsCard?.open?.()"
                            >
                                Добавить воронку
                            </button>
                        </template>

                        <div v-if="canEdit && showFunnelAdd" class="mb-5 rounded-xl border border-f5-border bg-f5-surface-2 p-4 dark:border-white/10 dark:bg-white/5">
                            <form class="space-y-4" @submit.prevent="submitFunnel">
                                <div>
                                    <label for="funnel_name" class="f5-label">Название воронки</label>
                                    <input
                                        id="funnel_name"
                                        v-model="funnelForm.name"
                                        type="text"
                                        class="f5-input"
                                        :class="{ 'f5-input--error': funnelForm.errors.name }"
                                        placeholder="Например: Продажи"
                                        required
                                    >
                                    <p v-if="funnelForm.errors.name" class="f5-error">{{ funnelForm.errors.name }}</p>
                                </div>
                                <button type="submit" class="f5-btn f5-btn--primary" :disabled="funnelForm.processing">
                                    Создать воронку
                                </button>
                            </form>
                        </div>

                        <SectionEmptyState
                            v-if="funnels.length === 0"
                            title="Воронок пока нет"
                            description="Соберите этапы продаж — с нуля или из библиотеки шаблонов."
                            :action-label="canEdit ? 'Добавить воронку' : ''"
                            @action="startAddFunnel"
                        >
                            <template v-if="canEdit && (library?.funnels?.length ?? 0) > 0" #actions>
                                <button
                                    type="button"
                                    class="f5-btn f5-btn--ghost f5-btn--sm"
                                    @click="showFunnelLibrary = true"
                                >
                                    Из библиотеки
                                </button>
                            </template>
                            <template #icon>
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6">
                                    <path d="M3.5 5.5h13M5.5 5.5v9M14.5 5.5v9M8 9.5h4M8 12.5h4" stroke-linecap="round" />
                                </svg>
                            </template>
                        </SectionEmptyState>

                        <div v-else class="f5-stagger grid gap-3 sm:grid-cols-2">
                            <article
                                v-for="(funnel, index) in funnels"
                                :key="funnel.id"
                                class="f5-stagger-item f5-card-lift flex items-stretch gap-2 rounded-xl border border-f5-border bg-f5-surface-2 hover:border-f5-green/50 dark:border-white/10 dark:bg-white/5 dark:hover:border-f5-green/40"
                                :style="{ '--stagger-index': index }"
                            >
                                <Link
                                    :href="funnel.show_url"
                                    class="flex min-w-0 flex-1 flex-col justify-center gap-1 p-4 no-underline"
                                >
                                    <h4 class="text-sm font-semibold text-f5-text dark:text-white">{{ funnel.name }}</h4>
                                    <p class="text-xs text-f5-text-muted dark:text-white/50">
                                        {{ funnel.stages_count }} {{ funnel.stages_label }}
                                    </p>
                                </Link>
                                <div class="flex shrink-0 items-center gap-1 pr-3">
                                    <Link :href="funnel.canvas_url" class="f5-btn f5-btn--ghost f5-btn--sm">
                                        Доска
                                    </Link>
                                    <button
                                        v-if="canEdit"
                                        type="button"
                                        class="f5-btn f5-btn--danger f5-btn--sm !min-w-8 !px-2 text-lg leading-none"
                                        title="Удалить воронку"
                                        @click="deleteFunnel(funnel)"
                                    >
                                        ×
                                    </button>
                                </div>
                            </article>
                        </div>
                    </WorkspaceCard>

                    <WorkspaceCard
                        section-id="project-skk"
                        flush
                        :count="skkFieldsCount"
                        collapsible
                        :default-open="skkFieldsCount === 0"
                        :storage-key="sectionStorage('skk')"
                    >
                        <template #title>{{ skkSectionLabel }}</template>
                        <template #subtitle>Сделки, контакты и компании</template>
                        <template v-if="canEdit && (library?.skk_fields?.length ?? 0) > 0" #actions>
                            <button
                                type="button"
                                class="f5-btn f5-btn--ghost f5-btn--sm"
                                @click="openSkkLibrary()"
                            >
                                Из библиотеки
                            </button>
                        </template>

                        <div class="grid items-start gap-4 p-5 lg:grid-cols-3">
                            <SkkSection
                                v-for="section in skkSections"
                                :key="section.entity_type"
                                :section="section"
                                :store-url="project.urls.skk_fields_store"
                                :reorder-url="project.urls.skk_fields_reorder"
                                :funnel-stages="funnelStages"
                                :can-edit="canEdit"
                                :has-library="(library?.skk_fields ?? []).some((field) => field.entity_type === section.entity_type)"
                                @open-library="openSkkLibrary(section.entity_type)"
                            />
                        </div>
                    </WorkspaceCard>

                    <WorkspaceCard
                        ref="integrationsCard"
                        section-id="project-integrations"
                        flush
                        :count="integrationsCount"
                        collapsible
                        :default-open="integrationsCount === 0"
                        :storage-key="sectionStorage('integrations')"
                    >
                        <template #title>Интеграции и виджеты</template>
                        <template #subtitle>Подключённые сервисы и дополнения</template>
                        <template #actions>
                            <button
                                v-if="canEdit && integrationsCount > 0"
                                type="button"
                                class="f5-btn f5-btn--danger f5-btn--sm"
                                @click="deleteAllIntegrations"
                            >
                                Удалить все
                            </button>
                            <button
                                v-if="canEdit"
                                type="button"
                                class="f5-btn f5-btn--primary f5-btn--sm"
                                @click="startAddIntegration"
                            >
                                Добавить
                            </button>
                        </template>

                        <IntegrationsPanel
                            ref="integrationsPanel"
                            :project-id="project.id"
                            :integrations="integrations"
                            :catalog="f5WidgetCatalog"
                            :employees-count="project.employees_count"
                            :store-url="project.urls.integrations_store"
                            :reorder-url="project.urls.integrations_reorder"
                            :can-edit="canEdit"
                        />
                    </WorkspaceCard>

                    <WorkspaceCard
                        section-id="project-license"
                        flush
                        collapsible
                        :default-open="true"
                        :storage-key="sectionStorage('license')"
                    >
                        <template #title>Лицензия amoCRM</template>
                        <template #subtitle>
                            Базовый · Расширенный · Профессиональный
                        </template>

                        <AmocrmLicensePanel
                            :project="project"
                            :employees-count="project.employees_count"
                            :licenses="amocrmLicenses"
                            :can-edit="canEdit"
                        />
                    </WorkspaceCard>

                    <div id="project-package" class="scroll-mt-40">
                        <ImplementationPackageCard
                            :work-stages="workStages"
                            :integrations="integrations"
                            :catalog="f5WidgetCatalog"
                            :employees-count="project.employees_count"
                            :amocrm-license-tariff="project.amocrm_license_tariff"
                            :amocrm-licenses="amocrmLicenses"
                            :export-xlsx-url="project.urls.export_xlsx"
                            :export-google-sheets-url="project.urls.export_google_sheets"
                            :can-edit="canEdit"
                        />
                    </div>

                    <WorkspaceCard
                        ref="workStagesCard"
                        section-id="project-work-stages"
                        flush
                        :count="workStagesCount"
                        collapsible
                        :default-open="workStagesCount === 0"
                        :storage-key="sectionStorage('work_stages')"
                    >
                        <template #title>Этапы работ</template>
                        <template #subtitle>Состав и стоимость этапов внедрения</template>
                        <template #actions>
                            <button
                                v-if="canEdit && workStagesCount > 0"
                                type="button"
                                class="f5-btn f5-btn--danger f5-btn--sm"
                                @click="deleteAllWorkStages"
                            >
                                Удалить все
                            </button>
                            <button
                                v-if="canEdit"
                                type="button"
                                class="f5-btn f5-btn--primary f5-btn--sm"
                                @click="startAddWorkStage"
                            >
                                Добавить
                            </button>
                        </template>

                        <WorkStagesPanel
                            ref="workStagesPanel"
                            :project="project"
                            :work-stages="workStages"
                            :store-url="project.urls.work_stages_store"
                            :reorder-url="project.urls.work_stages_reorder"
                            :can-edit="canEdit"
                        />
                    </WorkspaceCard>
                </div>

                <aside class="order-1 min-w-0 lg:order-2">
                    <div class="space-y-4 lg:sticky lg:top-[5.5rem]">
                        <ProjectLifecycleCard
                            :project="project"
                            :readiness-blocks="readinessBlocks"
                            @jump="scrollToSection"
                        />

                        <ProjectAccessCard :project="project" :editor-options="editorOptions" />

                        <div data-tour="project-ai">
                            <AiAssistantCard
                                :project="project"
                                :ai-prompts="aiPrompts"
                                :brief-documents="briefDocuments"
                                :can-edit="canEdit"
                                compact
                                collapsible
                                :default-open="false"
                                :storage-key="`f5plan:project-aside:ai:${project.id}`"
                            />
                        </div>

                        <AmocrmPanel
                            :amocrm="amocrm"
                            :can-edit="canEdit"
                            collapsible
                            :default-open="!amocrm.connected"
                            :storage-key="`f5plan:project-aside:amocrm:${project.id}`"
                        />
                    </div>
                </aside>
            </div>
        </div>

        <DragTrash v-if="canEdit" />

        <LibraryApplyModal
            :open="showFunnelLibrary"
            title="Воронка из библиотеки"
            :items="library?.funnels ?? []"
            empty-text="В библиотеке пока нет воронок. Их добавляет администратор."
            @close="showFunnelLibrary = false"
        />

        <LibraryApplyModal
            :open="showSkkLibrary"
            :title="skkLibraryEntityType ? 'Поле СКК из библиотеки' : 'Поля СКК из библиотеки'"
            :items="skkLibraryItems"
            empty-text="В библиотеке пока нет полей СКК. Их добавляет администратор."
            @close="showSkkLibrary = false"
        />
    </AppLayout>
</template>
