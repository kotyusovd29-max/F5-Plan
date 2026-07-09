let trashEl = null;
let pointer = { x: 0, y: 0 };
let pointerListenerAttached = false;
let trashActive = false;

export function initDragTrash() {
    trashEl = document.getElementById('project-trash');

    if (!trashEl || pointerListenerAttached) {
        return;
    }

    pointerListenerAttached = true;

    document.addEventListener('mousemove', (event) => {
        pointer = { x: event.clientX, y: event.clientY };
    });
}

export function showDragTrash() {
    trashEl?.classList.remove('is-hidden');
    trashEl?.setAttribute('aria-hidden', 'false');
}

export function hideDragTrash() {
    trashEl?.classList.remove('is-active');
    trashEl?.classList.add('is-hidden');
    trashEl?.setAttribute('aria-hidden', 'true');
    trashActive = false;
}

export function isDragTrashActive() {
    return trashActive;
}

export function isPointerOverDragTrash(clientX = pointer.x, clientY = pointer.y) {
    if (!trashEl || trashEl.classList.contains('is-hidden')) {
        return false;
    }

    const rect = trashEl.getBoundingClientRect();

    return clientX >= rect.left
        && clientX <= rect.right
        && clientY >= rect.top
        && clientY <= rect.bottom;
}

export function updateDragTrashState(clientX = pointer.x, clientY = pointer.y) {
    if (!trashEl || trashEl.classList.contains('is-hidden')) {
        return;
    }

    trashActive = isPointerOverDragTrash(clientX, clientY);
    trashEl.classList.toggle('is-active', trashActive);
}
