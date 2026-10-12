<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { confirmDialog } from '../../utils/confirmDialog';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
    editorOptions: {
        type: Array,
        default: () => [],
    },
});

const open = ref(false);
const editorSearch = ref('');
const canManage = computed(() => props.project.can_manage_editors);
const canEdit = computed(() => props.project.can_edit);
const canDelete = computed(() => props.project.can_delete);
const deleting = ref(false);

const editorsSummary = computed(() => {
    const editors = props.project.editors ?? [];

    if (editors.length === 0) {
        return 'доп. нет';
    }

    if (editors.length === 1) {
        return editors[0].name;
    }

    return `${editors.length} сотр.`;
});

const filteredEditorOptions = computed(() => {
    const query = editorSearch.value.trim().toLocaleLowerCase('ru-RU');
    const selected = new Set(form.user_ids.map(Number));

    const filtered = props.editorOptions.filter((user) => {
        if (!query) {
            return true;
        }

        const name = String(user.name ?? '').toLocaleLowerCase('ru-RU');
        const role = String(user.role_label ?? '').toLocaleLowerCase('ru-RU');

        return name.includes(query) || role.includes(query);
    });

    return [...filtered].sort((a, b) => {
        const aSelected = selected.has(Number(a.id)) ? 0 : 1;
        const bSelected = selected.has(Number(b.id)) ? 0 : 1;

        if (aSelected !== bSelected) {
            return aSelected - bSelected;
        }

        return String(a.name ?? '').localeCompare(String(b.name ?? ''), 'ru');
    });
});

const form = useForm({
    user_ids: [...(props.project.editor_ids ?? [])],
});

watch(
    () => props.project.editor_ids,
    (ids) => {
        form.user_ids = [...(ids ?? [])];
    },
    { deep: true },
);

watch(open, (isOpen) => {
    document.body.style.overflow = isOpen ? 'hidden' : '';

    if (isOpen) {
        form.user_ids = [...(props.project.editor_ids ?? [])];
        form.clearErrors();
        editorSearch.value = '';
    }
});

function onKeydown(event) {
    if (event.key === 'Escape' && open.value) {
        close();
    }
}

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});

function close() {
    open.value = false;
}

function toggleEditor(userId) {
    const id = Number(userId);
    const next = new Set(form.user_ids.map(Number));

    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }

    form.user_ids = [...next];
}

function isSelected(userId) {
    return form.user_ids.map(Number).includes(Number(userId));
}

function submit() {
    form.put(props.project.urls.editors_update, {
        preserveScroll: true,
        onSuccess: () => close(),
    });
}

async function deleteProject() {
    if (!canDelete.value || !props.project.urls.destroy) {
        return;
    }

    const confirmed = await confirmDialog({
        title: 'Удалить проект?',
        message: `Проект «${props.project.company_name}» и все связанные данные (воронки, поля, интеграции, чаты ИИ) будут удалены без возможности восстановления.`,
        confirmLabel: 'Удалить проект',
        cancelLabel: 'Отмена',
        variant: 'danger',
    });

    if (!confirmed) {
        return;
    }

    deleting.value = true;
    router.delete(props.project.urls.destroy, {
        onFinish: () => {
            deleting.value = false;
        },
    });
}
</script>

