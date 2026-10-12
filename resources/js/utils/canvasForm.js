import { router } from '@inertiajs/vue3';
import { confirmDialog } from './confirmDialog';

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function isPlainObject(value) {
    return value !== null && typeof value === 'object' && !(value instanceof File) && !(value instanceof Blob);
}

function appendFormValue(formData, key, value) {
    if (value === undefined || value === null) {
        return;
    }

    if (Array.isArray(value)) {
        // Nested structures (outcomes[].path_points, widgets, etc.) must be JSON.
        // Flat form fields cannot represent arrays-inside-objects; String(point) becomes "[object Object]".
        const hasObjects = value.some((item) => isPlainObject(item));

        if (value.length === 0 || hasObjects) {
            formData.set(key, JSON.stringify(value));
            return;
        }

        value.forEach((item, index) => {
            formData.append(`${key}[${index}]`, String(item));
        });
        return;
    }

    if (isPlainObject(value)) {
        formData.set(key, JSON.stringify(value));
        return;
    }

    formData.set(key, value);
}

function buildFormData(fields, method = 'POST') {
    const formData = new FormData();

    Object.entries(fields).forEach(([key, value]) => {
        appendFormValue(formData, key, value);
    });

    if (method !== 'POST') {
        formData.set('_method', method);
    }

    if (!formData.get('_token')) {
        formData.set('_token', getCsrfToken());
    }

    return formData;
}

export async function submitCanvasForm({
    url,
    fields = {},
    method = 'POST',
    confirmMessage = null,
    onStatus = null,
    reloadOnly = null,
    skipReload = false,
    resetOnSuccess = false,
}) {
    if (confirmMessage) {
        const confirmed = await confirmDialog({
            title: 'Подтвердите действие',
            message: confirmMessage,
        });

        if (!confirmed) {
            return { ok: false, cancelled: true };
        }
    }

    onStatus?.('Сохранение...', 'saving');

    try {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Canvas-Request': '1',
            },
            body: buildFormData(fields, method),
        });

        if (response.status === 419) {
            onStatus?.('Обновите страницу', 'error');
            return { ok: false };
        }

        if (response.status === 422) {
            const data = await response.json();
            onStatus?.('Проверьте поля', 'error');
            return { ok: false, errors: data.errors ?? {} };
        }

        if (!response.ok) {
            onStatus?.(`Ошибка ${response.status}`, 'error');
            return { ok: false };
        }

        const data = await response.json();

        if (!skipReload) {
            await new Promise((resolve) => {
                router.reload({
                    only: reloadOnly ?? undefined,
                    preserveScroll: true,
                    onFinish: resolve,
                });
            });
        }

        onStatus?.(data.message || 'Сохранено', 'saved');

        return { ok: true, message: data.message, resetOnSuccess };
    } catch {
        onStatus?.('Сеть недоступна', 'error');
        return { ok: false };
    }
}

export async function deleteCanvasBlock(url, onStatus) {
    onStatus?.('Удаление...', 'saving');

    try {
        const response = await fetch(url, {
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

        router.reload({ preserveScroll: true });
        onStatus?.(data.message || 'Удалено', 'saved');

        return { ok: true };
    } catch (error) {
        onStatus?.(error.message || 'Ошибка', 'error');
        return { ok: false };
    }
}
