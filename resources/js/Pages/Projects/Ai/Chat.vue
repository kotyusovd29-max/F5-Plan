<script setup>
import { onMounted, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import ProjectNav from '../../../Components/Projects/ProjectNav.vue';
import AiChatPanel from '../../../Components/Projects/AiChatPanel.vue';
import { useProjectAiChat } from '../../../composables/useProjectAiChat';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
    messages: {
        type: Array,
        default: () => [],
    },
    meetingMessages: {
        type: Array,
        default: () => [],
    },
    auditRecommendationMessages: {
        type: Array,
        default: () => [],
    },
    kpMessages: {
        type: Array,
        default: () => [],
    },
    aiPrompts: {
        type: Object,
        default: () => ({
            analysis: null,
            actions: [],
            meetingActions: [],
            auditRecommendationActions: [],
        }),
    },
    chatBootstrap: {
        type: Object,
        default: () => ({}),
    },
});

const bootstrapThread = props.chatBootstrap?.thread;
const activeTab = ref(
    bootstrapThread === 'meeting'
    || bootstrapThread === 'audit_recommendations'
    || bootstrapThread === 'kp_generation'
        ? bootstrapThread
        : 'project',
);

const projectChat = useProjectAiChat(props.project, {
    thread: 'project',
    initialMessages: props.messages,
});

const meetingChat = useProjectAiChat(props.project, {
    thread: 'meeting',
    initialMessages: props.meetingMessages,
});

const auditChat = useProjectAiChat(props.project, {
    thread: 'audit_recommendations',
    initialMessages: props.auditRecommendationMessages,
});

const kpChat = useProjectAiChat(props.project, {
    thread: 'kp_generation',
    initialMessages: props.kpMessages,
});

const tabs = [
    {
        id: 'project',
        label: 'Изменение проекта',
        description: 'Поля, воронки, интеграции',
        icon: '⚙',
    },
    {
        id: 'meeting',
        label: 'Первая встреча',
        description: 'Структура встречи с клиентом',
        icon: '🤝',
    },
    {
        id: 'audit_recommendations',
        label: 'Рекомендации по аудиту',
        description: 'Документ клиенту по итогам встречи',
        icon: '📋',
    },
    {
        id: 'kp_generation',
        label: 'Генерация КП',
        description: 'Презентация по резюме встречи',
        icon: '📊',
    },
];

const chatsByTab = {
    project: projectChat,
    meeting: meetingChat,
    audit_recommendations: auditChat,
    kp_generation: kpChat,
};

const messagesByTab = {
    project: () => props.messages,
    meeting: () => props.meetingMessages,
    audit_recommendations: () => props.auditRecommendationMessages,
    kp_generation: () => props.kpMessages,
};

const activeChat = () => chatsByTab[activeTab.value] ?? projectChat;

watch(activeTab, () => {
    const chat = activeChat();
    void chat.loadMessages(true);
    chat.scrollToBottom();
});

onMounted(async () => {
    const bootstrap = props.chatBootstrap ?? {};
    const chat = chatsByTab[bootstrap.thread] ?? projectChat;

    if (bootstrap.prompt || bootstrap.mode || bootstrap.autostart) {
        await chat.setBootstrap(bootstrap);
    } else {
        chat.scrollToBottom();
    }

    const initialMessages = (messagesByTab[bootstrap.thread] ?? messagesByTab.project)();

    const processingMessage = initialMessages.find(
        (item) => item.role === 'assistant' && item.status === 'processing',
    );

    if (processingMessage?.id) {
        await chat.loadMessages(true);
        const refreshed = chat.messages.value.find((item) => item.id === processingMessage.id);

        if (refreshed?.status === 'processing') {
            void chat.pollForAssistantResponse(processingMessage.id);
        }
    }
});
</script>

<template>
    <Head :title="`ИИ-чат — ${project.company_name}`" />

    <AppLayout fluid>
        <template #header>
            <div class="flex min-w-0 items-center justify-between gap-4">
                <div class="min-w-0">
                    <h1 class="truncate text-xl font-bold text-f5-text dark:text-white">
                        {{ project.company_name }}
                    </h1>
                    <p class="text-sm text-f5-text-muted dark:text-white/50">
                        <Link :href="project.urls.home" class="hover:text-f5-text dark:hover:text-white">← К проектам</Link>
                        · Чат с ИИ
                    </p>
                </div>
                <ProjectNav
                    :project-id="project.id"
                    :urls="project.urls"
                    current="ai"
                    compact
                />
            </div>
        </template>

        <div class="flex h-[calc(100vh-4rem)] min-h-0">
            <aside
                class="flex w-[220px] shrink-0 flex-col border-r border-f5-border bg-f5-surface-2/60 dark:border-white/10 dark:bg-white/[0.03] sm:w-[240px]"
                aria-label="Типы чата"
            >
                <div class="border-b border-f5-border px-4 py-3 dark:border-white/10">
                    <p class="text-xs font-semibold uppercase tracking-widest text-f5-text-muted dark:text-white/40">
                        Чаты ИИ
                    </p>
                </div>

                <nav class="flex flex-1 flex-col gap-1 p-2">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        type="button"
                        class="w-full rounded-xl px-3 py-3 text-left transition"
                        :class="activeTab === tab.id
                            ? 'bg-f5-green/15 text-f5-text shadow-sm dark:bg-f5-green/20 dark:text-white'
                            : 'text-f5-text-muted hover:bg-f5-surface hover:text-f5-text dark:text-white/55 dark:hover:bg-white/5 dark:hover:text-white'"
                        @click="activeTab = tab.id"
                    >
                        <span class="flex items-start gap-2.5">
                            <span
                                class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-sm"
                                :class="activeTab === tab.id
                                    ? 'bg-f5-green/20 dark:bg-f5-green/30'
                                    : 'bg-f5-surface dark:bg-white/5'"
                                aria-hidden="true"
                            >
                                {{ tab.icon }}
                            </span>
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold leading-snug">{{ tab.label }}</span>
                                <span class="mt-0.5 block text-[11px] leading-snug opacity-70">{{ tab.description }}</span>
                            </span>
                        </span>
                    </button>
                </nav>
            </aside>

            <section
                class="flex min-h-0 min-w-0 flex-1 flex-col bg-f5-surface dark:bg-f5-black"
                data-tour="ai-chat-panel"
            >
                <AiChatPanel
                    v-show="activeTab === 'project'"
                    :project="project"
                    :ai-prompts="aiPrompts"
                    :chat="projectChat"
                    :can-edit="project.can_edit !== false"
                />
                <AiChatPanel
                    v-show="activeTab === 'meeting'"
                    :project="project"
                    :ai-prompts="aiPrompts"
                    :chat="meetingChat"
                    :can-edit="project.can_edit !== false"
                />
                <AiChatPanel
                    v-show="activeTab === 'audit_recommendations'"
                    :project="project"
                    :ai-prompts="aiPrompts"
                    :chat="auditChat"
                    :can-edit="project.can_edit !== false"
                />
                <AiChatPanel
                    v-show="activeTab === 'kp_generation'"
                    :project="project"
                    :ai-prompts="aiPrompts"
                    :chat="kpChat"
                    :can-edit="project.can_edit !== false"
                />
            </section>
        </div>
    </AppLayout>
</template>
