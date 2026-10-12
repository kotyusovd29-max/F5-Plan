<script setup>
import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useFlashToasts } from '../composables/useFlashToasts';

const page = usePage();
const { toasts, push, dismiss } = useFlashToasts();

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) {
            push(flash.success, 'success');
        }

        if (flash?.error) {
            push(flash.error, 'error');
        }
    },
    { deep: true, immediate: true },
);
</script>

<template>
    <div
        class="pointer-events-none fixed top-5 right-5 z-[200] flex w-full max-w-sm flex-col gap-3"
        aria-live="polite"
        aria-atomic="true"
    >
        <TransitionGroup name="f5-toast">
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="f5-toast pointer-events-auto"
                :class="toast.type === 'error' ? 'f5-toast--error' : 'f5-toast--success'"
                role="status"
            >
                <span class="f5-toast__icon" aria-hidden="true">
                    <svg v-if="toast.type === 'error'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>

                <p class="f5-toast__message">{{ toast.message }}</p>

                <button
                    type="button"
                    class="f5-toast__close"
                    aria-label="Закрыть"
                    @click="dismiss(toast.id)"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
