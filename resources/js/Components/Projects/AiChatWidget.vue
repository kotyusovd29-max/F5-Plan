<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AiChatPanel from './AiChatPanel.vue';
import { useProjectAiChat } from '../../composables/useProjectAiChat';

const props = defineProps({
    variant: {
        type: String,
        default: 'app',
    },
});

const page = usePage();
const isOpen = ref(false);
const selectedProject = ref(null);
const pendingBootstrap = ref(null);

const aiChat = computed(() => page.props.aiChat ?? { projects: [], prompts: { analysis: null, actions: [] } });
const projects = computed(() => aiChat.value.projects ?? []);
const aiPrompts = computed(() => aiChat.value.prompts ?? { analysis: null, actions: [] });

// На полной странице чата виджет не нужен. На карточке проекта FAB скрыт,
// но оверлей остаётся — кнопка «Чат» в ИИ-помощнике его открывает.
const hideFab = computed(() => (
    page.component === 'Projects/Ai/Chat'
    || page.component === 'Projects/Show'
));

const widgetEnabled = computed(() => (
    !!page.props.auth?.user && page.component !== 'Projects/Ai/Chat'
));

const panelTopClass = computed(() => (
    props.variant === 'canvas' ? 'top-[52px]' : 'top-16'
));

const pageProject = computed(() => {
    const project = page.props.project;

    if (!project?.id) {
        return null;
    }

    const fromShared = projects.value.find((item) => item.id === project.id);

    if (fromShared) {
        return fromShared;
    }

    if (project.urls?.ai_send || project.urls?.ai_messages) {
        return {
            id: project.id,
            company_name: project.company_name,
            urls: {
                show: project.urls.show ?? project.urls.home,
                ...project.urls,
            },
        };
    }

    return null;
});

const activeProject = computed(() => selectedProject.value);

const chat = useProjectAiChat(activeProject, {
    onApplied: async (data) => {
        chat.status.value = data.message || 'Изменения применены.';
        chat.statusType.value = 'success';
        chat.applyingId.value = null;
        await chat.loadMessages(true);
        router.reload({ preserveScroll: true });
    },
});

watch(isOpen, (open) => {
    if (!open || !activeProject.value) {
        return;
    }

    chat.loadMessages();
    chat.scrollToBottom();
});

function selectProject(project) {
    selectedProject.value = project;
}

function openWidget() {
    if (pageProject.value) {
        selectedProject.value = pageProject.value;
    }

    isOpen.value = true;

    if (!selectedProject.value && projects.value.length === 1) {
        selectedProject.value = projects.value[0];
    }

    applyPendingBootstrap();
}

function closeWidget() {
    isOpen.value = false;
}

function applyPendingBootstrap() {
    const bootstrap = pendingBootstrap.value;

    if (!bootstrap || !activeProject.value) {
        return;
    }

    pendingBootstrap.value = null;
    chat.setBootstrap(bootstrap);
}

watch([activeProject, isOpen], () => {
    if (isOpen.value && activeProject.value) {
        chat.loadMessages();
        applyPendingBootstrap();
    }
});

async function openWithBootstrap({ project = null, prompt, mode, autostart = false, reloadMessages = false } = {}) {
    if (project) {
        const resolved = projects.value.find((item) => item.id === project.id) ?? project;
        selectedProject.value = resolved;
    } else if (pageProject.value) {
        selectedProject.value = pageProject.value;
    }

    pendingBootstrap.value = { prompt, mode, autostart };
    isOpen.value = true;

    if (!selectedProject.value && projects.value.length === 1) {
        selectedProject.value = projects.value[0];
    }

    if (activeProject.value) {
        await chat.loadMessages(reloadMessages);
        applyPendingBootstrap();
    }
}

function open() {
    openWidget();
}

function backToProjectList() {
    if (pageProject.value) {
        closeWidget();
        return;
    }

    selectedProject.value = null;
    pendingBootstrap.value = null;
    chat.resetConversation();
}

defineExpose({
    open,
    openWithBootstrap,
    close: closeWidget,
});
</script>

