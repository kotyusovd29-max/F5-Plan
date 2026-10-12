import { computed, ref, watch } from 'vue';

const STORAGE_KEY = 'f5-sidebar-autohide';

const autoHideEnabled = ref(
    typeof window !== 'undefined'
        && window.localStorage.getItem(STORAGE_KEY) === '1',
);

const hoverOpen = ref(false);
let hideTimer = null;

export function useSidebar() {
    const sidebarVisible = computed(() => !autoHideEnabled.value || hoverOpen.value);

    watch(autoHideEnabled, (enabled) => {
        if (typeof window === 'undefined') {
            return;
        }

        window.localStorage.setItem(STORAGE_KEY, enabled ? '1' : '0');

        if (!enabled) {
            hoverOpen.value = false;
            clearHideTimer();
        }
    });

    function clearHideTimer() {
        if (hideTimer) {
            window.clearTimeout(hideTimer);
            hideTimer = null;
        }
    }

    function openOnHover() {
        if (!autoHideEnabled.value) {
            return;
        }

        clearHideTimer();
        hoverOpen.value = true;
    }

    function scheduleHide() {
        if (!autoHideEnabled.value) {
            return;
        }

        clearHideTimer();
        hideTimer = window.setTimeout(() => {
            hoverOpen.value = false;
            hideTimer = null;
        }, 280);
    }

    function toggleAutoHide() {
        autoHideEnabled.value = !autoHideEnabled.value;
    }

    const sidebarCollapsed = computed(() => autoHideEnabled.value && !hoverOpen.value);

    return {
        autoHideEnabled,
        hoverOpen,
        sidebarVisible,
        sidebarCollapsed,
        openOnHover,
        scheduleHide,
        toggleAutoHide,
    };
}
