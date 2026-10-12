import { ref } from 'vue';

const toasts = ref([]);
let nextId = 0;

export function useFlashToasts() {
    function push(message, type = 'success') {
        if (!message) {
            return;
        }

        const id = ++nextId;

        toasts.value = [...toasts.value, { id, message, type }];

        window.setTimeout(() => dismiss(id), 4500);
    }

    function dismiss(id) {
        toasts.value = toasts.value.filter((entry) => entry.id !== id);
    }

    return { toasts, push, dismiss };
}
