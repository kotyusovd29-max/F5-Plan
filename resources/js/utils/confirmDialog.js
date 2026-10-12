import { reactive } from 'vue';

export const confirmState = reactive({
    visible: false,
    title: 'Подтверждение',
    message: '',
    confirmLabel: 'Удалить',
    cancelLabel: 'Отмена',
    variant: 'danger',
    resolve: null,
});

/**
 * @param {{
 *   title?: string,
 *   message: string,
 *   confirmLabel?: string,
 *   cancelLabel?: string,
 *   variant?: 'danger' | 'primary',
 * }} options
 * @returns {Promise<boolean>}
 */
export function confirmDialog(options) {
    return new Promise((resolve) => {
        confirmState.title = options.title ?? 'Подтверждение';
        confirmState.message = options.message;
        confirmState.confirmLabel = options.confirmLabel ?? 'Удалить';
        confirmState.cancelLabel = options.cancelLabel ?? 'Отмена';
        confirmState.variant = options.variant ?? 'danger';
        confirmState.resolve = resolve;
        confirmState.visible = true;
    });
}

export function acceptConfirm() {
    confirmState.resolve?.(true);
    closeConfirm();
}

export function rejectConfirm() {
    confirmState.resolve?.(false);
    closeConfirm();
}

function closeConfirm() {
    confirmState.visible = false;
    confirmState.resolve = null;
}
