<script setup>
import { computed } from 'vue';

const props = defineProps({
    size: {
        type: String,
        default: 'md',
    },
    round: {
        type: Boolean,
        default: false,
    },
});

const sizeClass = computed(() => {
    const sizes = {
        sm: 'h-8 w-8 text-xs',
        md: 'h-9 w-9 text-sm',
        lg: 'h-11 w-11 text-[0.95rem]',
        fab: 'h-14 w-14 text-base',
    };

    return sizes[props.size] ?? sizes.md;
});
</script>

<template>
    <div
        class="f5-mark relative flex shrink-0 items-center justify-center overflow-hidden bg-gradient-to-br from-[#d4f34a] via-f5-green to-f5-yellow font-black text-f5-black shadow-[0_6px_18px_rgba(184,230,46,0.38)] ring-1 ring-black/10 dark:ring-white/15"
        :class="[
            sizeClass,
            round ? 'rounded-full' : 'rounded-xl',
        ]"
        aria-hidden="true"
    >
        <span class="relative z-10 flex items-center gap-px font-black leading-none">
            <span>F</span>
            <span>5</span>
        </span>

        <span class="f5-mark__overlay pointer-events-none absolute inset-0 bg-gradient-to-t from-black/[0.08] via-transparent to-white/30" />
        <span class="f5-mark__glow pointer-events-none absolute -right-2 -top-2 h-8 w-8 rounded-full bg-white/25 blur-md" />
        <span class="f5-mark__shine pointer-events-none absolute inset-0" />
    </div>
</template>

<style scoped>
.f5-mark {
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}

.f5-mark__shine {
    background: linear-gradient(
        105deg,
        transparent 38%,
        rgba(255, 255, 255, 0.1) 44%,
        rgba(255, 255, 255, 0.55) 50%,
        rgba(255, 255, 255, 0.1) 56%,
        transparent 62%
    );
    transform: translateX(-130%) skewX(-14deg);
    opacity: 0;
}

.f5-mark:hover,
.group:hover .f5-mark {
    animation: f5-mark-hover 2.1s ease-in-out infinite;
}

.f5-mark:hover .f5-mark__shine,
.group:hover .f5-mark .f5-mark__shine {
    opacity: 1;
    animation: f5-mark-shine 1.7s ease-in-out infinite;
}

.f5-mark:hover .f5-mark__glow,
.group:hover .f5-mark .f5-mark__glow {
    animation: f5-mark-glow 2.1s ease-in-out infinite;
}

@keyframes f5-mark-shine {
    0% {
        transform: translateX(-130%) skewX(-14deg);
    }

    100% {
        transform: translateX(130%) skewX(-14deg);
    }
}

@keyframes f5-mark-hover {
    0%,
    100% {
        transform: scale(1) rotate(0deg);
        box-shadow: 0 6px 18px rgba(184, 230, 46, 0.38);
    }

    50% {
        transform: scale(1.06) rotate(-2deg);
        box-shadow: 0 12px 30px rgba(184, 230, 46, 0.58);
    }
}

@keyframes f5-mark-glow {
    0%,
    100% {
        opacity: 0.55;
        transform: scale(1);
    }

    50% {
        opacity: 1;
        transform: scale(1.2);
    }
}
</style>