<template>
    <Teleport to="body">
        <div v-if="widgetEnabled" class="ai-chat-widget pointer-events-none fixed inset-0 z-[80]">
            <button
                v-if="!isOpen && !hideFab"
                type="button"
                class="pointer-events-auto absolute right-4 bottom-4 z-0 flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-f5-green to-f5-yellow text-sm font-bold text-f5-black shadow-lg transition-transform hover:scale-105 active:scale-95 sm:right-6"
                aria-expanded="false"
                aria-label="Открыть чат с ИИ"
                @click="openWidget"
            >
                <span aria-hidden="true">ИИ</span>
            </button>

            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="translate-y-3 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="translate-y-0 opacity-100"
                leave-to-class="translate-y-3 opacity-0"
            >
                <section
                    v-if="isOpen"
                    class="pointer-events-auto absolute inset-x-2 bottom-2 z-10 flex flex-col overflow-hidden rounded-2xl border border-f5-border bg-f5-surface shadow-2xl dark:border-white/10 dark:bg-f5-black sm:inset-x-auto sm:right-4 sm:bottom-4 sm:w-[min(100vw-2rem,760px)]"
                    :class="panelTopClass"
                    role="dialog"
                    aria-label="Чат с ИИ"
                >
                    <header class="flex items-center gap-3 border-b border-f5-border bg-f5-surface px-4 py-3 dark:border-white/10 dark:bg-f5-black">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-f5-green to-f5-yellow text-xs font-bold text-f5-black">
                            ИИ
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-f5-text dark:text-white">
                                ИИ-помощник
                            </p>
                            <p v-if="activeProject" class="truncate text-xs text-f5-text-muted dark:text-white/50">
                                {{ activeProject.company_name }}
                            </p>
                            <p v-else class="truncate text-xs text-f5-text-muted dark:text-white/50">
                                Выберите проект
                            </p>
                        </div>
                        <button
                            v-if="activeProject && !pageProject"
                            type="button"
                            class="f5-btn f5-btn--ghost f5-btn--sm shrink-0"
                            title="Сменить проект"
                            @click="backToProjectList"
                        >
                            ←
                        </button>
                        <Link
                            v-if="activeProject"
                            :href="activeProject.urls.ai_chat"
                            class="f5-btn f5-btn--ghost f5-btn--sm shrink-0"
                            title="Открыть на всю страницу"
                        >
                            ↗
                        </Link>
                        <button
                            type="button"
                            class="f5-btn f5-btn--ghost f5-btn--sm shrink-0"
                            aria-label="Закрыть чат"
                            @click="closeWidget"
                        >
                            ✕
                        </button>
                    </header>

                    <div v-if="!activeProject" class="flex min-h-0 flex-1 flex-col overflow-y-auto p-4">
                        <p class="mb-3 text-sm text-f5-text-muted dark:text-white/50">
                            С каким проектом хотите пообщаться с ИИ?
                        </p>

                        <p v-if="projects.length === 0" class="text-sm text-f5-text-muted dark:text-white/50">
                            У вас пока нет проектов.
                            <Link href="/projects/create" class="text-f5-accent hover:underline">
                                Создать проект
                            </Link>
                        </p>

                        <ul v-else class="space-y-2">
                            <li v-for="project in projects" :key="project.id">
                                <button
                                    type="button"
                                    class="flex w-full flex-col rounded-xl border border-f5-border bg-f5-surface-2 px-3 py-3 text-left transition-colors hover:border-f5-green/50 dark:border-white/10 dark:bg-white/5 dark:hover:border-f5-green/40"
                                    @click="selectProject(project)"
                                >
                                    <span class="text-sm font-medium text-f5-text dark:text-white">
                                        {{ project.company_name }}
                                    </span>
                                </button>
                            </li>
                        </ul>
                    </div>

                    <AiChatPanel
                        v-else
                        :project="activeProject"
                        :ai-prompts="aiPrompts"
                        :chat="chat"
                        compact
                    />
                </section>
            </Transition>
        </div>
    </Teleport>
</template>
