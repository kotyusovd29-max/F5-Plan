import { onBeforeUnmount, onMounted } from 'vue';

export function useDragScroll(containerRef, options = {}) {
    const ignoreSelector = options.ignoreSelector
        ?? '.sortable-handle, button, a, input, textarea, select, label, .stage-block__edit';

    let isDragging = false;
    let startX = 0;
    let scrollLeft = 0;
    let moved = false;
    let suppressClick = false;

    function getContainer() {
        return containerRef.value;
    }

    function shouldIgnore(target) {
        if (!(target instanceof Element)) {
            return true;
        }

        return Boolean(target.closest(ignoreSelector));
    }

    function onMouseDown(event) {
        if (event.button !== 0) {
            return;
        }

        const container = getContainer();

        if (!container || shouldIgnore(event.target)) {
            return;
        }

        isDragging = true;
        moved = false;
        startX = event.pageX;
        scrollLeft = container.scrollLeft;
        container.classList.add('is-drag-scrolling');
    }

    function onMouseMove(event) {
        if (!isDragging) {
            return;
        }

        const container = getContainer();

        if (!container) {
            return;
        }

        const walk = event.pageX - startX;

        if (Math.abs(walk) > 4) {
            moved = true;
            event.preventDefault();
        }

        container.scrollLeft = scrollLeft - walk;
    }

    function stopDragging() {
        if (!isDragging) {
            return;
        }

        isDragging = false;
        getContainer()?.classList.remove('is-drag-scrolling');

        if (moved) {
            suppressClick = true;
            window.setTimeout(() => {
                suppressClick = false;
            }, 0);
        }
    }

    function onClickCapture(event) {
        if (!suppressClick) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
    }

    onMounted(() => {
        const container = getContainer();

        if (!container) {
            return;
        }

        container.addEventListener('mousedown', onMouseDown);
        container.addEventListener('click', onClickCapture, true);
        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', stopDragging);
    });

    onBeforeUnmount(() => {
        const container = getContainer();

        container?.removeEventListener('mousedown', onMouseDown);
        container?.removeEventListener('click', onClickCapture, true);
        document.removeEventListener('mousemove', onMouseMove);
        document.removeEventListener('mouseup', stopDragging);
    });
}
