<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import ProjectNav from '../../Components/Projects/ProjectNav.vue';
import GoogleDocsModal from '../../Components/GoogleDocsModal.vue';
import { confirmDialog } from '../../utils/confirmDialog';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
    documents: {
        type: Array,
        default: () => [],
    },
    googleConnected: {
        type: Boolean,
        default: false,
    },
    googleConnectUrl: {
        type: String,
        default: '/google/connect',
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success || '');

const openDoc = ref(null);
const modalOpen = ref(false);

function openDocument(document) {
    openDoc.value = {
        id: document.google_file_id,
        name: document.name,
        url: document.url,
    };
    modalOpen.value = true;
}

function closeModal() {
    modalOpen.value = false;
}

async function removeFromList(document) {
    if (props.project.can_edit === false) {
        return;
    }

    const confirmed = await confirmDialog({
        title: 'Убрать документ?',
        message: `«${document.name}» будет убран из списка проекта. Файл в Google Drive останется.`,
        confirmLabel: 'Убрать',
        cancelLabel: 'Отмена',
    });

    if (!confirmed) {
        return;
    }

    router.delete(`/projects/${props.project.id}/documents/${document.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`Документы — ${project.company_name}`" />

    <AppLayout>
        <template #header>
            <div>
                <Link
                    :href="project.urls.show"
                    class="text-sm text-f5-text-muted transition hover:text-f5-text dark:text-white/50 dark:hover:text-white"
                >
                    ← {{ project.company_name }}
                </Link>
                <h1 class="mt-1 text-xl font-bold text-f5-text dark:text-white">
                    Документы Google
                </h1>
            </div>
        </template>

        <div class="mx-auto max-w-5xl space-y-5">
            <ProjectNav
                :project-id="project.id"
                :urls="project.urls"
                current="documents"
            />

            <p
                v-if="flashSuccess"
                class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-500/30 dark:bg-green-500/10 dark:text-green-300"
            >
                {{ flashSuccess }}
            </p>

            <div class="f5-card p-5 sm:p-6">
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-f5-text dark:text-white">
                            Документы по сделке
                        </h2>
                        <p class="mt-1 text-sm text-f5-text-muted dark:text-white/55">
                            Здесь отображаются Google Docs, сохранённые из этого проекта
                            (план внедрения и сценарии «Первая встреча»).
                        </p>
                    </div>
                    <a
                        v-if="!googleConnected"
                        :href="googleConnectUrl"
                        class="f5-btn f5-btn--primary f5-btn--sm shrink-0 self-start"
                    >
                        Подключить Google
                    </a>
                </div>

                <div
                    v-if="documents.length === 0"
                    class="rounded-xl border border-dashed border-f5-border px-4 py-10 text-center dark:border-white/15"
                >
                    <p class="text-sm text-f5-text-muted dark:text-white/50">
                        Пока нет сохранённых документов.
                    </p>
                    <p class="mt-2 text-xs text-f5-text-muted dark:text-white/40">
                        Нажмите «В Google Docs» на странице проекта или в чате встречи.
                    </p>
                </div>

                <ul v-else class="divide-y divide-f5-border dark:divide-white/10">
                    <li
                        v-for="document in documents"
                        :key="document.id"
                        class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <p class="truncate font-medium text-f5-text dark:text-white">
                                {{ document.name }}
                            </p>
                            <p class="mt-1 text-xs text-f5-text-muted dark:text-white/45">
                                {{ document.source_label }}
                                <span v-if="document.author"> · {{ document.author }}</span>
                                <span v-if="document.created_at"> · {{ document.created_at }}</span>
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="f5-btn f5-btn--primary f5-btn--sm"
                                @click="openDocument(document)"
                            >
                                Открыть
                            </button>
                            <a
                                :href="document.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="f5-btn f5-btn--ghost f5-btn--sm"
                            >
                                В новой вкладке
                            </a>
                            <button
                                v-if="project.can_edit !== false"
                                type="button"
                                class="f5-btn f5-btn--ghost f5-btn--sm text-red-600 dark:text-red-400"
                                @click="removeFromList(document)"
                            >
                                Убрать
                            </button>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <GoogleDocsModal
            :open="modalOpen"
            :document="openDoc"
            @close="closeModal"
        />
    </AppLayout>
</template>
