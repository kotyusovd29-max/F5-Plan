<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import SortableList from './SortableList.vue';
import SortableBlock from './SortableBlock.vue';
import SkkFieldForm from './SkkFieldForm.vue';
import { emptySkkField, skkFieldFromApi, skkFieldSummary, skkFieldToPayload } from '../../utils/skkField';
import { confirmDialog } from '../../utils/confirmDialog';
import SectionEmptyState from './SectionEmptyState.vue';

const COLLAPSE_THRESHOLD = 4;
const COLLAPSIBLE_ENTITY_TYPES = new Set(['deals', 'contacts', 'companies']);

const props = defineProps({
    section: {
        type: Object,
        required: true,
    },
    storeUrl: {
        type: String,
        required: true,
    },
    reorderUrl: {
        type: String,
        required: true,
    },
    funnelStages: {
        type: Array,
        default: () => [],
    },
    canEdit: {
        type: Boolean,
        default: true,
    },
    hasLibrary: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['open-library']);

const fields = ref([...props.section.fields]);
const showAddForm = ref(false);
const openFieldId = ref(null);
const edits = reactive({});
const isExpanded = ref(false);

const isCollapsible = computed(() => (
    COLLAPSIBLE_ENTITY_TYPES.has(props.section.entity_type)
    && fields.value.length >= COLLAPSE_THRESHOLD
));

const isListCollapsed = computed(() => isCollapsible.value && !isExpanded.value);

const previewFieldNames = computed(() => (
    fields.value.slice(0, 3).map((field) => field.name).join(', ')
));

const hiddenFieldsCount = computed(() => Math.max(fields.value.length - 3, 0));

watch(() => props.section.fields, (value) => {
    fields.value = [...value];
    value.forEach((field) => {
        edits[field.id] = skkFieldFromApi(field);
    });

    if (value.length < COLLAPSE_THRESHOLD) {
        isExpanded.value = false;
    }
}, { immediate: true, deep: true });

const addForm = useForm(emptySkkField(props.section.entity_type));

function submitAdd() {
    addForm
        .transform((data) => ({
            entity_type: props.section.entity_type,
            ...skkFieldToPayload(data),
        }))
        .post(props.storeUrl, {
            preserveScroll: true,
            onSuccess: () => {
                addForm.reset();
                addForm.entity_type = props.section.entity_type;
                addForm.value_type = 'text';
                addForm.show_in_card = true;
                showAddForm.value = false;
            },
        });
}

function saveField(field) {
    router.put(field.update_url, {
        editing_skk_field_id: field.id,
        ...skkFieldToPayload(edits[field.id]),
    }, { preserveScroll: true });
}

async function deleteField(field) {
    const confirmed = await confirmDialog({
        title: 'Удалить поле?',
        message: `Поле «${field.name}» будет удалено без возможности восстановления.`,
    });

    if (!confirmed) {
        return;
    }

    router.delete(field.destroy_url, { preserveScroll: true });
}

async function deleteAllFields() {
    const count = fields.value.length;

    const confirmed = await confirmDialog({
        title: `Удалить все поля раздела «${props.section.label}»?`,
        message: `Будут удалены все поля (${count} шт.) в разделе «${props.section.label}». Это действие нельзя отменить.`,
    });

    if (!confirmed) {
        return;
    }

    router.delete(props.section.bulk_destroy_url, { preserveScroll: true });
}

function onFieldDeleted(id) {
    fields.value = fields.value.filter((field) => field.id !== id);
    delete edits[id];
}

function toggleList() {
    isExpanded.value = !isExpanded.value;
}
</script>

<template>
    <div
        class="min-w-0 rounded-xl border border-f5-border bg-f5-surface p-4 dark:border-white/10 dark:bg-f5-black"
        :class="{ 'lg:col-span-3': showAddForm || openFieldId !== null }"
    >
        <header class="mb-4 space-y-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h4 class="text-sm font-semibold text-f5-text dark:text-white">
                    {{ section.label }}
                    <span class="ml-1 font-normal text-f5-text-muted dark:text-white/45">
                        ({{ fields.length }})
                    </span>
                </h4>
            </div>
            <div v-if="canEdit" class="flex flex-wrap gap-2">
                <button
                    v-if="fields.length > 0"
                    type="button"
                    class="f5-btn f5-btn--danger f5-btn--sm"
                    @click="deleteAllFields"
                >
                    Удалить все
                </button>
                <button
                    v-if="hasLibrary"
                    type="button"
                    class="f5-btn f5-btn--ghost f5-btn--sm"
                    @click="emit('open-library')"
                >
                    Из библиотеки
                </button>
                <button type="button" class="f5-btn f5-btn--primary f5-btn--sm" @click="showAddForm = !showAddForm">
                    {{ showAddForm ? 'Скрыть форму' : 'Добавить поле' }}
                </button>
            </div>
        </header>

        <div
            v-if="canEdit && showAddForm"
            class="mb-4 max-w-3xl overflow-hidden rounded-xl border border-f5-border bg-f5-surface-2 p-4 dark:border-white/10 dark:bg-white/5 sm:p-5"
        >
            <form class="space-y-4" @submit.prevent="submitAdd">
                <SkkFieldForm
                    v-model="addForm"
                    :funnel-stages="funnelStages"
                    :errors="addForm.errors"
                />
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="f5-btn f5-btn--primary f5-btn--sm" :disabled="addForm.processing">
                        Добавить
                    </button>
                    <button
                        type="button"
                        class="f5-btn f5-btn--ghost f5-btn--sm"
                        @click="showAddForm = false"
                    >
                        Отмена
                    </button>
                </div>
            </form>
        </div>

        <SectionEmptyState
            v-if="fields.length === 0 && !showAddForm"
            compact
            title="Поля не добавлены"
            :description="canEdit ? 'Вручную или из библиотеки.' : ''"
            :action-label="canEdit ? 'Добавить' : ''"
            @action="showAddForm = true"
        />

        <button
            v-else-if="isCollapsible && isListCollapsed"
            type="button"
            class="mb-3 flex w-full items-center gap-2 rounded-lg border border-f5-border bg-f5-surface-2 px-3 py-3 text-left text-sm transition-colors hover:bg-f5-surface-muted dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/10"
            @click="toggleList"
        >
            <span
                class="shrink-0 text-f5-text-muted transition-transform dark:text-white/40"
                aria-hidden="true"
            >▶</span>
            <span class="min-w-0 flex-1 text-f5-text dark:text-white">
                <span class="font-medium">{{ fields.length }} {{ fields.length === 1 ? 'поле' : fields.length < 5 ? 'поля' : 'полей' }}</span>
                <span class="text-f5-text-muted dark:text-white/50"> — {{ previewFieldNames }}</span>
                <span v-if="hiddenFieldsCount > 0" class="text-f5-text-muted dark:text-white/50">
                    и ещё {{ hiddenFieldsCount }}
                </span>
            </span>
            <span class="shrink-0 text-xs text-f5-accent">Показать</span>
        </button>

        <div v-if="fields.length > 0 && (!isCollapsible || !isListCollapsed)">
            <button
                v-if="isCollapsible"
                type="button"
                class="mb-3 flex w-full items-center justify-center gap-2 rounded-lg border border-dashed border-f5-border px-3 py-2 text-xs text-f5-text-muted transition-colors hover:border-f5-accent hover:text-f5-accent dark:border-white/10 dark:text-white/50"
                @click="toggleList"
            >
                <span aria-hidden="true">▼</span>
                Свернуть список
            </button>

            <SortableList
                v-if="canEdit"
                :reorder-url="reorderUrl"
                :entity-type="section.entity_type"
                drag-delete
                @deleted="onFieldDeleted"
            >
                <SortableBlock
                    v-for="field in fields"
                    :key="field.id"
                    :id="field.id"
                    :name="field.name"
                    :summary="skkFieldSummary(field)"
                    :destroy-url="field.destroy_url"
                    :open="openFieldId === field.id"
                    @toggle="openFieldId = openFieldId === field.id ? null : field.id"
                >
                    <form class="space-y-4 pt-4" @submit.prevent="saveField(field)">
                        <SkkFieldForm
                            v-model="edits[field.id]"
                            :funnel-stages="funnelStages"
                        />
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" class="f5-btn f5-btn--primary f5-btn--sm">
                                Сохранить
                            </button>
                            <button type="button" class="f5-btn f5-btn--danger f5-btn--sm" @click="deleteField(field)">
                                Удалить
                            </button>
                        </div>
                    </form>
                </SortableBlock>
            </SortableList>

            <ul v-else class="divide-y divide-f5-border dark:divide-white/10">
                <li
                    v-for="field in fields"
                    :key="field.id"
                    class="px-1 py-3 text-sm"
                >
                    <span class="font-semibold text-f5-text dark:text-white">{{ field.name }}</span>
                    <span
                        v-if="skkFieldSummary(field)"
                        class="mt-0.5 block text-xs text-f5-text-muted dark:text-white/50"
                    >{{ skkFieldSummary(field) }}</span>
                </li>
            </ul>
        </div>
    </div>
</template>
