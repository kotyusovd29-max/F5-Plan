<script setup>
import { computed, onMounted, onUnmounted, watch } from 'vue';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    document: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close']);

const editUrl = computed(() => {
    if (!props.document) {
        return '';
    }

    if (props.document.url) {
        return props.document.url;
    }

    if (props.document.id) {
        return `https://docs.google.com/document/d/${props.document.id}/edit`;
    }

    return '';
});

const embedUrl = computed(() => editUrl.value);

function onKeydown(event) {
    if (event.key === 'Escape' && props.open) {
        emit('close');
    }
}

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
});

watch(() => props.open, (isOpen) => {
    document.body.style.overflow = isOpen ? 'hidden' : '';
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open && document"
            class="fixed inset-0 z-[90] flex items-center justify-center bg-black/50 p-1 sm:p-2"
            role="dialog"
            aria-modal="true"
            aria-label="Google Docs"
            @click.self="emit('close')"
        >
            <div class="flex h-[min(98vh,1200px)] w-full max-w-[min(98vw,1600px)] flex-col overflow-hidden rounded-xl border border-f5-border bg-f5-surface shadow-xl dark:border-white/10 dark:bg-f5-surface sm:rounded-2xl">
                <div class="flex shrink-0 items-start justify-between gap-3 border-b border-f5-border px-4 py-3 dark:border-white/10 sm:px-5">
                    <div class="min-w-0">
                        <h2 class="truncate text-base font-semibold text-f5-text dark:text-white">
                            {{ document.name || 'Документ Google Docs' }}
                        </h2>
                        <p class="mt-1 text-xs text-f5-text-muted dark:text-white/50">
                            Если редактор не открылся во фрейме (ограничение Google), используйте кнопку ниже.
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <a
                            v-if="document.download_url"
                            :href="document.download_url"
                            class="f5-btn f5-btn--ghost f5-btn--sm"
                        >
                            Скачать PPTX
                        </a>
                        <a
                            v-if="editUrl"
                            :href="editUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="f5-btn f5-btn--primary f5-btn--sm"
                        >
                            Открыть в новой вкладке
                        </a>
                        <button
                            type="button"
                            class="f5-btn f5-btn--ghost f5-btn--sm"
                            @click="emit('close')"
                        >
                            Закрыть
                        </button>
                    </div>
                </div>

                <div class="relative min-h-0 flex-1 bg-f5-surface-2 dark:bg-black/20">
                    <iframe
                        v-if="embedUrl"
                        :src="embedUrl"
                        class="h-full w-full border-0"
                        title="Google Docs"
                        allow="clipboard-read; clipboard-write"
                    />
                </div>
            </div>
        </div>
    </Teleport>
</template>
