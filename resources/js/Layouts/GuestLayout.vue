<script setup>
import F5Logo from '../Components/F5Logo.vue';
import ThemeToggle from '../Components/ThemeToggle.vue';
</script>

<template>
    <div class="relative min-h-screen overflow-hidden bg-f5-dark">
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute -left-32 top-0 h-96 w-96 rounded-full bg-f5-green/25 blur-3xl" />
            <div class="absolute bottom-0 right-0 h-[28rem] w-[28rem] rounded-full bg-f5-yellow/20 blur-3xl" />
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,rgba(184,230,46,0.06),transparent_55%)]" />
        </div>

        <div class="relative z-10 flex min-h-screen flex-col">
            <header class="flex items-center justify-between px-6 py-5 sm:px-10">
                <F5Logo size="lg" on-dark />
                <ThemeToggle on-dark />
            </header>

            <main class="flex flex-1 items-center justify-center px-4 pb-10">
                <slot />
            </main>

            <footer class="px-6 py-4 text-center text-xs text-white/40">
                © {{ new Date().getFullYear() }} Команда F5 — автоматизация продаж
            </footer>
        </div>
    </div>
</template>
