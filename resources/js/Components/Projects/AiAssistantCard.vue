<script setup>
import { computed, ref, watch } from 'vue';
import AiModelSelect from './AiModelSelect.vue';
import AiQuickFillPanel from './AiQuickFillPanel.vue';
import ProjectAnalysisBlock from './ProjectAnalysisBlock.vue';
import ProjectBriefDocumentsPanel from './ProjectBriefDocumentsPanel.vue';
import { useGlobalAiChat } from '../../composables/useGlobalAiChat';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
    aiPrompts: {
        type: Object,
        default: () => ({ analysis: null, actions: [] }),
    },
    briefDocuments: {
        type: Array,
        default: () => [],
    },
    compact: {
        type: Boolean,
        default: false,
    },
    canEdit: {
        type: Boolean,
        default: true,
    },
    collapsible: {
        type: Boolean,
        default: false,
    },
    defaultOpen: {
        type: Boolean,
        default: true,
    },
    storageKey: {
        type: String,
        default: '',
    },
});

const showQuickFill = ref(false);
const globalAiChat = useGlobalAiChat();

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

const docsCount = computed(() => props.briefDocuments?.length ?? 0);

function toggle() {
    if (!props.collapsible) {
        return;
    }

    open.value = !open.value;
}

function openChat(bootstrap = {}) {
    globalAiChat?.openWithBootstrap({
        project: props.project,
        ...bootstrap,
    });
}

function openAction(item) {
    openChat({
        prompt: item.message,
        mode: item.mode,
        autostart: true,
    });
}
</script>

