<script setup>
import F5Mark from './F5Mark.vue';

defineProps({
    size: {
        type: String,
        default: 'md',
    },
    onDark: {
        type: Boolean,
        default: false,
    },
    teamReveal: {
        type: Boolean,
        default: false,
    },
    iconOnly: {
        type: Boolean,
        default: false,
    },
});

const teamLeft = 'Команда'.split('');
const teamRight = 'Проекты'.split('');
</script>

<template>
    <div
        v-if="iconOnly"
        class="flex justify-center"
    >
        <F5Mark :size="size === 'lg' ? 'lg' : 'md'" />
        <span class="sr-only">F5 Plan</span>
    </div>

    <div
        v-else-if="teamReveal"
        class="f5-logo-team group flex w-full justify-center"
    >
        <div class="f5-logo-team__row inline-flex items-center justify-center">
            <div class="f5-logo-team__side f5-logo-team__side--left">
                <div class="f5-logo-team__side-wrap min-w-0 overflow-hidden">
                    <span
                        class="f5-logo-team__label f5-logo-team__label--left inline-flex whitespace-nowrap pr-2 text-sm font-semibold text-white"
                        aria-hidden="true"
                    >
                        <span
                            v-for="(char, index) in teamLeft"
                            :key="`left-${char}-${index}`"
                            class="f5-logo-team__char f5-logo-team__char--left"
                            :style="{ '--char-index': index }"
                        >{{ char }}</span>
                    </span>
                </div>
            </div>

            <F5Mark
                :size="size === 'lg' ? 'lg' : 'md'"
                class="f5-logo-team__mark relative z-10 shrink-0"
            />

            <div class="f5-logo-team__side f5-logo-team__side--right">
                <div class="f5-logo-team__side-wrap min-w-0 overflow-hidden">
                    <span
                        class="f5-logo-team__label f5-logo-team__label--right inline-flex whitespace-nowrap pl-2 text-sm font-semibold text-white"
                        aria-hidden="true"
                    >
                        <span
                            v-for="(char, index) in teamRight"
                            :key="`right-${char}-${index}`"
                            class="f5-logo-team__char f5-logo-team__char--right"
                            :style="{ '--char-index': index }"
                        >{{ char }}</span>
                    </span>
                </div>
            </div>

            <span class="sr-only">Команда F5 Проекты</span>
        </div>
    </div>

    <div v-else class="flex items-center gap-2.5" :class="size === 'lg' ? 'gap-3' : ''">
        <F5Mark :size="size === 'lg' ? 'lg' : 'md'" />

        <div class="min-w-0">
            <div
                class="truncate font-semibold leading-tight"
                :class="[
                    size === 'lg' ? 'text-lg' : 'text-sm',
                    onDark ? 'text-white' : 'text-f5-text dark:text-white',
                ]"
            >
                F5 Plan
            </div>
            <div
                v-if="size === 'lg'"
                class="truncate text-sm"
                :class="onDark ? 'text-white/50' : 'text-f5-text-muted dark:text-white/50'"
            >
                Планы внедрения amoCRM
            </div>
        </div>
    </div>
</template>

<style scoped>
.f5-logo-team__side {
    display: grid;
    transition: grid-template-columns 0.42s cubic-bezier(0.4, 0, 0.2, 1);
}

.f5-logo-team__side--left {
    grid-template-columns: 0fr;
}

.f5-logo-team__side--right {
    grid-template-columns: 0fr;
}

.f5-logo-team:hover .f5-logo-team__side--left,
.f5-logo-team:focus-within .f5-logo-team__side--left,
.f5-logo-team:hover .f5-logo-team__side--right,
.f5-logo-team:focus-within .f5-logo-team__side--right {
    grid-template-columns: 1fr;
}

.f5-logo-team__label--left {
    justify-content: flex-end;
    width: max-content;
    margin-left: auto;
}

.f5-logo-team__label--right {
    justify-content: flex-start;
}

.f5-logo-team__char {
    display: inline-block;
    opacity: 0;
    transition:
        opacity 0.18s ease,
        transform 0.22s cubic-bezier(0.34, 1.2, 0.64, 1);
    transition-delay: calc(var(--char-index) * 52ms);
}

.f5-logo-team__char--left {
    transform: translateX(8px);
}

.f5-logo-team__char--right {
    transform: translateX(-8px);
}

.f5-logo-team:hover .f5-logo-team__char,
.f5-logo-team:focus-within .f5-logo-team__char {
    opacity: 1;
    transform: translateX(0);
}

.f5-logo-team:not(:hover):not(:focus-within) .f5-logo-team__char {
    transition-delay: 0ms;
    opacity: 0;
}

.f5-logo-team:not(:hover):not(:focus-within) .f5-logo-team__char--left {
    transform: translateX(6px);
}

.f5-logo-team:not(:hover):not(:focus-within) .f5-logo-team__char--right {
    transform: translateX(-6px);
}
</style>