<template>
    <div>
        <button
            type="button"
            class="flex w-full items-center justify-between gap-3 rounded-2xl border border-f5-border bg-f5-surface px-4 py-3 text-left shadow-sm transition hover:border-f5-green/50 dark:border-white/10 dark:bg-f5-black dark:hover:border-f5-green/40"
            @click="open = true"
        >
            <span class="min-w-0">
                <span class="block text-xs font-semibold uppercase tracking-wide text-f5-text-muted dark:text-white/40">
                    Доступ к проекту
                </span>
                <span class="mt-0.5 block truncate text-sm text-f5-text dark:text-white">
                    {{ project.manager?.name || project.author }}
                    <span class="text-f5-text-muted dark:text-white/50"> · {{ editorsSummary }}</span>
                </span>
            </span>
            <span class="shrink-0 text-xs font-medium text-f5-green">
                Открыть
            </span>
        </button>

        <Teleport to="body">
            <div
                v-if="open"
                class="fixed inset-0 z-[90] flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4"
                role="dialog"
                aria-modal="true"
                aria-labelledby="project-access-title"
                @click.self="close"
            >
                <div class="flex max-h-[min(92vh,720px)] w-full max-w-lg flex-col overflow-hidden rounded-t-2xl border border-f5-border bg-f5-surface shadow-xl dark:border-white/10 dark:bg-f5-black sm:rounded-2xl">
                    <div class="flex shrink-0 items-start justify-between gap-3 border-b border-f5-border px-5 py-4 dark:border-white/10">
                        <div class="min-w-0">
                            <h2
                                id="project-access-title"
                                class="text-base font-semibold text-f5-text dark:text-white"
                            >
                                Доступ к проекту
                            </h2>
                            <p class="mt-1 text-xs text-f5-text-muted dark:text-white/50">
                                Права просмотра, редактирования и удаления
                            </p>
                        </div>
                        <button
                            type="button"
                            class="f5-btn f5-btn--ghost f5-btn--sm shrink-0"
                            @click="close"
                        >
                            Закрыть
                        </button>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                        <dl class="space-y-3 text-sm">
                            <div>
                                <dt class="text-f5-text-muted dark:text-white/50">Ответственный менеджер</dt>
                                <dd class="mt-0.5 font-medium text-f5-text dark:text-white">
                                    {{ project.manager?.name || project.author }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-f5-text-muted dark:text-white/50">Редактирование</dt>
                                <dd class="mt-0.5 text-f5-text dark:text-white">
                                    Менеджер, его РОП
                                    <span v-if="project.editors?.length">
                                        и {{ project.editors.map((item) => item.name).join(', ') }}
                                    </span>
                                    <span v-else-if="!canManage">
                                        · доп. сотрудники не назначены
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-f5-text-muted dark:text-white/50">Просмотр</dt>
                                <dd class="mt-0.5 text-f5-text dark:text-white">
                                    Доступен всем сотрудникам
                                </dd>
                            </div>
                        </dl>

                        <form
                            v-if="canManage"
                            class="mt-5 space-y-3 border-t border-f5-border pt-4 dark:border-white/10"
                            @submit.prevent="submit"
                        >
                            <p class="text-sm font-medium text-f5-text dark:text-white">
                                Доп. сотрудники с правом изменений
                            </p>
                            <p class="text-xs text-f5-text-muted dark:text-white/50">
                                Они смогут менять проект наравне с менеджером и РОПом.
                            </p>

                            <div
                                v-if="editorOptions.length === 0"
                                class="text-sm text-f5-text-muted dark:text-white/50"
                            >
                                Нет других пользователей для назначения.
                            </div>

                            <template v-else>
                                <div>
                                    <label for="editor_search" class="f5-label">Поиск сотрудника</label>
                                    <input
                                        id="editor_search"
                                        v-model="editorSearch"
                                        type="search"
                                        class="f5-input"
                                        placeholder="Имя или роль…"
                                        autocomplete="off"
                                    >
                                </div>

                                <p
                                    v-if="form.user_ids.length > 0"
                                    class="text-xs text-f5-text-muted dark:text-white/50"
                                >
                                    Выбрано: {{ form.user_ids.length }}
                                </p>

                                <div class="max-h-48 space-y-1 overflow-y-auto rounded-xl border border-f5-border p-2 dark:border-white/10">
                                    <p
                                        v-if="filteredEditorOptions.length === 0"
                                        class="px-2 py-3 text-sm text-f5-text-muted dark:text-white/50"
                                    >
                                        Никого не найдено.
                                    </p>

                                    <label
                                        v-for="user in filteredEditorOptions"
                                        :key="user.id"
                                        class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-f5-surface-2 dark:hover:bg-white/5"
                                        :class="isSelected(user.id) ? 'bg-f5-green/10 dark:bg-f5-green/15' : ''"
                                    >
                                        <input
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-f5-border text-f5-green focus:ring-f5-green/30 dark:border-white/20"
                                            :checked="isSelected(user.id)"
                                            @change="toggleEditor(user.id)"
                                        >
                                        <span class="min-w-0 flex-1 text-f5-text dark:text-white">{{ user.name }}</span>
                                        <span class="shrink-0 text-xs text-f5-text-muted dark:text-white/40">{{ user.role_label }}</span>
                                    </label>
                                </div>
                            </template>

                            <p v-if="form.errors.user_ids" class="f5-error">{{ form.errors.user_ids }}</p>

                            <button
                                type="submit"
                                class="f5-btn f5-btn--primary f5-btn--sm"
                                :disabled="form.processing"
                            >
                                {{ form.processing ? 'Сохранение…' : 'Сохранить доступ' }}
                            </button>
                        </form>

                        <p
                            v-else-if="!canEdit"
                            class="mt-4 rounded-lg border border-f5-border bg-f5-surface-2/60 px-3 py-2 text-xs text-f5-text-muted dark:border-white/10 dark:bg-white/5 dark:text-white/60"
                        >
                            У вас режим просмотра: изменения недоступны.
                        </p>

                        <div
                            v-if="canDelete"
                            class="mt-5 space-y-2 border-t border-f5-border pt-4 dark:border-white/10"
                        >
                            <p class="text-sm font-medium text-f5-text dark:text-white">
                                Удаление проекта
                            </p>
                            <p class="text-xs text-f5-text-muted dark:text-white/50">
                                Удалить проект может ответственный менеджер или администратор. Действие необратимо.
                            </p>
                            <button
                                type="button"
                                class="f5-btn f5-btn--danger f5-btn--sm"
                                :disabled="deleting"
                                @click="deleteProject"
                            >
                                {{ deleting ? 'Удаление…' : 'Удалить проект' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