<template>
    <section
        class="overflow-hidden rounded-2xl border border-f5-border bg-f5-surface shadow-sm dark:border-white/10 dark:bg-f5-black"
    >
        <header
            class="flex items-start justify-between gap-3 border-b border-f5-border bg-f5-surface-2 px-4 py-3.5 dark:border-white/10 dark:bg-white/5"
            :class="collapsible ? 'cursor-pointer select-none' : ''"
            @click="toggle"
        >
            <div class="flex min-w-0 items-center gap-3">
                <button
                    v-if="collapsible"
                    type="button"
                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-f5-text-muted transition-colors hover:bg-black/5 hover:text-f5-text dark:text-white/40 dark:hover:bg-white/10 dark:hover:text-white"
                    :aria-expanded="open"
                    @click.stop="toggle"
                >
                    <span
                        class="inline-block text-[10px] transition-transform duration-300 ease-[cubic-bezier(0.34,1.2,0.64,1)]"
                        :class="{ 'rotate-90': open }"
                        aria-hidden="true"
                    >▶</span>
                </button>

                <div
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-f5-green to-f5-yellow text-xs font-bold text-f5-black"
                >
                    ИИ
                </div>

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-sm font-semibold text-f5-text dark:text-white">
                            ИИ-помощник
                        </h2>
                        <span
                            v-if="docsCount > 0"
                            class="inline-flex min-w-5 items-center justify-center rounded-full bg-f5-green/20 px-1.5 py-0.5 text-[10px] font-semibold tabular-nums text-f5-text dark:bg-f5-green/25 dark:text-f5-green"
                        >
                            {{ docsCount }}
                        </span>
                    </div>
                    <p
                        v-if="!compact"
                        class="text-sm text-f5-text-muted dark:text-white/50"
                    >
                        Анализ проекта, быстрые действия и генерация разделов
                    </p>
                    <p
                        v-else-if="!open"
                        class="truncate text-xs text-f5-text-muted dark:text-white/45"
                    >
                        Чат, документы и быстрые действия
                    </p>
                </div>
            </div>

            <div
                v-if="canEdit && (!collapsible || open)"
                class="flex shrink-0 gap-2"
                :class="compact ? 'flex-col' : 'flex-wrap'"
                @click.stop
            >
                <button
                    type="button"
                    class="f5-btn f5-btn--primary f5-btn--sm"
                    :class="compact ? 'justify-center' : ''"
                    @click="showQuickFill = true"
                >
                    Заполнить
                </button>
                <button
                    type="button"
                    class="f5-btn f5-btn--ghost f5-btn--sm"
                    :class="compact ? 'justify-center' : ''"
                    @click="openChat()"
                >
                    Чат
                </button>
            </div>
            <p
                v-else-if="!canEdit && (!collapsible || open)"
                class="text-xs text-f5-text-muted dark:text-white/50"
            >
                Только для редакторов
            </p>
        </header>

        <div
            v-if="collapsible"
            class="f5-collapse"
            :class="{ 'f5-collapse--open': open }"
        >
            <div class="f5-collapse__inner">
                <div :class="compact ? 'p-4' : 'p-5'">
                    <div
                        v-if="canEdit"
                        class="mb-4 rounded-xl border border-f5-border bg-f5-surface-2/70 p-3 dark:border-white/10 dark:bg-white/5"
                    >
                        <AiModelSelect />
                        <p class="mt-1.5 text-[11px] text-f5-text-muted dark:text-white/45">
                            Личный выбор — используется в чате и быстрых действиях
                        </p>
                    </div>

                    <ProjectAnalysisBlock
                        v-if="aiPrompts.analysis"
                        :project="project"
                        :analysis="aiPrompts.analysis"
                        :compact="compact"
                    />

                    <ProjectBriefDocumentsPanel
                        :project="project"
                        :documents="briefDocuments"
                        :compact="compact"
                        :can-edit="canEdit"
                    />

                    <div
                        v-if="canEdit && aiPrompts.actions?.length"
                        class="flex flex-col gap-2"
                        :class="compact ? 'mt-4' : 'mt-4 flex-wrap sm:flex-row'"
                    >
                        <button
                            v-for="action in aiPrompts.actions"
                            :key="action.id"
                            type="button"
                            class="rounded-xl border border-f5-border bg-f5-surface-2 text-left transition-colors hover:border-f5-green/50 dark:border-white/10 dark:bg-white/5 dark:hover:border-f5-green/40"
                            :class="compact ? 'px-3 py-2.5' : 'inline-flex flex-col px-3 py-2'"
                            :title="action.description"
                            @click="openAction(action)"
                        >
                            <span class="text-sm font-medium text-f5-text dark:text-white">{{ action.label }}</span>
                            <span
                                class="text-f5-text-muted dark:text-white/50"
                                :class="compact ? 'mt-0.5 line-clamp-2 text-xs' : 'text-xs'"
                            >
                                {{ action.description }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div
            v-else
            :class="compact ? 'p-4' : 'p-5'"
        >
            <div
                v-if="canEdit"
                class="mb-4 rounded-xl border border-f5-border bg-f5-surface-2/70 p-3 dark:border-white/10 dark:bg-white/5"
            >
                <AiModelSelect />
                <p class="mt-1.5 text-[11px] text-f5-text-muted dark:text-white/45">
                    Личный выбор — используется в чате и быстрых действиях
                </p>
            </div>

            <ProjectAnalysisBlock
                v-if="aiPrompts.analysis"
                :project="project"
                :analysis="aiPrompts.analysis"
                :compact="compact"
            />

            <ProjectBriefDocumentsPanel
                :project="project"
                :documents="briefDocuments"
                :compact="compact"
                :can-edit="canEdit"
            />

            <div
                v-if="canEdit && aiPrompts.actions?.length"
                class="flex flex-col gap-2"
                :class="compact ? 'mt-4' : 'mt-4 flex-wrap sm:flex-row'"
            >
                <button
                    v-for="action in aiPrompts.actions"
                    :key="action.id"
                    type="button"
                    class="rounded-xl border border-f5-border bg-f5-surface-2 text-left transition-colors hover:border-f5-green/50 dark:border-white/10 dark:bg-white/5 dark:hover:border-f5-green/40"
                    :class="compact ? 'px-3 py-2.5' : 'inline-flex flex-col px-3 py-2'"
                    :title="action.description"
                    @click="openAction(action)"
                >
                    <span class="text-sm font-medium text-f5-text dark:text-white">{{ action.label }}</span>
                    <span
                        class="text-f5-text-muted dark:text-white/50"
                        :class="compact ? 'mt-0.5 line-clamp-2 text-xs' : 'text-xs'"
                    >
                        {{ action.description }}
                    </span>
                </button>
            </div>
        </div>
    </section>

    <AiQuickFillPanel
        v-if="canEdit"
        :project="project"
        :open="showQuickFill"
        @close="showQuickFill = false"
    />
</template>
