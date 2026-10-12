<script setup>
import { reactive, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import SortableList from './SortableList.vue';
import SortableBlock from './SortableBlock.vue';
import IntegrationWidgetPicker from './IntegrationWidgetPicker.vue';
import IntegrationsCostSummary from './IntegrationsCostSummary.vue';
import {
    emptyIntegration,
    integrationFromApi,
    integrationToPayload,
} from '../../utils/integrationWidget';
import { confirmDialog } from '../../utils/confirmDialog';
import SectionEmptyState from './SectionEmptyState.vue';

const props = defineProps({
    integrations: {
        type: Array,
        required: true,
    },
    catalog: {
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
    employeesCount: {
        type: [Number, String],
        default: null,
    },
    projectId: {
        type: [Number, String],
        required: true,
    },
    canEdit: {
        type: Boolean,
        default: true,
    },
});

const items = ref([...props.integrations]);
const showAddForm = ref(false);

defineExpose({
    openAdd: () => {
        if (!props.canEdit) {
            return;
        }

        showAddForm.value = true;
    },
});
const openId = ref(null);
const edits = reactive({});

watch(() => props.integrations, (value) => {
    items.value = [...value];
    value.forEach((item) => {
        edits[item.id] = integrationFromApi(item);
    });
}, { immediate: true, deep: true });

const addForm = useForm({
    form_type: 'new_integration',
    ...emptyIntegration(),
});

function submitAdd() {
    addForm.transform((data) => ({
        ...integrationToPayload(data),
        form_type: data.form_type,
    })).post(props.storeUrl, {
        preserveScroll: true,
        onSuccess: () => {
            addForm.reset();
            Object.assign(addForm, emptyIntegration());
            showAddForm.value = false;
        },
    });
}

function saveItem(item) {
    router.put(item.update_url, integrationToPayload(edits[item.id]), { preserveScroll: true });
}

async function deleteItem(item) {
    const confirmed = await confirmDialog({
        title: 'Удалить интеграцию?',
        message: `«${item.name}» будет удалено без возможности восстановления.`,
    });

    if (!confirmed) {
        return;
    }

    router.delete(item.destroy_url, { preserveScroll: true });
}

function onDeleted(id) {
    items.value = items.value.filter((item) => item.id !== id);
    delete edits[id];
}

function cancelAdd() {
    addForm.clearErrors();
    addForm.reset();
    Object.assign(addForm, emptyIntegration());
    showAddForm.value = false;
}
</script>

<template>
    <div>
        <IntegrationsCostSummary
            v-if="items.length > 0"
            :project-id="projectId"
            :integrations="items"
            :catalog="catalog"
            :employees-count="employeesCount"
        />

        <div v-if="canEdit && showAddForm" class="border-b border-f5-border p-5 dark:border-white/10">
            <form class="mx-auto max-w-3xl space-y-4" @submit.prevent="submitAdd">
                <IntegrationWidgetPicker
                    v-model="addForm"
                    :catalog="catalog"
                    :errors="addForm.errors"
                />
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="f5-btn f5-btn--primary" :disabled="addForm.processing">
                        Добавить
                    </button>
                    <button
                        type="button"
                        class="f5-btn f5-btn--ghost"
                        :disabled="addForm.processing"
                        @click="cancelAdd"
                    >
                        Отмена
                    </button>
                </div>
            </form>
        </div>

        <SectionEmptyState
            v-if="items.length === 0 && !showAddForm"
            title="Интеграций пока нет"
            description="Добавьте виджеты F5 или свой сервис с подпиской или разовой оплатой."
            :action-label="canEdit ? 'Добавить виджет' : ''"
            @action="showAddForm = true"
        >
            <template #icon>
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6">
                    <rect x="3.5" y="3.5" width="5" height="5" rx="1.2" />
                    <rect x="11.5" y="3.5" width="5" height="5" rx="1.2" />
                    <rect x="3.5" y="11.5" width="5" height="5" rx="1.2" />
                    <path d="M12.5 14h3M14 12.5v3" stroke-linecap="round" />
                </svg>
            </template>
        </SectionEmptyState>

        <SortableList
            v-else-if="canEdit && items.length > 0"
            :reorder-url="reorderUrl"
            drag-delete
            class="border-t border-f5-border dark:border-white/10"
            @deleted="onDeleted"
        >
            <SortableBlock
                v-for="item in items"
                :key="item.id"
                :id="item.id"
                :name="item.name"
                :comment="item.comment"
                :destroy-url="item.destroy_url"
                :open="openId === item.id"
                @toggle="openId = openId === item.id ? null : item.id"
            >
                <form class="space-y-4 pt-4" @submit.prevent="saveItem(item)">
                    <IntegrationWidgetPicker
                        v-model="edits[item.id]"
                        :catalog="catalog"
                    />
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="f5-btn f5-btn--primary f5-btn--sm">
                            Сохранить
                        </button>
                        <button type="button" class="f5-btn f5-btn--danger f5-btn--sm" @click="deleteItem(item)">
                            Удалить
                        </button>
                    </div>
                </form>
            </SortableBlock>
        </SortableList>

        <ul
            v-else-if="items.length > 0"
            class="divide-y divide-f5-border border-t border-f5-border dark:divide-white/10 dark:border-white/10"
        >
            <li
                v-for="item in items"
                :key="item.id"
                class="px-6 py-3.5 text-sm"
            >
                <span class="font-semibold text-f5-text dark:text-white">{{ item.name }}</span>
                <span
                    v-if="item.is_custom && item.cost != null"
                    class="mt-0.5 block text-xs text-f5-text-muted dark:text-white/50"
                >
                    {{ item.payment_type === 'one_time' ? 'Единовременно' : 'Подписка' }}:
                    {{ Number(item.cost).toLocaleString('ru-RU') }} ₽{{ item.payment_type === 'one_time' ? '' : '/мес' }}
                </span>
                <span
                    v-if="item.comment"
                    class="mt-0.5 block text-xs text-f5-text-muted dark:text-white/50"
                >{{ item.comment }}</span>
            </li>
        </ul>
    </div>
</template>
