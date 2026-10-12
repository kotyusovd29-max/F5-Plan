import { onBeforeUnmount, onMounted } from 'vue';
import { confirmDialog } from '../utils/confirmDialog';
import Sortable from 'sortablejs';
import {
    hideDragTrash,
    initDragTrash,
    isDragTrashActive,
    isPointerOverDragTrash,
    showDragTrash,
    updateDragTrashState,
} from '../drag-trash';

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

export function useSortableList(listRef, options) {
    let sortable = null;
    let dragMoveListener = null;

    onMounted(() => {
        initDragTrash();

        if (!listRef.value) {
            return;
        }

        const list = listRef.value;
        const direction = options.direction === 'horizontal' ? 'horizontal' : 'vertical';

        sortable = Sortable.create(list, {
            draggable: '.sortable-item',
            handle: '.sortable-handle',
            animation: 150,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            direction,
            forceFallback: true,
            fallbackTolerance: 3,
            onStart() {
                if (!options.dragDelete) {
                    return;
                }

                showDragTrash();
                dragMoveListener = () => updateDragTrashState();
                document.addEventListener('mousemove', dragMoveListener);
            },
            onEnd: async (event) => {
                if (dragMoveListener) {
                    document.removeEventListener('mousemove', dragMoveListener);
                    dragMoveListener = null;
                }

                const item = event.item;
                const dropEvent = event.originalEvent;
                const dropX = dropEvent?.clientX;
                const dropY = dropEvent?.clientY;
                const shouldDelete = options.dragDelete
                    && item.dataset.dragDeleteUrl
                    && (
                        isDragTrashActive()
                        || (dropX != null && dropY != null && isPointerOverDragTrash(dropX, dropY))
                    );

                hideDragTrash();

                if (shouldDelete) {
                    await deleteSortableItem(item, list, event.oldIndex, options.onDeleted);
                    return;
                }

                await saveOrder(list, options.reorderUrl, options.entityType, options.onReordered);
            },
        });
    });

    onBeforeUnmount(() => {
        sortable?.destroy();
    });
}

async function saveOrder(list, reorderUrl, entityType, onReordered) {
    const order = [...list.querySelectorAll('.sortable-item')]
        .map((item) => Number(item.dataset.sortableId))
        .filter((id) => Number.isFinite(id) && id > 0);

    if (order.length === 0) {
        return;
    }

    onReordered?.(order);

    if (!reorderUrl) {
        return;
    }

    const body = { order };

    if (entityType) {
        body.entity_type = entityType;
    }

    const response = await fetch(reorderUrl, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        list.classList.add('sortable-list--error');
        window.setTimeout(() => list.classList.remove('sortable-list--error'), 1200);
    }
}

function restoreSortableItem(list, item, oldIndex) {
    const items = [...list.querySelectorAll('.sortable-item')].filter((node) => node !== item);

    if (oldIndex >= items.length) {
        list.appendChild(item);
        return;
    }

    list.insertBefore(item, items[oldIndex]);
}

async function deleteSortableItem(item, list, oldIndex, onDeleted) {
    const url = item.dataset.dragDeleteUrl;
    const label = item.dataset.dragDeleteLabel || 'элемент';

    if (!url) {
        return false;
    }

    const confirmed = await confirmDialog({
        title: 'Удалить?',
        message: `«${label}» будет удалено без возможности восстановления.`,
    });

    if (!confirmed) {
        restoreSortableItem(list, item, oldIndex);
        return false;
    }

    try {
        const response = await fetch(url, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok || data.success === false) {
            throw new Error(data.message || 'Не удалось удалить.');
        }

        const id = Number(item.dataset.sortableId);
        item.remove();
        onDeleted?.(id);
        return true;
    } catch {
        restoreSortableItem(list, item, oldIndex);
        list.classList.add('sortable-list--error');
        window.setTimeout(() => list.classList.remove('sortable-list--error'), 1200);
        return false;
    }
}
