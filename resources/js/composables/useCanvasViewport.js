import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

export function useCanvasViewport(initialViewport, saveUrl, onSavePayload) {
    const viewportRef = ref(null);
    const offsetX = ref(initialViewport?.offset_x ?? 0);
    const offsetY = ref(initialViewport?.offset_y ?? 0);
    const zoom = ref(initialViewport?.zoom ?? 1);
    const isPanning = ref(false);
    const status = ref({ text: 'Сохранено', state: 'saved' });

    let panStart = null;
    let saveTimer = null;

    const surfaceStyle = computed(() => {
        // Integer pan + 2-decimal zoom keep text sharper under CSS scale.
        const x = Math.round(offsetX.value);
        const y = Math.round(offsetY.value);
        const z = Math.round(zoom.value * 100) / 100;

        return {
            transform: `translate(${x}px, ${y}px) scale(${z})`,
        };
    });

    const zoomLabel = computed(() => `${Math.round(zoom.value * 100)}%`);

    function setStatus(text, state = 'saved') {
        status.value = { text, state };
    }

    function viewportPayload() {
        return {
            offset_x: Math.round(offsetX.value),
            offset_y: Math.round(offsetY.value),
            zoom: Math.round(zoom.value * 100) / 100,
        };
    }

    function scheduleSave() {
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
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                    },
                    body: JSON.stringify(onSavePayload()),
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
        const viewport = viewportRef.value;

        if (anchorX !== null && anchorY !== null && viewport) {
            const rect = viewport.getBoundingClientRect();
            const mx = anchorX - rect.left;
            const my = anchorY - rect.top;
            const wx = (mx - offsetX.value) / zoom.value;
            const wy = (my - offsetY.value) / zoom.value;
            offsetX.value = mx - wx * clamped;
            offsetY.value = my - wy * clamped;
        }

        zoom.value = Math.round(clamped * 100) / 100;
        scheduleSave();
    }

    function zoomIn() {
        const viewport = viewportRef.value;

        if (!viewport) {
            setZoom(zoom.value + 0.1);
            return;
        }

        const rect = viewport.getBoundingClientRect();
        setZoom(zoom.value + 0.1, rect.left + rect.width / 2, rect.top + rect.height / 2);
    }

    function zoomOut() {
        const viewport = viewportRef.value;

        if (!viewport) {
            setZoom(zoom.value - 0.1);
            return;
        }

        const rect = viewport.getBoundingClientRect();
        setZoom(zoom.value - 0.1, rect.left + rect.width / 2, rect.top + rect.height / 2);
    }

    function zoomReset() {
        zoom.value = 1;
        offsetX.value = 0;
        offsetY.value = 0;
        scheduleSave();
    }

    function onWheel(event) {
        event.preventDefault();
        const delta = event.deltaY > 0 ? -0.08 : 0.08;
        setZoom(zoom.value + delta, event.clientX, event.clientY);
    }

    function startPan(event) {
        isPanning.value = true;
        panStart = {
            x: event.clientX,
            y: event.clientY,
            ox: offsetX.value,
            oy: offsetY.value,
        };
        viewportRef.value?.classList.add('is-panning');
    }

    function onPanMove(event) {
        if (!isPanning.value || !panStart) {
            return;
        }

        offsetX.value = panStart.ox + (event.clientX - panStart.x);
        offsetY.value = panStart.oy + (event.clientY - panStart.y);
    }

    function endPan() {
        if (!isPanning.value) {
            return;
        }

        offsetX.value = Math.round(offsetX.value);
        offsetY.value = Math.round(offsetY.value);
        isPanning.value = false;
        panStart = null;
        viewportRef.value?.classList.remove('is-panning');
        scheduleSave();
    }

    function onViewportMouseDown(event, isPanTarget) {
        if (event.button !== 0 && event.button !== 1) {
            return;
        }

        if (!isPanTarget(event.target)) {
            return;
        }

        startPan(event);
        event.preventDefault();
    }

    onMounted(() => {
        document.addEventListener('mousemove', onPanMove);
        document.addEventListener('mouseup', endPan);
    });

    onBeforeUnmount(() => {
        document.removeEventListener('mousemove', onPanMove);
        document.removeEventListener('mouseup', endPan);

        if (saveTimer) {
            window.clearTimeout(saveTimer);
        }
    });

    return {
        viewportRef,
        surfaceStyle,
        zoomLabel,
        status,
        isPanning,
        zoom,
        offsetX,
        offsetY,
        setStatus,
        scheduleSave,
        viewportPayload,
        onWheel,
        onViewportMouseDown,
        zoomIn,
        zoomOut,
        zoomReset,
    };
}
