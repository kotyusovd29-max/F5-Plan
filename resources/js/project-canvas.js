function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function getFormActionUrl(form) {
    return form.getAttribute('action') || '';
}

function createCanvasController(root) {
    const viewport = root.querySelector('#canvas-viewport');
    const surface = root.querySelector('#canvas-surface');
    const saveUrl = root.dataset.saveUrl;
    const refreshUrl = root.dataset.refreshUrl;
    const statusEl = root.querySelector('[data-canvas-status]');
    const zoomLabel = root.querySelector('[data-canvas-zoom-label]');
    const titleEl = root.querySelector('[data-canvas-project-title]');

    let offsetX = Number(root.dataset.offsetX) || 0;
    let offsetY = Number(root.dataset.offsetY) || 0;
    let zoom = Number(root.dataset.zoom) || 1;

    let panning = false;
    let panStart = { x: 0, y: 0, ox: 0, oy: 0 };
    let draggingBlock = null;
    let dragStart = { x: 0, y: 0, left: 0, top: 0 };
    let saveTimer = null;
    let maxZIndex = 1;
    const trashEl = root.querySelector('#canvas-trash');
    let trashActive = false;

    function isDeletableBlock(block) {
        return block?.dataset.canvasDeletable === '1';
    }

    function showTrash() {
        trashEl?.classList.remove('is-hidden');
        trashEl?.setAttribute('aria-hidden', 'false');
    }

    function hideTrash() {
        trashEl?.classList.remove('is-active');
        trashEl?.classList.add('is-hidden');
        trashEl?.setAttribute('aria-hidden', 'true');
        trashActive = false;
    }

    function isPointerOverTrash(clientX, clientY) {
        if (!trashEl || trashEl.classList.contains('is-hidden')) {
            return false;
        }

        const rect = trashEl.getBoundingClientRect();

        return clientX >= rect.left
            && clientX <= rect.right
            && clientY >= rect.top
            && clientY <= rect.bottom;
    }

    function updateTrashState(clientX, clientY) {
        if (!trashEl || trashEl.classList.contains('is-hidden')) {
            return;
        }

        trashActive = isPointerOverTrash(clientX, clientY);
        trashEl.classList.toggle('is-active', trashActive);
    }

    async function deleteDraggedBlock(block) {
        const deleteUrl = block.dataset.canvasDeleteUrl;
        const label = block.dataset.canvasDeleteLabel || 'блок';

        if (!deleteUrl) {
            return;
        }

        if (!window.confirm(`Удалить «${label}»?`)) {
            return;
        }

        setStatus('Удаление...', 'saving');

        try {
            const response = await fetch(deleteUrl, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Canvas-Request': '1',
                },
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'delete failed');
            }

            await refreshBlocks();
            setStatus(data.message || 'Удалено', 'saved');
        } catch (error) {
            setStatus(error.message || 'Ошибка', 'error');
        }
    }

    function recalcMaxZIndex() {
        maxZIndex = Math.max(
            1,
            ...[...root.querySelectorAll('.canvas-block')].map((block) => Number(block.dataset.zIndex) || 1)
        );
    }

    function applyTransform() {
        surface.style.transform = `translate(${offsetX}px, ${offsetY}px) scale(${zoom})`;

        if (zoomLabel) {
            zoomLabel.textContent = `${Math.round(zoom * 100)}%`;
        }
    }

    function setStatus(text, state = 'saved') {
        if (!statusEl) {
            return;
        }

        statusEl.textContent = text;
        statusEl.classList.remove('is-saving', 'is-error');

        if (state === 'saving') {
            statusEl.classList.add('is-saving');
        }

        if (state === 'error') {
            statusEl.classList.add('is-error');
        }
    }

    function collectBlocks() {
        return [...root.querySelectorAll('.canvas-block')].map((block) => ({
            id: Number(block.dataset.blockId),
            pos_x: Math.round(parseFloat(block.style.left) || 0),
            pos_y: Math.round(parseFloat(block.style.top) || 0),
            z_index: Number(block.style.zIndex) || Number(block.dataset.zIndex) || 1,
        }));
    }

    function scheduleLayoutSave() {
        if (!saveUrl) {
            return;
        }

        setStatus('Сохранение...', 'saving');

        if (saveTimer) {
            window.clearTimeout(saveTimer);
        }

        saveTimer = window.setTimeout(async () => {
            try {
                const response = await fetch(saveUrl, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                    },
                    body: JSON.stringify({
                        blocks: collectBlocks(),
                        viewport: {
                            offset_x: Math.round(offsetX),
                            offset_y: Math.round(offsetY),
                            zoom: Math.round(zoom * 100) / 100,
                        },
                    }),
                });

                if (!response.ok) {
                    throw new Error('save failed');
                }

                setStatus('Сохранено', 'saved');
            } catch {
                setStatus('Ошибка', 'error');
            }
        }, 450);
    }

    function setZoom(nextZoom, anchorX = null, anchorY = null) {
        const clamped = Math.min(2, Math.max(0.25, nextZoom));

        if (anchorX !== null && anchorY !== null) {
            const rect = viewport.getBoundingClientRect();
            const mx = anchorX - rect.left;
            const my = anchorY - rect.top;
            const wx = (mx - offsetX) / zoom;
            const wy = (my - offsetY) / zoom;
            offsetX = mx - wx * clamped;
            offsetY = my - wy * clamped;
        }

        zoom = clamped;
        applyTransform();
        scheduleLayoutSave();
    }

    function isPanTarget(target) {
        if (target.closest('.canvas-block')) {
            return false;
        }

        return target === viewport || target === surface;
    }

    function clearFormErrors(form) {
        form.querySelectorAll('.form__error').forEach((error) => error.remove());
        form.querySelectorAll('.form__input--error, .form__textarea--error').forEach((input) => {
            input.classList.remove('form__input--error', 'form__textarea--error');
        });
    }

    function showFormErrors(form, errors) {
        Object.entries(errors || {}).forEach(([field, messages]) => {
            const input = form.querySelector(`[name="${field}"]`);

            if (!input) {
                return;
            }

            input.classList.add(input.tagName === 'TEXTAREA' ? 'form__textarea--error' : 'form__input--error');

            const error = document.createElement('span');
            error.className = 'form__error';
            error.textContent = messages[0];
            input.insertAdjacentElement('afterend', error);
        });
    }

    async function refreshBlocks() {
        if (!refreshUrl) {
            return;
        }

        setStatus('Обновление...', 'saving');

        const response = await fetch(refreshUrl, {
            headers: {
                'Accept': 'application/json',
                'X-Canvas-Request': '1',
            },
        });

        if (!response.ok) {
            setStatus('Ошибка', 'error');
            throw new Error('refresh failed');
        }

        const data = await response.json();
        surface.innerHTML = data.html;

        if (titleEl && data.title) {
            titleEl.textContent = data.title;
        }

        recalcMaxZIndex();
        bindBlockInteractions();
        setStatus('Сохранено', 'saved');
    }

    async function submitCanvasForm(form) {
        const confirmMessage = form.dataset.canvasConfirm;

        if (confirmMessage && !window.confirm(confirmMessage)) {
            return;
        }

        clearFormErrors(form);
        setStatus('Сохранение...', 'saving');

        const formData = new FormData(form);

        try {
            const response = await fetch(getFormActionUrl(form), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Canvas-Request': '1',
                },
                body: formData,
            });

            if (response.status === 422) {
                const data = await response.json();
                showFormErrors(form, data.errors);
                setStatus('Проверьте поля', 'error');
                return;
            }

            if (!response.ok) {
                throw new Error('submit failed');
            }

            const data = await response.json();
            await refreshBlocks();
            setStatus(data.message || 'Сохранено', 'saved');
        } catch {
            setStatus('Ошибка', 'error');
        }
    }

    function bindBlockInteractions() {
        root.querySelectorAll('[data-canvas-drag-handle]').forEach((handle) => {
            handle.onmousedown = (event) => {
                if (event.button !== 0) {
                    return;
                }

                const block = handle.closest('.canvas-block');

                if (!block) {
                    return;
                }

                draggingBlock = block;
                maxZIndex += 1;
                block.style.zIndex = String(maxZIndex);
                block.dataset.zIndex = String(maxZIndex);
                block.classList.add('is-dragging');

                if (isDeletableBlock(block)) {
                    showTrash();
                }

                dragStart = {
                    x: event.clientX,
                    y: event.clientY,
                    left: parseFloat(block.style.left) || 0,
                    top: parseFloat(block.style.top) || 0,
                };

                event.preventDefault();
                event.stopPropagation();
            };
        });

        root.querySelectorAll('.canvas-block').forEach((block) => {
            block.onwheel = (event) => {
                event.stopPropagation();
            };
        });

        root.querySelectorAll('[data-canvas-item-toggle]').forEach((toggle) => {
            toggle.onclick = (event) => {
                event.preventDefault();
                event.stopPropagation();

                const item = toggle.closest('[data-canvas-item]');
                const panel = item?.querySelector('.canvas-item__panel');

                if (!panel) {
                    return;
                }

                const isHidden = panel.classList.toggle('is-hidden');
                item.classList.toggle('canvas-item--open', !isHidden);
            };
        });

        root.querySelectorAll('[data-canvas-form]').forEach((form) => {
            form.onsubmit = (event) => {
                event.preventDefault();
                submitCanvasForm(form);
            };
        });
    }

    viewport.onmousedown = (event) => {
        if (event.button !== 0 && event.button !== 1) {
            return;
        }

        if (!isPanTarget(event.target)) {
            return;
        }

        panning = true;
        panStart = {
            x: event.clientX,
            y: event.clientY,
            ox: offsetX,
            oy: offsetY,
        };
        viewport.classList.add('is-panning');
        event.preventDefault();
    };

    window.onmousemove = (event) => {
        if (panning) {
            offsetX = panStart.ox + (event.clientX - panStart.x);
            offsetY = panStart.oy + (event.clientY - panStart.y);
            applyTransform();
            return;
        }

        if (!draggingBlock) {
            return;
        }

        const dx = (event.clientX - dragStart.x) / zoom;
        const dy = (event.clientY - dragStart.y) / zoom;

        draggingBlock.style.left = `${Math.max(0, dragStart.left + dx)}px`;
        draggingBlock.style.top = `${Math.max(0, dragStart.top + dy)}px`;
        updateTrashState(event.clientX, event.clientY);
    };

    window.onmouseup = async (event) => {
        if (panning) {
            panning = false;
            viewport.classList.remove('is-panning');
            scheduleLayoutSave();
        }

        if (draggingBlock) {
            const block = draggingBlock;
            const shouldDelete = isDeletableBlock(block) && trashActive;

            block.classList.remove('is-dragging');
            draggingBlock = null;
            hideTrash();

            if (shouldDelete) {
                await deleteDraggedBlock(block);
                return;
            }

            scheduleLayoutSave();
        }
    };

    viewport.onwheel = (event) => {
        event.preventDefault();
        const delta = event.deltaY > 0 ? -0.08 : 0.08;
        setZoom(zoom + delta, event.clientX, event.clientY);
    };

    root.querySelector('[data-canvas-zoom-in]')?.addEventListener('click', () => {
        const rect = viewport.getBoundingClientRect();
        setZoom(zoom + 0.1, rect.left + rect.width / 2, rect.top + rect.height / 2);
    });

    root.querySelector('[data-canvas-zoom-out]')?.addEventListener('click', () => {
        const rect = viewport.getBoundingClientRect();
        setZoom(zoom - 0.1, rect.left + rect.width / 2, rect.top + rect.height / 2);
    });

    root.querySelector('[data-canvas-zoom-reset]')?.addEventListener('click', () => {
        zoom = 1;
        offsetX = 0;
        offsetY = 0;
        applyTransform();
        scheduleLayoutSave();
    });

    recalcMaxZIndex();
    bindBlockInteractions();
    applyTransform();

    return { refreshBlocks };
}

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('project-canvas');

    if (root) {
        createCanvasController(root);
    }
});
