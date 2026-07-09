function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function getFormActionUrl(form) {
    return form.getAttribute('action') || '';
}

function drawFunnelArrows(surface) {
    const svg = surface.querySelector('#funnel-canvas-arrows');

    if (!svg) {
        return;
    }

    const stages = [...surface.querySelectorAll('.funnel-stage-block')]
        .sort((a, b) => Number(a.dataset.sortOrder) - Number(b.dataset.sortOrder));

    const existingLines = svg.querySelectorAll('line');
    existingLines.forEach((line) => line.remove());

    for (let index = 0; index < stages.length - 1; index += 1) {
        const from = stages[index];
        const to = stages[index + 1];
        const x1 = from.offsetLeft + from.offsetWidth;
        const y1 = from.offsetTop + from.offsetHeight / 2;
        const x2 = to.offsetLeft;
        const y2 = to.offsetTop + to.offsetHeight / 2;

        const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        line.setAttribute('x1', String(x1));
        line.setAttribute('y1', String(y1));
        line.setAttribute('x2', String(x2 - 10));
        line.setAttribute('y2', String(y2));
        line.setAttribute('stroke', '#f97316');
        line.setAttribute('stroke-width', '2');
        line.setAttribute('marker-end', 'url(#funnel-arrowhead)');
        svg.appendChild(line);
    }
}

function createFunnelCanvasController(root) {
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

    function collectStages() {
        return [...surface.querySelectorAll('.funnel-stage-block')].map((block) => ({
            id: Number(block.dataset.stageId),
            canvas_pos_x: Math.round(parseFloat(block.style.left) || 0),
            canvas_pos_y: Math.round(parseFloat(block.style.top) || 0),
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
                        stages: collectStages(),
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
        if (target.closest('.funnel-stage-block')) {
            return false;
        }

        return target === viewport || target === surface || target.closest('#funnel-canvas-arrows');
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

    async function parseJsonResponse(response) {
        const contentType = response.headers.get('content-type') || '';

        if (!contentType.includes('application/json')) {
            throw new Error(`unexpected response: ${response.status}`);
        }

        return response.json();
    }

    async function refreshSurface() {
        if (!refreshUrl) {
            return false;
        }

        setStatus('Обновление...', 'saving');

        const response = await fetch(refreshUrl, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Canvas-Request': '1',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
        });

        if (response.status === 419) {
            setStatus('Обновите страницу', 'error');
            return false;
        }

        if (!response.ok) {
            setStatus(`Ошибка ${response.status}`, 'error');
            return false;
        }

        const data = await parseJsonResponse(response);
        surface.innerHTML = data.html;

        if (titleEl && data.title) {
            titleEl.textContent = data.title;
        }

        bindBlockInteractions();
        drawFunnelArrows(surface);
        setStatus('Сохранено', 'saved');

        return true;
    }

    async function submitCanvasForm(form) {
        const confirmMessage = form.dataset.canvasConfirm;

        if (confirmMessage && !window.confirm(confirmMessage)) {
            return;
        }

        clearFormErrors(form);
        setStatus('Сохранение...', 'saving');

        const formData = new FormData(form);
        const csrfToken = formData.get('_token') || getCsrfToken();

        if (!formData.get('_token') && csrfToken) {
            formData.set('_token', csrfToken);
        }

        let response;

        try {
            response = await fetch(getFormActionUrl(form), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Canvas-Request': '1',
                },
                body: formData,
            });
        } catch {
            setStatus('Сеть недоступна', 'error');
            return;
        }

        if (response.status === 419) {
            setStatus('Обновите страницу', 'error');
            return;
        }

        if (response.status === 422) {
            const data = await parseJsonResponse(response);
            showFormErrors(form, data.errors);
            setStatus('Проверьте поля', 'error');
            return;
        }

        if (!response.ok) {
            setStatus(`Ошибка ${response.status}`, 'error');
            return;
        }

        let data;

        try {
            data = await parseJsonResponse(response);
        } catch {
            setStatus('Некорректный ответ сервера', 'error');
            return;
        }

        const refreshed = await refreshSurface();

        if (form.dataset.canvasResetOnSuccess) {
            form.reset();
        }

        if (!refreshed) {
            setStatus(data.message || 'Этап создан', 'saved');
        }
    }

    function bindBlockInteractions() {
        surface.querySelectorAll('[data-canvas-drag-handle]').forEach((handle) => {
            handle.onmousedown = (event) => {
                if (event.button !== 0) {
                    return;
                }

                const block = handle.closest('.funnel-stage-block');

                if (!block) {
                    return;
                }

                draggingBlock = block;
                block.classList.add('is-dragging');

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

        surface.querySelectorAll('.funnel-stage-block').forEach((block) => {
            block.onwheel = (event) => {
                event.stopPropagation();
            };
        });

        surface.querySelectorAll('[data-canvas-item-toggle]').forEach((toggle) => {
            toggle.onclick = (event) => {
                event.preventDefault();
                event.stopPropagation();

                const block = toggle.closest('.funnel-stage-block');
                const panel = block?.querySelector('.canvas-item__panel');

                if (!panel) {
                    return;
                }

                const isHidden = panel.classList.toggle('is-hidden');
                block.classList.toggle('funnel-stage-block--open', !isHidden);
            };
        });

        root.querySelectorAll('[data-canvas-form]').forEach((form) => {
            form.onsubmit = (event) => {
                event.preventDefault();
                submitCanvasForm(form);
            };
        });

        drawFunnelArrows(surface);
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
        drawFunnelArrows(surface);
    };

    window.onmouseup = () => {
        if (panning) {
            panning = false;
            viewport.classList.remove('is-panning');
            scheduleLayoutSave();
        }

        if (draggingBlock) {
            draggingBlock.classList.remove('is-dragging');
            draggingBlock = null;
            scheduleLayoutSave();
            drawFunnelArrows(surface);
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

    bindBlockInteractions();
    applyTransform();
}

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('funnel-canvas');

    if (root) {
        createFunnelCanvasController(root);
    }
});
