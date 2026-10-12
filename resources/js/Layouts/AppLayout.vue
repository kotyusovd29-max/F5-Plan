<script setup>
import { computed, provide, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import F5Logo from '../Components/F5Logo.vue';
import NavLink from '../Components/NavLink.vue';
import ThemeToggle from '../Components/ThemeToggle.vue';
import AiChatWidget from '../Components/Projects/AiChatWidget.vue';
import ConfirmDialog from '../Components/ConfirmDialog.vue';
import ProductTourHost from '../Components/ProductTourHost.vue';
import { useSidebar } from '../composables/useSidebar';
import { GLOBAL_AI_CHAT_KEY } from '../composables/useGlobalAiChat';

defineProps({
    fluid: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const aiChatWidgetRef = ref(null);

provide(GLOBAL_AI_CHAT_KEY, {
    open: (...args) => aiChatWidgetRef.value?.open(...args),
    openWithBootstrap: (...args) => aiChatWidgetRef.value?.openWithBootstrap(...args),
    close: (...args) => aiChatWidgetRef.value?.close(...args),
});

const {
    autoHideEnabled,
    hoverOpen,
    sidebarCollapsed,
    openOnHover,
    scheduleHide,
    toggleAutoHide,
} = useSidebar();
</script>

<template>
    <div class="flex min-h-screen bg-f5-surface-2 dark:bg-f5-dark">
        <aside
            class="app-sidebar"
            :class="{
                'app-sidebar--collapsed': sidebarCollapsed,
                'app-sidebar--overlay': autoHideEnabled && hoverOpen,
            }"
            aria-label="Главное меню"
            @mouseenter="openOnHover"
            @mouseleave="scheduleHide"
        >
            <div class="app-sidebar__header">
                <Link href="/" class="app-sidebar__logo-link text-white no-underline">
                    <F5Logo
                        :icon-only="sidebarCollapsed"
                        :team-reveal="!sidebarCollapsed"
                        on-dark
                    />
                </Link>
            </div>

            <nav class="app-sidebar__nav flex-1 space-y-1 py-4">
                <NavLink
                    href="/"
                    label="Проекты"
                    tour-id="nav-projects"
                    :active="$page.url === '/' || $page.url.startsWith('/?')"
                >
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span class="app-sidebar__label">Проекты</span>
                </NavLink>

                <NavLink
                    href="/knowledge"
                    label="База знаний"
                    :active="$page.url.startsWith('/knowledge')"
                >
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    <span class="app-sidebar__label">База знаний</span>
                </NavLink>

                <NavLink
                    href="/profile"
                    label="Личный кабинет"
                    :active="$page.url.startsWith('/profile')"
                >
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <span class="app-sidebar__label">Личный кабинет</span>
                </NavLink>

                <NavLink
                    v-if="user?.is_admin"
                    href="/admin/dashboard"
                    label="Админ-панель"
                    :active="$page.url.startsWith('/admin')"
                >
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <span class="app-sidebar__label">Админ-панель</span>
                </NavLink>
            </nav>

            <div class="app-sidebar__footer">
                <button
                    type="button"
                    class="app-sidebar__hide-btn"
                    :class="{ 'app-sidebar__hide-btn--active': autoHideEnabled }"
                    :title="autoHideEnabled ? 'Автоскрытие: вкл' : 'Автоскрытие: выкл'"
                    @click="toggleAutoHide"
                >
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                    </svg>
                    <span class="app-sidebar__label">
                        {{ autoHideEnabled ? 'Автоскрытие: вкл' : 'Автоскрытие: выкл' }}
                    </span>
                </button>
                <p class="app-sidebar__footer-note mt-3 px-1 text-xs text-white/40">
                    Команда F5
                </p>
            </div>
        </aside>

        <div
            class="app-main"
            :class="{ 'app-main--collapsed': autoHideEnabled }"
        >
            <header class="sticky top-0 z-20 border-b border-f5-border bg-f5-surface/90 backdrop-blur dark:border-white/10 dark:bg-f5-black/90">
                <div class="flex min-h-16 items-center justify-between gap-4 px-6 py-3">
                    <div class="min-w-0 flex-1">
                        <slot name="header" />
                    </div>

                    <div class="flex shrink-0 items-center gap-3">
                        <ThemeToggle />

                        <div class="hidden h-8 w-px bg-f5-border dark:bg-white/10 sm:block" />

                        <Link
                            href="/profile"
                            class="hidden text-sm text-f5-text-muted hover:text-f5-text dark:text-white/60 dark:hover:text-white sm:inline"
                        >
                            {{ user?.name }}
                        </Link>

                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            class="f5-btn f5-btn--ghost !px-3 !py-2 text-xs"
                        >
                            Выйти
                        </Link>
                    </div>
                </div>
            </header>

            <main :class="fluid ? 'flex-1 min-w-0' : 'flex-1 min-w-0 px-6 py-6'">
                <slot />
            </main>
        </div>

        <AiChatWidget v-if="user" ref="aiChatWidgetRef" />
        <ProductTourHost v-if="user" />
        <ConfirmDialog />
    </div>
</template>
