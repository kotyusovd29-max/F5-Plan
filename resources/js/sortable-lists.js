import Sortable from 'sortablejs';
import {
    hideDragTrash,
    initDragTrash,
    isDragTrashActive,
    isPointerOverDragTrash,
    showDragTrash,
    updateDragTrashState,
} from './drag-trash';

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function saveOrder(list) {
    const url = list.dataset.reorderUrl;

    if (!url) {
        return;
    }

    const order = [...list.querySelectorAll('.sortable-item')]
        .map((item) => item.dataset.sortableId)
        .filter(Boolean);

    if (order.length === 0) {
        return;
    }

    const body = { order: order.map(Number) };

    if (list.dataset.entityType) {
        body.entity_type = list.dataset.entityType;
    }

    const response = await fetch(url, {
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

function handleEmptyList(list) {
    if (list.querySelectorAll('.sortable-item').length > 0) {
        return;
    }

    const emptyText = list.dataset.emptyText || 'Элементов нет.';
    const empty = document.createElement('p');
    empty.className = list.dataset.emptyClass || 'workspace-card__empty';
    empty.textContent = emptyText;
    list.insertAdjacentElement('afterend', empty);
    list.remove();
}

async function deleteSortableItem(item, list, oldIndex) {
    const url = item.dataset.dragDeleteUrl;
    const label = item.dataset.dragDeleteLabel || 'элемент';

    if (!url) {
        return false;
    }

    if (!window.confirm(`Удалить «${label}»?`)) {
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

        item.remove();
        handleEmptyList(list);
        return true;
    } catch {
        restoreSortableItem(list, item, oldIndex);
        list.classList.add('sortable-list--error');
        window.setTimeout(() => list.classList.remove('sortable-list--error'), 1200);
        return false;
    }
}

export function initSortableLists() {
    initDragTrash();

    document.querySelectorAll('[data-sortable-list]').forEach((list) => {
        const direction = list.dataset.sortableDirection === 'horizontal' ? 'horizontal' : 'vertical';
        let dragMoveListener = null;

        Sortable.create(list, {
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
                if (list.dataset.dragDelete !== '1') {
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
                const shouldDelete = list.dataset.dragDelete === '1'
                    && item.dataset.dragDeleteUrl
                    && (
                        isDragTrashActive()
                        || (dropX != null && dropY != null && isPointerOverDragTrash(dropX, dropY))
                    );

                hideDragTrash();

                if (shouldDelete) {
                    await deleteSortableItem(item, list, event.oldIndex);
                    return;
                }

                saveOrder(list);
            },
        });
    });
}
