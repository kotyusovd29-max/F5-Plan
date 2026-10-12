<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    primary: {
        type: Boolean,
        default: false,
    },
    flush: {
        type: Boolean,
        default: false,
    },
    sectionId: {
        type: String,
        default: '',
    },
    count: {
        type: [Number, String],
        default: null,
    },
    collapsible: {
        type: Boolean,
        default: false,
    },
    /** Initial open state when collapsible (ignored if storageKey restores a value). */
    defaultOpen: {
        type: Boolean,
        default: true,
    },
    storageKey: {
        type: String,
        default: '',
    },
});

function readStoredOpen() {
    if (!props.storageKey || typeof localStorage === 'undefined') {
        return null;
    }

    try {
        const stored = localStorage.getItem(props.storageKey);

        if (stored === '0') {
            return false;
        }

        if (stored === '1') {
            return true;
        }
    } catch {
        // ignore
    }

    return null;
}

const open = ref(readStoredOpen() ?? props.defaultOpen);

watch(
    () => props.defaultOpen,
    (value) => {
        if (readStoredOpen() === null) {
            open.value = value;
        }
    },
);

watch(open, (value) => {
    if (!props.storageKey || typeof localStorage === 'undefined') {
        return;
    }

    try {
        localStorage.setItem(props.storageKey, value ? '1' : '0');
    } catch {
        // ignore
    }
});

function toggle() {
    if (!props.collapsible) {
        return;
    }

    open.value = !open.value;
}

defineExpose({
    open: () => {
        open.value = true;
    },
    close: () => {
        open.value = false;
    },
    isOpen: () => open.value,
});
</script>

<template>
    <section
        :id="sectionId || undefined"
        class="scroll-mt-40 overflow-hidden rounded-2xl border shadow-sm"
        :class="primary
            ? 'border-f5-green/30 bg-f5-surface dark:border-f5-green/20 dark:bg-f5-black'
            : 'border-f5-border bg-f5-surface dark:border-white/10 dark:bg-f5-black'"
    >
        <header
            class="flex items-start justify-between gap-4 border-b px-5 py-4 sm:px-6 sm:py-5"
            :class="[
                primary
                    ? 'border-f5-green/20 bg-f5-green/5 dark:border-f5-green/10 dark:bg-f5-green/5'
                    : 'border-f5-border bg-f5-surface-2 dark:border-white/10 dark:bg-white/5',
                collapsible ? 'cursor-pointer select-none' : '',
            ]"
            @click="toggle"
        >
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-if="collapsible"
                        type="button"
                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-f5-text-muted transition-colors hover:bg-black/5 hover:text-f5-text dark:text-white/40 dark:hover:bg-white/10 dark:hover:text-white"
                        :aria-expanded="open"
                        :aria-controls="sectionId ? `${sectionId}-body` : undefined"
                        @click.stop="toggle"
                    >
                        <span
                            class="inline-block text-[10px] transition-transform duration-300 ease-[cubic-bezier(0.34,1.2,0.64,1)]"
                            :class="{ 'rotate-90': open }"
                            aria-hidden="true"
                        >▶</span>
                    </button>

                    <h3 class="text-base font-semibold text-f5-text dark:text-white">
                        <slot name="title" />
                    </h3>

                    <span
                        v-if="count !== null && count !== undefined && count !== ''"
                        class="inline-flex min-w-6 items-center justify-center rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums"
                        :class="Number(count) > 0
                            ? 'bg-f5-green/20 text-f5-text dark:bg-f5-green/25 dark:text-f5-green'
                            : 'bg-black/5 text-f5-text-muted dark:bg-white/10 dark:text-white/45'"
                    >
                        {{ count }}
                    </span>
                </div>
                <p
                    v-if="$slots.subtitle"
                    class="mt-1 text-sm text-f5-text-muted dark:text-white/50"
                    :class="collapsible ? 'sm:pl-9' : ''"
                >
                    <slot name="subtitle" />
                </p>
            </div>

            <div
                v-if="$slots.actions"
                class="flex shrink-0 flex-wrap items-center justify-end gap-2"
                @click.stop
            >
                <slot name="actions" />
            </div>
        </header>

        <div
            v-if="collapsible"
            class="f5-collapse"
            :class="{ 'f5-collapse--open': open }"
        >
            <div class="f5-collapse__inner">
                <div
                    :id="sectionId ? `${sectionId}-body` : undefined"
                    :class="flush ? 'p-0' : 'p-5 sm:p-6'"
                >
                    <slot />
                </div>
            </div>
        </div>
        <div
            v-else
            :id="sectionId ? `${sectionId}-body` : undefined"
            :class="flush ? 'p-0' : 'p-5 sm:p-6'"
        >
            <slot />
        </div>
    </section>
</template>
