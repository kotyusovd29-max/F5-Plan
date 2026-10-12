<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import CanvasLayout from '../../Layouts/CanvasLayout.vue';
import CanvasToolbar from '../../Components/Canvas/CanvasToolbar.vue';
import CanvasTrash from '../../Components/Canvas/CanvasTrash.vue';
import ProjectCanvasBlock from '../../Components/Canvas/ProjectCanvasBlock.vue';
import { useCanvasViewport } from '../../composables/useCanvasViewport';
import { deleteCanvasBlock, submitCanvasForm } from '../../utils/canvasForm';
import { confirmDialog } from '../../utils/confirmDialog';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
    funnelStages: {
        type: Array,
        default: () => [],
    },
    f5WidgetCatalog: {
        type: Object,
        default: () => ({ categories: {}, widgets: [] }),
    },
    aiPrompts: {
        type: Object,
        default: () => ({ analysis: null, actions: [] }),
    },
    blocks: {
        type: Array,
        default: () => [],
    },
    viewport: {
        type: Object,
        default: () => ({}),
    },
    urls: {
        type: Object,
        required: true,
    },
});

const canEdit = computed(() => props.project.can_edit !== false);
const localBlocks = ref(props.blocks.map((block) => ({ ...block })));
const draggingBlockId = ref(null);
const showTrash = ref(false);
const trashActive = ref(false);
const maxZIndex = ref(Math.max(1, ...props.blocks.map((block) => block.z_index ?? 1)));

let dragStart = null;

const funnelForm = useForm({ name: '' });

const {
    viewportRef,
    surfaceStyle,
    zoomLabel,
    status,
    zoom,
    setStatus,
    scheduleSave,
    viewportPayload,
    onWheel,
    onViewportMouseDown,
    zoomIn,
    zoomOut,
    zoomReset,
} = useCanvasViewport(props.viewport, canEdit.value ? props.urls.save : null, () => ({
    blocks: localBlocks.value.map((block) => ({
        id: block.id,
        pos_x: Math.round(block.pos_x),
        pos_y: Math.round(block.pos_y),
        z_index: block.z_index,
    })),
    viewport: viewportPayload(),
}));

watch(
    () => props.blocks,
    (blocks) => {
        localBlocks.value = blocks.map((block) => ({ ...block }));
        maxZIndex.value = Math.max(1, ...blocks.map((block) => block.z_index ?? 1));
    },
    { deep: true },
);

const draggingBlock = computed(() => (
    draggingBlockId.value
        ? localBlocks.value.find((block) => block.id === draggingBlockId.value)
        : null
));

function isPanTarget(target) {
    if (!(target instanceof Element)) {
        return false;
    }

    return target.classList.contains('canvas-viewport')
        || target.classList.contains('canvas-surface')
        || target === viewportRef.value;
}

function isPointerOverTrash(clientX, clientY) {
    const trash = document.querySelector('.canvas-trash:not(.is-hidden)');

    if (!trash) {
        return false;
    }

    const rect = trash.getBoundingClientRect();

    return clientX >= rect.left
        && clientX <= rect.right
        && clientY >= rect.top
        && clientY <= rect.bottom;
}

function updateTrashState(clientX, clientY) {
    trashActive.value = isPointerOverTrash(clientX, clientY);
}

function onBlockDragStart(blockId, event) {
    if (!canEdit.value) {
        return;
    }
    if (event.button !== 0) {
        return;
    }

    const block = localBlocks.value.find((item) => item.id === blockId);

    if (!block) {
        return;
    }

    draggingBlockId.value = blockId;
    maxZIndex.value += 1;
    block.z_index = maxZIndex.value;

    if (block.is_deletable) {
        showTrash.value = true;
    }

    dragStart = {
        x: event.clientX,
        y: event.clientY,
        left: block.pos_x,
        top: block.pos_y,
    };

    event.preventDefault();
    event.stopPropagation();
}

function onMouseMove(event) {
    if (!draggingBlockId.value || !dragStart) {
        return;
    }

    const block = localBlocks.value.find((item) => item.id === draggingBlockId.value);

    if (!block) {
        return;
    }

    const dx = (event.clientX - dragStart.x) / zoom.value;
    const dy = (event.clientY - dragStart.y) / zoom.value;

    block.pos_x = Math.round(Math.max(0, dragStart.left + dx));
    block.pos_y = Math.round(Math.max(0, dragStart.top + dy));
    updateTrashState(event.clientX, event.clientY);
}

async function onMouseUp(event) {
    if (!draggingBlockId.value) {
        return;
    }

    const block = draggingBlock.value;
    const shouldDelete = block?.is_deletable && trashActive.value;

    draggingBlockId.value = null;
    dragStart = null;
    showTrash.value = false;
    trashActive.value = false;

    if (shouldDelete && block?.destroy_url) {
        const confirmed = await confirmDialog({
            title: 'Удалить блок?',
            message: `«${block.title}» и все данные в нём будут удалены без возможности восстановления.`,
        });

        if (!confirmed) {
            return;
        }

        await deleteCanvasBlock(block.destroy_url, (text, state) => setStatus(text, state));
        return;
    }

    scheduleSave();
}

async function submitFunnel() {
    const result = await submitCanvasForm({
        url: props.project.urls.funnels_store,
        fields: { name: funnelForm.name },
        onStatus: (text, state) => setStatus(text, state),
        reloadOnly: ['project', 'blocks'],
    });

    if (result.ok) {
        funnelForm.reset();
    }
}

onMounted(() => {
    document.addEventListener('mousemove', onMouseMove);
    document.addEventListener('mouseup', onMouseUp);
});

onBeforeUnmount(() => {
    document.removeEventListener('mousemove', onMouseMove);
    document.removeEventListener('mouseup', onMouseUp);
});
</script>

<template>
    <CanvasLayout :title="`Доска — ${project.company_name}`">
        <CanvasToolbar
            :back-href="project.urls.show"
            back-label="← К проекту"
            :title="project.company_name"
            :zoom-label="zoomLabel"
            :status-text="status.text"
            :status-state="status.state"
            @zoom-in="zoomIn"
            @zoom-out="zoomOut"
            @zoom-reset="zoomReset"
        >
            <template #center>
                <form class="canvas-toolbar__add-form" @submit.prevent="submitFunnel">
                    <input
                        v-model="funnelForm.name"
                        type="text"
                        class="f5-input !w-44 !py-1.5 text-xs"
                        placeholder="Название воронки"
                        required
                    >
                    <button type="submit" class="f5-btn f5-btn--primary f5-btn--sm" :disabled="funnelForm.processing">
                        + Воронка
                    </button>
                </form>
            </template>
        </CanvasToolbar>

        <div
            ref="viewportRef"
            class="canvas-viewport"
            @mousedown="onViewportMouseDown($event, isPanTarget)"
            @wheel="onWheel"
        >
            <div class="canvas-surface" :style="surfaceStyle">
                <ProjectCanvasBlock
                    v-for="block in localBlocks"
                    :key="block.id"
                    :block="block"
                    :project="project"
                    :funnel-stages="funnelStages"
                    :widget-catalog="f5WidgetCatalog"
                    :is-dragging="draggingBlockId === block.id"
                    @drag-start="onBlockDragStart(block.id, $event)"
                />
            </div>
        </div>

        <CanvasTrash :visible="showTrash" :active="trashActive" />
    </CanvasLayout>
</template>
