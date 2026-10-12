<script setup>
import { confirmState, acceptConfirm, rejectConfirm } from '../utils/confirmDialog';

function onBackdropClick() {
    rejectConfirm();
}

function onKeydown(event) {
    if (!confirmState.visible) {
        return;
    }

    if (event.key === 'Escape') {
        rejectConfirm();
    }
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="confirmState.visible"
            class="fixed inset-0 z-[60] flex items-end justify-center p-4 sm:items-center"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="confirmState.title ? 'confirm-dialog-title' : undefined"
            @keydown="onKeydown"
        >
            <button
                type="button"
                class="absolute inset-0 bg-black/50"
                aria-label="Закрыть"
                @click="onBackdropClick"
            />

            <div class="relative w-full max-w-md overflow-hidden rounded-2xl border border-f5-border bg-f5-surface shadow-xl dark:border-white/10 dark:bg-f5-black">
                <div class="px-5 py-4">
                    <h3
                        v-if="confirmState.title"
                        id="confirm-dialog-title"
                        class="text-lg font-semibold text-f5-text dark:text-white"
                    >
                        {{ confirmState.title }}
                    </h3>
                    <p
                        class="text-sm text-f5-text-muted dark:text-white/70"
                        :class="confirmState.title ? 'mt-2' : ''"
                    >
                        {{ confirmState.message }}
                    </p>
                </div>

                <div class="flex justify-end gap-2 border-t border-f5-border px-5 py-3 dark:border-white/10">
                    <button type="button" class="f5-btn f5-btn--ghost" @click="rejectConfirm">
                        {{ confirmState.cancelLabel }}
                    </button>
                    <button
                        type="button"
                        class="f5-btn"
                        :class="confirmState.variant === 'danger' ? 'f5-btn--danger' : 'f5-btn--primary'"
                        @click="acceptConfirm"
                    >
                        {{ confirmState.confirmLabel }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
