<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useTheme } from '../composables/useTheme';

defineProps({
    onDark: {
        type: Boolean,
        default: false,
    },
});

const { theme, current, options, setTheme } = useTheme();
const open = ref(false);
const root = ref(null);

const lightOptions = computed(() => options.filter((item) => item.group === 'light'));
const darkOptions = computed(() => options.filter((item) => item.group === 'dark'));

function toggleMenu() {
    open.value = !open.value;
}

function choose(id) {
    setTheme(id);
    open.value = false;
}

function onDocumentClick(event) {
    if (!open.value || !root.value) {
        return;
    }

    if (!root.value.contains(event.target)) {
        open.value = false;
    }
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        open.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="inline-flex h-9 w-9 items-center justify-center rounded-xl border transition-colors"
            :class="onDark
                ? 'border-white/10 text-white/60 hover:bg-white/5 hover:text-white'
                : 'border-f5-border text-f5-text-muted hover:bg-f5-surface-2 hover:text-f5-text dark:border-white/10 dark:text-white/60 dark:hover:bg-white/5 dark:hover:text-white'"
            :title="`Тема: ${current.label}`"
            :aria-expanded="open"
            aria-haspopup="menu"
            @click="toggleMenu"
        >
            <span
                class="h-4 w-4 rounded-full border border-black/10 dark:border-white/20"
                :style="{ background: `linear-gradient(135deg, ${current.swatch} 45%, ${current.accent} 45%)` }"
                aria-hidden="true"
            />
        </button>

        <div
            v-if="open"
            class="absolute right-0 z-50 mt-2 w-56 overflow-hidden rounded-2xl border border-f5-border bg-f5-surface shadow-lg dark:border-white/10 dark:bg-f5-black"
            role="menu"
        >
            <div class="border-b border-f5-border px-3 py-2 dark:border-white/10">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-f5-text-muted dark:text-white/40">
                    Светлые
                </p>
            </div>
            <button
                v-for="option in lightOptions"
                :key="option.id"
                type="button"
                class="flex w-full items-center gap-3 px-3 py-2.5 text-left text-sm transition-colors"
                :class="theme === option.id
                    ? 'bg-f5-green/15 text-f5-text dark:bg-f5-green/20 dark:text-white'
                    : 'text-f5-text hover:bg-f5-surface-2 dark:text-white/80 dark:hover:bg-white/5'"
                role="menuitem"
                @click="choose(option.id)"
            >
                <span
                    class="h-5 w-5 shrink-0 rounded-full border border-black/10 dark:border-white/15"
                    :style="{ background: `linear-gradient(135deg, ${option.swatch} 45%, ${option.accent} 45%)` }"
                />
                <span class="font-medium">{{ option.label }}</span>
            </button>

            <div class="border-y border-f5-border px-3 py-2 dark:border-white/10">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-f5-text-muted dark:text-white/40">
                    Тёмные
                </p>
            </div>
            <button
                v-for="option in darkOptions"
                :key="option.id"
                type="button"
                class="flex w-full items-center gap-3 px-3 py-2.5 text-left text-sm transition-colors"
                :class="theme === option.id
                    ? 'bg-f5-green/15 text-f5-text dark:bg-f5-green/20 dark:text-white'
                    : 'text-f5-text hover:bg-f5-surface-2 dark:text-white/80 dark:hover:bg-white/5'"
                role="menuitem"
                @click="choose(option.id)"
            >
                <span
                    class="h-5 w-5 shrink-0 rounded-full border border-black/10 dark:border-white/15"
                    :style="{ background: `linear-gradient(135deg, ${option.swatch} 45%, ${option.accent} 45%)` }"
                />
                <span class="font-medium">{{ option.label }}</span>
            </button>
        </div>
    </div>
</template>
